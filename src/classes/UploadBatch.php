<?php

declare(strict_types=1);

/**
 * A group of uploaded files (video/audio, possibly mixed with images) that can't
 * be transcoded fast enough to handle inline, so the whole post is staged to
 * disk and drained by the upload-worker service (bin/upload-worker.php, which
 * runs bin/process-upload.php per batch at a bounded concurrency) instead.
 *
 * A batch directory moves through two locations:
 *   pending/<id>/     just staged; claimable by the worker service
 *   processing/<id>/  claimed by a live worker (or crashed, awaiting recovery)
 *
 * Within a claimed batch the files are transcoded one at a time, with per-file
 * progress persisted (progress.json) so a crash is resumable: already-finished
 * files aren't redone, and a file whose transcode keeps killing the worker
 * (OOM-kill, segfault - NOT a clean transcode failure, which is handled in one
 * pass) is retried up to MAX_FILE_DEATHS times, then dropped. The post is
 * assembled from whatever files survived; only if none survive is the whole
 * upload marked failed.
 */
class UploadBatch
{
    // A batch is assembled here first, then atomically renamed into pending/ as
    // its last step - so the worker can never claim a half-copied batch.
    private const STAGING_DIR = __DIR__ . '/../../uploads/private/staging';
    private const PENDING_DIR = __DIR__ . '/../../uploads/private/pending';
    private const PROCESSING_DIR = __DIR__ . '/../../uploads/private/processing';

    // How many times a single file may kill its worker process before it's
    // abandoned and dropped from the post (see the class docblock).
    private const MAX_FILE_DEATHS = 3;

    public static function stage(int $user_id, ?int $parent_id, ?string $title, ?string $description, ?string $description_delta, ?string $link_url, array $files): string
    {
        // Same lottery sweep as UploadProcessor::sweepStagedLinkImages().
        // Triggered here (the web path that stages a batch), not from the
        // worker - a batch is only ever created here, so sweep frequency tracks
        // batch-creation frequency.
        if (mt_rand(1, 100) === 1) {
            self::sweepOrphanedBatches();
        }

        self::ensureDir(self::STAGING_DIR);
        self::ensureDir(self::PENDING_DIR);

        // Build the whole batch under staging/ (copying a large upload takes real
        // time), then publish it into pending/ with one atomic rename. The worker
        // only ever scans pending/, so it can never claim a batch mid-copy and
        // find it vanish out from under the still-writing web request.
        $batch_id = bin2hex(random_bytes(16));
        $staging_dir = self::STAGING_DIR . '/' . $batch_id;
        mkdir($staging_dir, 0755, true);

        $staged_files = [];

        foreach (array_values($files) as $index => $file) {
            copy($file['tmpPath'], $staging_dir . '/' . $index);
            $staged_files[] = ['originalFilename' => $file['originalFilename']];
        }

        file_put_contents($staging_dir . '/metadata.json', json_encode([
            'userId' => $user_id,
            'parentId' => $parent_id,
            'title' => $title,
            'description' => $description,
            'descriptionDelta' => $description_delta,
            'linkURL' => $link_url,
            'files' => $staged_files,
        ]));

        rename($staging_dir, self::PENDING_DIR . '/' . $batch_id);

        return $batch_id;
    }

    /**
     * Atomically claims the oldest pending batch for the calling worker by
     * moving it into processing/, returning its id (or null if none pending).
     * The rename is the claim - the upload-worker service is the only claimer,
     * so there's no contention, but the atomic move still cleanly separates
     * "claimable" from "owned/crashed" state for recovery.
     */
    public static function claimNext(): ?string
    {
        if (!is_dir(self::PENDING_DIR)) {
            return null;
        }

        self::ensureDir(self::PROCESSING_DIR);

        $dirs = glob(self::PENDING_DIR . '/*', GLOB_ONLYDIR) ?: [];

        // Oldest first, so the queue drains FIFO.
        usort($dirs, fn ($a, $b) => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));

