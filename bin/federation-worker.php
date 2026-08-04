<?php

declare(strict_types=1);

/**
 * Drains the outbound federation queue: `php bin/federation-worker.php`.
 *
 * Delivery cannot happen inside the request that caused it. Someone with a
 * thousand followers posting is a thousand HTTPS round trips to servers that
 * may be slow, down, or gone, and none of that belongs between a person
 * pressing Post and seeing their post. So the request queues rows and this
 * drains them.
 *
 * Long-running, like bin/upload-worker.php, rather than a timer: a post
 * reaching the network seconds after it was written is the difference between
 * federation feeling live and feeling like a mailing list. It holds no state
 * between passes, so it can be restarted at any moment and simply picks up
 * whatever is still queued.
 *
 * Each activity is signed as the member it is from - their key, not the
 * instance's - because in ActivityPub the signature is the only thing that says
 * who sent it.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/../src/config.php';

/** How long to wait before looking again when the queue came back empty. */
const IDLE_SLEEP_SECONDS = 5;

$running = true;

// Answer a stop the way systemd expects rather than dying mid-delivery: the
// current pass finishes, then the loop ends.
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, function () use (&$running): void {
            $running = false;
        });
    }
}

/** The members whose keys this pass has already decrypted, so a fan-out costs one. */
$authors = [];

while ($running) {
    $due = FediverseDelivery::due();

    if ($due === []) {
        sleep(IDLE_SLEEP_SECONDS);
        $authors = [];

        continue;
    }

    foreach ($due as $delivery) {
        if (!$running) {
            break;
        }

        $delivery_id = (int) $delivery -> deliveryId;

        // No member named means the instance signs it - a Flag, which must not
        // name whoever reported.
        if ($delivery -> actorUserId === null) {
            $instance_actor = ServerURL::absolute('/activitypub/actor');
            $key_id = $instance_actor . '#main-key';
            $private_key = ActivityPubKeys::privateKeyPem();
        } else {
            $author_id = (int) $delivery -> actorUserId;

            if (!isset($authors[$author_id])) {
                $authors[$author_id] = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $author_id);
            }

            $author = $authors[$author_id];
            $key_id = $author === null ? '' : ActivityPubActor::keyIdFor($author);
            $private_key = $author === null ? null : ActivityPubActor::privateKeyPem($author);
        }

        // Nobody left to sign as, or no key to sign with. Neither resolves by
        // waiting, so the row is dropped rather than retried until it ages out.
        if ($private_key === null) {
            FediverseDelivery::succeeded($delivery_id);

            continue;
        }

        $activity = json_decode((string) $delivery -> activity, true);

        if (!is_array($activity)) {
            FediverseDelivery::succeeded($delivery_id);

            continue;
        }

        $delivered = ActivityPubDelivery::post(
            (string) $delivery -> inboxURL,
            $activity,
            $key_id,
            $private_key
        );

        if ($delivered) {
            FediverseDelivery::succeeded($delivery_id);

            continue;
        }

        FediverseDelivery::failed($delivery_id, (int) $delivery -> attempts, 'delivery to ' . $delivery -> inboxURL . ' failed');
    }
}
