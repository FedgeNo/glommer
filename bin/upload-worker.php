<?php

declare(strict_types=1);

/**
 * Media-upload worker service: `php bin/upload-worker.php`.
 *
 * A long-running supervisor that drains the async transcode queue (batches
 * staged by UploadBatch::stage under uploads/private/pending) at a BOUNDED
 * concurrency, so a burst of uploads can't spawn unlimited concurrent ffmpeg
 * processes and take the host down. It claims pending batches and runs each in
 * a child (bin/process-upload.php), never more than uploadWorkerConcurrency at
 * once. When a child dies abnormally (OOM-kill, crash) it hands the batch to
 * UploadBatch::recoverDied, which retries it (resuming at the unfinished files)
 * up to a per-file death cap. Completion is signalled the same way as before -
 * process()/finalize create the postReady / uploadPartlyFailed / uploadFailed
 * notification, pushed live over the WebSocket.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// The supervisor itself is light (it only shuffles files and manages child
// processes); each child sets its own, larger limit.
ini_set('memory_limit', '128M');

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function log_line(string $message): void
{
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n");
}

/**
 * Sends a systemd service notification (READY=1, WATCHDOG=1, STOPPING=1) to
 * $NOTIFY_SOCKET. A no-op when not run under systemd, so running by hand still
 * works. Best-effort - any failure is swallowed. (Mirrors the WS daemon's.)
 */
function sd_notify(string $state): void
{
    $socket_path = getenv('NOTIFY_SOCKET');

    if ($socket_path === false || $socket_path === '') {
        return;
    }

    // An '@'-prefixed path names an abstract-namespace socket, where the '@'
    // stands in for a leading NUL byte.
    if ($socket_path[0] === '@') {
        $socket_path = "\0" . substr($socket_path, 1);
    }

    $socket = @socket_create(AF_UNIX, SOCK_DGRAM, 0);

    if ($socket === false) {
        return;
    }

    @socket_sendto($socket, $state, strlen($state), 0, $socket_path);
    socket_close($socket);
}

$config = require __DIR__ . '/../src/config.php';
$concurrency = max(1, (int) $config['uploadWorkerConcurrency']);

// Graceful shutdown on systemd stop / Ctrl-C: stop claiming, cleanly release
// in-flight batches (no fault, so a shutdown mid-transcode doesn't count as a
// file death), and exit. pcntl_async_signals delivers the signal during the
// poll sleep, so shutdown is prompt.
pcntl_async_signals(true);
$shutting_down = false;
$request_shutdown = function () use (&$shutting_down): void {
    $shutting_down = true;
};
pcntl_signal(SIGTERM, $request_shutdown);
pcntl_signal(SIGINT, $request_shutdown);

// Single-instance guard. Under systemd the unit is a singleton and a
// stop/restart kills the whole cgroup (old children included) before the new
// instance starts, so recovery never races a live child. But nothing stops an
// admin hand-running a second copy alongside the service (or a non-cgroup
// process manager leaving an old one alive) - two supervisors would both claim
// and finalize the same batches (duplicate posts). Hold an exclusive lock for
// our whole lifetime; a second instance exits immediately. The lock is released
// automatically when this process dies.
$lock_path = __DIR__ . '/../uploads/private/upload-worker.lock';
$lock_handle = fopen($lock_path, 'c');

if ($lock_handle === false || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
    log_line('Another upload worker already holds the lock - exiting');
    exit(0);
}

// Anything left in processing/ by a prior run died with it - recover before
// claiming new work.
UploadBatch::recoverOrphanedProcessing();

log_line('Upload worker starting (concurrency ' . $concurrency . ')');

// systemd watchdog: ping at half the configured interval so a hung supervisor
// loop is noticed and restarted. Disabled when run by hand (no WATCHDOG_USEC).
$watchdog_usec = (int) (getenv('WATCHDOG_USEC') ?: 0);
$watchdog_interval = $watchdog_usec > 0 ? $watchdog_usec / 2000000 : 0.0;
$last_watchdog_ping = microtime(true);

sd_notify('READY=1');

if ($watchdog_interval > 0) {
    sd_notify('WATCHDOG=1');
}

$php_binary = PHP_BINARY;
$worker_script = __DIR__ . '/process-upload.php';

/** @var array<string, array{proc: resource, pipes: array<int, resource>}> $workers batchId => handle */
$workers = [];

while (true) {
    // 1. Reap any children that have exited.
    foreach ($workers as $batch_id => $worker) {
        $status = proc_get_status($worker['proc']);

        if ($status['running']) {
            continue;
        }

        // Capture the exit disposition from this first post-exit read
        // (proc_get_status reports the real exitcode only once).
        $died = $status['signaled'] || $status['exitcode'] !== 0;

        foreach ($worker['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($worker['proc']);
        unset($workers[$batch_id]);

        if ($died) {
            if ($shutting_down) {
                // A shutdown SIGTERM reaches the whole cgroup, so a child usually
                // dies here (in the reap step) before step 4's graceful stop runs.
                // Release it with no fault, so stopping the service mid-transcode
                // (or the systemd restart) doesn't count a spurious file death and
                // eventually drop a good file.
                UploadBatch::releaseClaim($batch_id);
            } else {
                // The worker process itself was killed / crashed (a clean
                // transcode failure returns normally and exits 0); retry the
                // batch, resuming at its unfinished files.
                log_line('Worker for batch ' . $batch_id . ' died - recovering');
                UploadBatch::recoverDied($batch_id);
            }
        }
    }

    // 2. Fill free slots with pending work (never while shutting down).
    if (!$shutting_down) {
        while (count($workers) < $concurrency) {
            $batch_id = UploadBatch::claimNext();

            if ($batch_id === null) {
                break;
            }

            $descriptors = [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ];

            $proc = proc_open([$php_binary, $worker_script, $batch_id], $descriptors, $pipes);

            if (!is_resource($proc)) {
                // Couldn't spawn - release the claim and stop filling for now.
                UploadBatch::releaseClaim($batch_id);
                break;
            }

            $workers[$batch_id] = ['proc' => $proc, 'pipes' => $pipes];
        }
    }

    // 3. Watchdog ping at half the configured interval.
    if ($watchdog_interval > 0 && microtime(true) - $last_watchdog_ping >= $watchdog_interval) {
        sd_notify('WATCHDOG=1');
        $last_watchdog_ping = microtime(true);
    }

    // 4. Shutting down: stop the in-flight children and cleanly release their
    // batches (no fault), then exit. Unfinished work is retried next start.
    if ($shutting_down) {
        foreach ($workers as $batch_id => $worker) {
            proc_terminate($worker['proc']);

            foreach ($worker['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            // proc_close waits for the child to actually exit before we touch
            // its batch state.
            proc_close($worker['proc']);
            UploadBatch::releaseClaim($batch_id);
        }

        break;
    }

    // Poll interval. Transcodes take seconds, so 1s of queue latency is
    // invisible; the signal handler fires during this sleep for prompt shutdown.
    usleep(1000000);
}

sd_notify('STOPPING=1');
log_line('Upload worker stopped');