        foreach ($dirs as $dir) {
            $batch_id = basename($dir);
            $target = self::PROCESSING_DIR . '/' . $batch_id;

            if (@rename($dir, $target)) {
                // Freshen the mtime so the orphan sweep ages a batch from when it
                // was last worked on, not from when it was first staged - a long
                // multi-file transcode (or a resume) can't be swept out from
                // under a live worker just for being old (progress.json is
                // rewritten in place, which doesn't bump the dir's own mtime).
                @touch($target);

                return $batch_id;
            }
        }

        return null;
    }

    /**
     * Moves a claimed batch back to pending/ so it's re-claimed and retried
     * (its progress.json is preserved, so the retry resumes at the remaining
     * files rather than redoing finished ones).
     */
    public static function requeue(string $batch_id): void
    {
        self::ensureDir(self::PENDING_DIR);
        $target = self::PENDING_DIR . '/' . $batch_id;

        if (@rename(self::PROCESSING_DIR . '/' . $batch_id, $target)) {
            @touch($target);
        }
    }

    /**
     * Releases a claimed batch back to pending WITHOUT counting a file death -
     * used when the worker service is shutting down and terminates an in-flight
     * child itself, so a graceful stop (or the daily restart) mid-transcode
     * doesn't penalise the file that happened to be in flight. A batch already
     * in the DB-assembly phase is left in processing/ (like recoverDied) rather
     * than risk a duplicate post.
     */
    public static function releaseClaim(string $batch_id): void
    {
        $batch_dir = self::PROCESSING_DIR . '/' . $batch_id;
        $metadata_path = $batch_dir . '/metadata.json';

        if (is_file($metadata_path)) {
            $progress = self::loadProgress($batch_dir, json_decode((string) file_get_contents($metadata_path), true));

            if (!empty($progress['finalizing'])) {
                return;
            }

            $progress['started'] = null;
            self::saveProgress($batch_dir, $progress);
        }

        self::requeue($batch_id);
    }

    /**
     * Transcodes a claimed batch's outstanding files one at a time (recording
     * per-file progress so a crash is resumable), then assembles the surviving
     * files into a Post. Runs under bin/process-upload.php as a child of the
     * worker service. Idempotent across retries: finished files are skipped.
     */
    public static function process(string $batch_id): void
    {
        $batch_dir = self::PROCESSING_DIR . '/' . $batch_id;
        $metadata_path = $batch_dir . '/metadata.json';

        if (!is_file($metadata_path)) {
            return;
        }

        $metadata = json_decode((string) file_get_contents($metadata_path), true);
        $progress = self::loadProgress($batch_dir, $metadata);

        foreach ($progress['files'] as $index => $file) {
            if ($file['status'] !== 'pending') {
                continue;
            }

            // Record which file is in flight BEFORE decoding it, so a crash mid-
            // transcode is attributed to exactly this file on recovery.
            $progress['started'] = $index;
            self::saveProgress($batch_dir, $progress);

            $file_meta = $metadata['files'][$index];
            $result = UploadProcessor::process($batch_dir . '/' . $index, $file['seed'], $file_meta['originalFilename']);

            if ($result === null) {
                // Clean transcode failure (bad media, killed ffmpeg, oversized).
                $progress['files'][$index]['status'] = 'failed';
            } else {
                $progress['files'][$index]['status'] = 'done';
                $progress['files'][$index]['itemType'] = $result['itemType'];
                $progress['files'][$index]['ext'] = UploadProcessor::safeExtension($file_meta['originalFilename']);
            }

            $progress['started'] = null;
            self::saveProgress($batch_dir, $progress);
        }

        // Every file is now terminal (done or failed). Mark finalizing so a
        // crash during the DB-only assembly below is NOT retried - assembly is
        // near-instant and a duplicate post is worse than the rare lost one
        // (which the orphan sweep cleans up, exactly as before this queue).
        $progress['finalizing'] = true;
        self::saveProgress($batch_dir, $progress);

        self::finalize($batch_dir, $metadata, $progress);
    }

    /**
     * Assembles the post from the files that survived, and notifies the author:
     * postReady when at least one file is live, an extra uploadPartlyFailed
     * warning when some files were dropped, or uploadFailed when none survived.
     */
    private static function finalize(string $batch_dir, array $metadata, array $progress): void
    {
        $survivors = array_filter($progress['files'], fn ($file) => $file['status'] === 'done');
        $any_failed = array_filter($progress['files'], fn ($file) => $file['status'] === 'failed') !== [];
        $user_id = (int) $metadata['userId'];

        if ($survivors === []) {
            // Whole upload failed - no post, just tell the author.
            Notification::create($user_id, $user_id, 'uploadFailed', null, true);
            self::cleanupBatch($batch_dir, $progress);

            return;
        }

        $mysqli = DB::connection();

        $title_value = $metadata['title'] !== null && $metadata['title'] !== '' ? $metadata['title'] : null;
        $description_value = $metadata['description'] !== null && $metadata['description'] !== '' ? $metadata['description'] : null;
        $link_url_value = $metadata['linkURL'] !== null && $metadata['linkURL'] !== '' ? $metadata['linkURL'] : null;
        $parent_id = $metadata['parentId'];

        // A batch staged after the Delta migration carries the Delta JSON (and
        // its description is already the derived plaintext). One staged before
        // it has no descriptionDelta key and an old-style HTML description -
        // convert that here so a mid-deploy batch still finishes as a rendered
        // post rather than a permanently bodyless one.
        $description_delta_value = $metadata['descriptionDelta'] ?? null;

        if ($description_delta_value === null && $description_value !== null) {
            $ops = Delta::sanitize(HTMLToDelta::convert($description_value));

            if (Delta::isBlank($ops)) {
                $description_value = null;
            } else {
                $description_value = Delta::plainText($ops);
                $description_delta_value = json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // The post, its timeline fan-out, and its FeedItem rows go in as one
        // transaction: a crash during this DB-only assembly (see the finalizing
        // note in process()) then rolls back cleanly rather than leaving a
        // fanned-out post with no media rows. The seed->itemId file renames run
        // inside the commit; a rollback leaves them as invisible orphan files
        // rather than a visibly broken post. Notifications fire only AFTER the
        // commit, so a rolled-back assembly signals nothing.
        mysqli_begin_transaction($mysqli);

        DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `descriptionDelta`, `linkURL`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'iissss', $metadata['userId'], $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value);
        $post_id = (int) mysqli_insert_id($mysqli);

        $mentioned_user_ids = [];

        if ($description_delta_value !== null) {
            $description_ops = Delta::decode($description_delta_value);
            Hashtag::indexPost($post_id, $description_ops);
            $mentioned_user_ids = Mention::indexPost($post_id, $description_ops);
        }

        $parent_user_id = null;

        if ($parent_id !== null) {
            $parent_post = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $parent_id);
            $parent_user_id = $parent_post !== null ? (int) $parent_post -> userId : null;
        } else {
            Timeline::fanOutPost($user_id, $post_id);
        }

        foreach ($survivors as $file) {
            $item_type = $file['itemType'];
            DB::run('
INSERT INTO `FeedItems` (`postId`, `itemType`)
    VALUES (?, ?)
', 'is', $post_id, $item_type);
            $item_id = (int) mysqli_insert_id($mysqli);

            UploadProcessor::rename($file['seed'], $item_id, $file['itemType'], $file['ext']);
        }

        mysqli_commit($mysqli);

        // A reply notifies the parent's author; postReady always tells the
        // uploader their post is live; uploadPartlyFailed warns them if some of
        // their files were dropped along the way.
        if ($parent_user_id !== null) {
            Notification::create($parent_user_id, $user_id, 'reply', $parent_id);
        }

        Mention::notify($mentioned_user_ids, $user_id, $post_id);

        Notification::create($user_id, $user_id, 'postReady', $post_id, true);

        if ($any_failed) {
            Notification::create($user_id, $user_id, 'uploadPartlyFailed', $post_id, true);
        }

        self::cleanupBatch($batch_dir, $progress);
    }

    /**
     * Recovers a batch whose worker process died (crash / OOM-kill / nonzero
     * exit) while it held the claim. Called by the upload-worker service both
     * when it reaps an abnormally-exited child and, at startup, for every batch
     * left in processing/ by a prior run.
     */
    public static function recoverDied(string $batch_id): void
    {
        $batch_dir = self::PROCESSING_DIR . '/' . $batch_id;

        if (!is_dir($batch_dir)) {
            return;
        }

        $metadata_path = $batch_dir . '/metadata.json';

        if (!is_file($metadata_path)) {
            self::cleanupDir($batch_dir);

            return;
        }

        $progress = self::loadProgress($batch_dir, json_decode((string) file_get_contents($metadata_path), true));

        // Died during the DB-only assembly phase: not retried (would risk a
        // duplicate post). Left in processing/ for the orphan sweep, matching
        // the pre-queue behaviour where an assembly crash just lost the post.
        if (!empty($progress['finalizing'])) {
            return;
        }

        $started = $progress['started'] ?? null;

        if ($started !== null && isset($progress['files'][$started])) {
            // Attribute the death to the file that was in flight.
            $progress['files'][$started]['deaths'] = ($progress['files'][$started]['deaths'] ?? 0) + 1;

            if ($progress['files'][$started]['deaths'] >= self::MAX_FILE_DEATHS) {
                // This file has killed the worker too many times - drop it. Its
                // partial output (named by the reused seed) is purged so retries
                // don't leak it.
                $progress['files'][$started]['status'] = 'failed';
                UploadProcessor::purgeStaged($progress['files'][$started]['seed']);
            }

            $progress['started'] = null;
            self::saveProgress($batch_dir, $progress);
        }

        // Retry: back to pending/ so the service re-claims it and resumes at the
        // remaining pending files.
        self::requeue($batch_id);
    }

    /**
     * At worker-service startup, every batch sitting in processing/ was left by
     * a prior run that died (no worker is alive yet), so each is recovered.
     */
    public static function recoverOrphanedProcessing(): void
    {
        if (!is_dir(self::PROCESSING_DIR)) {
            return;
        }

        foreach (glob(self::PROCESSING_DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            self::recoverDied(basename($dir));
        }
    }

    /**
     * Loads a batch's per-file progress, initialising it (all files pending,
     * each with a stable staging seed reused across retries so a re-transcode
     * overwrites rather than orphaning its output) on first run.
     *
     * @return array{files: array<int, array<string, mixed>>, started: int|null, finalizing: bool}
     */
    private static function loadProgress(string $batch_dir, array $metadata): array
    {
        $progress_path = $batch_dir . '/progress.json';

        if (is_file($progress_path)) {
            $decoded = json_decode((string) file_get_contents($progress_path), true);

            if (is_array($decoded) && isset($decoded['files'])) {
                return $decoded;
            }
        }

        $files = [];

        foreach (array_keys($metadata['files']) as $index) {
            $files[$index] = [
                'status' => 'pending',
                'deaths' => 0,
                'seed' => bin2hex(random_bytes(8)) . '-' . $index,
            ];
        }

        $progress = ['files' => $files, 'started' => null, 'finalizing' => false];
        self::saveProgress($batch_dir, $progress);

        return $progress;
    }

    private static function saveProgress(string $batch_dir, array $progress): void
    {
        // Write-then-rename so a crash mid-write can't leave truncated JSON - a
        // corrupt progress.json would make loadProgress re-initialise the batch
        // (deaths reset, new seeds orphaning already-transcoded output).
        $tmp = $batch_dir . '/progress.json.tmp';
        file_put_contents($tmp, json_encode($progress));
        rename($tmp, $batch_dir . '/progress.json');
    }

    /**
     * Removes a finished batch: purges any staging output still named by a seed
     * (a survivor's was already renamed onto its itemId, so this only catches a
     * dropped file's partial), then deletes the batch directory.
     */
    private static function cleanupBatch(string $batch_dir, array $progress): void
    {
        foreach ($progress['files'] as $file) {
            if (isset($file['seed'])) {
                UploadProcessor::purgeStaged($file['seed']);
            }
        }

        self::cleanupDir($batch_dir);
    }

    /**
     * Removes batch directories left behind by an upload that never completed -
     * scans staging/ (a stage that died mid-copy), pending/ (never claimed, e.g.
     * the service was down), and processing/ (left mid-assembly). process()/
     * recovery clean up a batch they finish, and claim/requeue touch a batch's
     * mtime so a live one is never aged out; anything older than the cutoff is a
     * genuine orphan (no real transcode runs anywhere near a day).
     */
    /**
     * How many batches are sitting in each stage of the queue right now - a
     * cheap directory count (the queue itself is directory-based, see the
     * class docblock), not a DB query. Lets the admin Site Settings page tell
     * "dead" from "alive but backlogged" instead of SSHing in to check.
     *
     * @return array{staging: int, pending: int, processing: int}
     */
    public static function queueDepth(): array
    {
        return [
            'staging' => count(glob(self::STAGING_DIR . '/*', GLOB_ONLYDIR) ?: []),
            'pending' => count(glob(self::PENDING_DIR . '/*', GLOB_ONLYDIR) ?: []),
            'processing' => count(glob(self::PROCESSING_DIR . '/*', GLOB_ONLYDIR) ?: []),
        ];
    }

    /**
     * Whether the upload-worker systemd service is currently running - a
     * `systemctl is-active` shell-out, checked as both a system-level unit (a
     * root/sudo install) and a user-level one (see EnvironmentChecker's
     * install-time persistence check for why both exist), first definitive
     * match wins. Read-only service-status queries need no special Unix
     * privilege, but on an Enforcing SELinux host they can still be denied by
     * policy to the web server's own domain (confirmed live: `systemctl
     * is-active` from PHP-FPM returned nothing but "Access denied" on stderr,
     * which - discarded via 2>/dev/null - used to read back as an empty
     * string and get folded into a false "dead"). bin/install.php's
     * ensure_httpd_can_query_systemd_status() fixes that at the source; this
     * only tells the two apart so a host that hasn't run it yet reports
     * "don't know" instead of confidently lying that a healthy worker is down.
     */
    public static function workerIsActive(): ?bool
    {
        if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
            return null;
        }

        $system = self::systemdUnitActiveState('systemctl is-active glommer-upload-worker.service 2>/dev/null');
        $user = self::systemdUnitActiveState('systemctl is-active --user glommer-upload-worker.service 2>/dev/null');

        if ($system === true || $user === true) {
            return true;
        }

        if ($system === false || $user === false) {
            return false;
        }

        return null;
    }

    /**
     * Maps a `systemctl is-active` result to a definitive true/false, or null
     * when the output isn't one of systemd's own terminal ActiveState values -
     * e.g. blank (a permission-denied error suppressed by 2>/dev/null) or
     * 'unknown'. Trusting only the states systemd itself defines, rather than
     * just checking for 'active', is what lets a denied/indeterminate query
     * be told apart from a genuinely stopped unit.
     */
    private static function systemdUnitActiveState(string $command): ?bool
    {
        return match (trim((string) shell_exec($command))) {
            'active', 'activating', 'reloading' => true,
            'inactive', 'failed', 'deactivating' => false,
            default => null,
        };
    }

    public static function sweepOrphanedBatches(): void
    {
        $cutoff = time() - 86400;

        foreach ([self::STAGING_DIR, self::PENDING_DIR, self::PROCESSING_DIR] as $base) {
            foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $batch_dir) {
                $modified_at = filemtime($batch_dir);

                if ($modified_at === false || $modified_at >= $cutoff) {
                    continue;
                }

                // Tell the author their long-orphaned upload was dropped rather
                // than deleting it silently - the metadata still holds their
                // userId. A half-written staging orphan has no readable metadata,
                // so it's just cleaned up.
                $metadata = json_decode((string) @file_get_contents($batch_dir . '/metadata.json'), true);

                if (is_array($metadata) && isset($metadata['userId'])) {
                    Notification::create((int) $metadata['userId'], (int) $metadata['userId'], 'uploadFailed', null, true);
                }

                self::cleanupDir($batch_dir);
            }
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            // 0777 so the dir is writable by BOTH the web-server user (which
            // stages batches) and the worker-service user (which claims and
            // renames them) - commonly different Unix accounts, and neither can
            // chmod a dir the other created, so it must be world-writable from
            // creation (mkdir's mode is umask-masked, hence the explicit chmod).
            // The private/ tree is already blocked from web reads by its
            // .htaccess; this matches the rest of the uploads/ tree.
            mkdir($dir, 0777, true);
            @chmod($dir, 0777);
        }
    }

    private static function cleanupDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($dir);
    }
}
