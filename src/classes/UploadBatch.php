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
    private static string $stagingDirectory = __DIR__ . '/../../uploads/private/staging';
    private static string $pendingDirectory = __DIR__ . '/../../uploads/private/pending';
    private static string $processingDirectory = __DIR__ . '/../../uploads/private/processing';

    // How many times a single file may kill its worker process before it's
    // abandoned and dropped from the post (see the class docblock).
    private const MAX_FILE_DEATHS = 3;

    public static function stage(int $user_id, ?int $parent_id, ?string $title, ?string $description, ?string $description_delta, ?string $link_url, array $files, ?float $latitude = null, ?float $longitude = null, int $sensitive = 0, ?string $content_warning = null): string
    {
        // Same lottery sweep as UploadProcessor::sweepStagedLinkImages().
        // Triggered here (the web path that stages a batch), not from the
        // worker - a batch is only ever created here, so sweep frequency tracks
        // batch-creation frequency.
        if (mt_rand(1, 100) === 1) {
            self::sweepOrphanedBatches();
        }

        self::ensureDir(self::$stagingDirectory);
        self::ensureDir(self::$pendingDirectory);

        // Build the whole batch under staging/ (copying a large upload takes real
        // time), then publish it into pending/ with one atomic rename. The worker
        // only ever scans pending/, so it can never claim a batch mid-copy and
        // find it vanish out from under the still-writing web request.
        $batch_id = bin2hex(random_bytes(16));
        $staging_dir = self::$stagingDirectory . '/' . $batch_id;
        mkdir($staging_dir, 0755, true);

        $staged_files = [];

        foreach (array_values($files) as $index => $file) {
            copy($file['tmpPath'], $staging_dir . '/' . $index);
            $staged_files[] = [
                'originalFilename' => $file['originalFilename'],
                'altText' => $file['altText'] ?? null,
            ];
        }

        file_put_contents($staging_dir . '/metadata.json', json_encode([
            'userId' => $user_id,
            'parentId' => $parent_id,
            'title' => $title,
            'description' => $description,
            'descriptionDelta' => $description_delta,
            'linkURL' => $link_url,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'sensitive' => $sensitive,
            'contentWarning' => $content_warning,
            'files' => $staged_files,
        ]));

        rename($staging_dir, self::$pendingDirectory . '/' . $batch_id);

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
        if (!is_dir(self::$pendingDirectory)) {
            return null;
        }

        self::ensureDir(self::$processingDirectory);

        $dirs = glob(self::$pendingDirectory . '/*', GLOB_ONLYDIR) ?: [];

        // Oldest first, so the queue drains FIFO.
        usort($dirs, fn ($a, $b) => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));

        foreach ($dirs as $dir) {
            $progress = json_decode((string) @file_get_contents($dir . '/progress.json'), true);
            if (is_array($progress) && ($progress['retryAt'] ?? 0) > time()) {
                continue;
            }
            $batch_id = basename($dir);
            $target = self::$processingDirectory . '/' . $batch_id;

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
        self::ensureDir(self::$pendingDirectory);
        $target = self::$pendingDirectory . '/' . $batch_id;

        if (@rename(self::$processingDirectory . '/' . $batch_id, $target)) {
            @touch($target);
        }
    }

    /**
     * Releases a claimed batch back to pending WITHOUT counting a file death -
     * used when the worker service is shutting down and terminates an in-flight
     * child itself, so a graceful stop (or the daily restart) mid-transcode
     * doesn't penalise the file that happened to be in flight. Finalization is
     * also resumable: its durable receipt identifies a committed publication.
     */
    public static function releaseClaim(string $batch_id): void
    {
        $batch_dir = self::$processingDirectory . '/' . $batch_id;
        $metadata_path = $batch_dir . '/metadata.json';

        if (is_file($metadata_path)) {
            $progress = self::loadProgress($batch_dir, json_decode((string) file_get_contents($metadata_path), true));

            $progress['started'] = null;
            self::saveProgress($batch_dir, $progress);
        }

        self::requeue($batch_id);
    }

    /** A temporary database outage must not consume a good upload's crash budget. */
    public static function deferClaim(string $batch_id): void
    {
        $directory = self::$processingDirectory . '/' . $batch_id;
        $metadata = json_decode((string) @file_get_contents($directory . '/metadata.json'), true);
        if (is_array($metadata)) {
            $progress = self::loadProgress($directory, $metadata);
            $progress['retryAt'] = time() + 60;
            self::saveProgress($directory, $progress);
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
        if (preg_match('/\A[a-f0-9]{32}\z/', $batch_id) !== 1) {
            throw new \InvalidArgumentException('Invalid upload batch identity.');
        }
        $batch_dir = self::$processingDirectory . '/' . $batch_id;
        if (!is_dir($batch_dir)) {
            return;
        }
        $lock = fopen($batch_dir . '/worker.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot lock the upload batch.');
        }
        try {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                self::processClaimed($batch_id);
            }
        } finally {
            fclose($lock);
        }
    }

    private static function processClaimed(string $batch_id): void
    {
        $batch_dir = self::$processingDirectory . '/' . $batch_id;
        $metadata_path = $batch_dir . '/metadata.json';

        $receipt = self::receipt($batch_id);
        if ($receipt !== null && $receipt -> finished) {
            $progress = json_decode((string) @file_get_contents($batch_dir . '/progress.json'), true);
            self::cleanupBatch($batch_dir, is_array($progress) ? $progress : ['files' => []]);
            return;
        }

        if (!is_file($metadata_path)) {
            return;
        }

        $metadata = json_decode((string) file_get_contents($metadata_path), true);
        $progress = self::loadProgress($batch_dir, $metadata);

        // Old workers moved seeds before committing without recording item IDs
        // and ignored rename failures. Even surviving seeds cannot prove that
        // no post committed. Preserve these batches for explicit reconciliation.
        if (!empty($progress['finalizing']) && empty($progress['publicationVersion'])) {
            error_log('Legacy upload finalization requires reconciliation: ' . $batch_id);
            return;
        }
        $progress['publicationVersion'] = 1;
        self::saveProgress($batch_dir, $progress);
        DB::run('INSERT IGNORE INTO `UploadPublications` (`batchId`) VALUES (?)', 's', $batch_id);
        self::cleanupUncommittedItems($progress);
        $progress['publishingItems'] = [];
        self::saveProgress($batch_dir, $progress);

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

        // Every file is now terminal. The receipt and all database publication
        // effects commit together; retained seed files survive any rollback.
        $progress['finalizing'] = true;
        self::saveProgress($batch_dir, $progress);

        self::finalize($batch_dir, $metadata, $progress);
        self::cleanupBatch($batch_dir, $progress);
    }

    private static function receipt(string $batch_id): ?object
    {
        return DB::row('SELECT `postId`, `finished` FROM `UploadPublications` WHERE `batchId` = ?', 'stdClass', 's', $batch_id);
    }

    /** Only remove destinations whose row demonstrably rolled back. */
    private static function cleanupUncommittedItems(array $progress): void
    {
        foreach ($progress['publishingItems'] ?? [] as $item) {
            if (DB::row('SELECT `itemId` FROM `FeedItems` WHERE `itemId` = ?', 'stdClass', 'i', $item['itemId']) !== null) {
                throw new \RuntimeException('Upload output belongs to a committed item; refusing cleanup.');
            }
            UploadProcessor::deleteForItem((int) $item['itemId'], $item['type']);
        }
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

        $title_value = $metadata['title'] !== null && $metadata['title'] !== '' ? $metadata['title'] : null;
        $description_value = $metadata['description'] !== null && $metadata['description'] !== '' ? $metadata['description'] : null;
        $link_url_value = $metadata['linkURL'] !== null && $metadata['linkURL'] !== '' ? $metadata['linkURL'] : null;
        $latitude_value = $metadata['latitude'] ?? null;
        $longitude_value = $metadata['longitude'] ?? null;
        // A batch staged before the composer's sensitive toggle carries no key;
        // absent means unclassified, same as the column default.
        $sensitive_value = (int) ($metadata['sensitive'] ?? 0);
        // Absent on a batch staged before the warning existed, the same as the
        // key above and for the same reason.
        $content_warning_value = $metadata['contentWarning'] ?? null;
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

        $detected_language = ($progress['finalizationDeaths'] ?? 0) >= self::MAX_FILE_DEATHS
            ? null : LanguageDetector::of((string) $description_value);

        DB::transaction(static function () use ($batch_dir, $metadata, $progress, $survivors, $any_failed,
            $user_id, $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value,
            $sensitive_value, $content_warning_value, $detected_language, $latitude_value, $longitude_value): void {
            $author = DB::row('SELECT * FROM `Users` WHERE `userId` = ? FOR UPDATE', 'User', 'i', $user_id);
            $batch_id = basename($batch_dir);
            $receipt = DB::row('SELECT `finished` FROM `UploadPublications` WHERE `batchId` = ? FOR UPDATE', 'stdClass', 's', $batch_id);
            if ($receipt === null) {
                throw new \RuntimeException('Upload publication receipt missing.');
            }
            if ($receipt -> finished) {
                return;
            }
            $parent = $parent_id === null ? null : DB::row('SELECT `userId` FROM `Posts` WHERE `postId` = ? FOR UPDATE', 'stdClass', 'i', $parent_id);
            if ($survivors === [] || ($progress['finalizationDeaths'] ?? 0) >= self::MAX_FILE_DEATHS
                || $author === null || $author -> banned || ($parent_id !== null && $parent === null)) {
                if ($author !== null) {
                    Notification::create($user_id, $user_id, 'uploadFailed', null, true);
                }
                DB::run('UPDATE `UploadPublications` SET `finished` = 1 WHERE `batchId` = ?', 's', $batch_id);
                return;
            }

            if ($parent_id !== null) {
                Post::adjustCounts($parent_id, replies: 1);
            }

            DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `descriptionDelta`, `linkURL`, `sensitive`, `contentWarning`, `detectedLanguage`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
', 'iissssiss', $metadata['userId'], $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value, $sensitive_value, $content_warning_value, $detected_language);
            $post_id = (int) mysqli_insert_id(DB::connection());

            if ($latitude_value !== null && $longitude_value !== null) {
                PostLocation::save($post_id, $latitude_value, $longitude_value);
            }

            $mentioned_user_ids = [];

            if ($description_delta_value !== null) {
                $description_ops = Delta::decode($description_delta_value);
                Hashtag::indexPost($post_id, $description_ops);
                $mentioned_user_ids = Mention::indexPost($post_id, $description_ops);
            }

            $parent_user_id = $parent !== null ? (int) $parent -> userId : null;

            // Replies included, the same as the request path does it.
            Timeline::fanOutPost($user_id, $post_id);

            // array_filter kept the original indexes, which is what ties each
            // survivor back to its own staged metadata - and so its alt text.
            foreach ($survivors as $index => $file) {
                $item_type = $file['itemType'];

                // A batch staged before alt text existed carries no key; and only
                // an image row has anything for one to say.
                $alt_text = $item_type === 'ImageItem'
                    ? ($metadata['files'][$index]['altText'] ?? null)
                    : null;

                DB::run('
INSERT INTO `FeedItems` (`postId`, `type`, `altText`)
    VALUES (?, ?, ?)
', 'iss', $post_id, $item_type, $alt_text);
                $item_id = (int) mysqli_insert_id(DB::connection());

                // Persist the destination before touching it. A dead transaction
                // consumes AUTO_INCREMENT IDs, so recovery must know what to remove.
                $progress['publishingItems'][] = ['itemId' => $item_id, 'type' => $item_type];
                self::saveProgress($batch_dir, $progress);
                UploadProcessor::publishStaged($file['seed'], $item_id, $file['itemType'], $file['ext']);
            }

            // A reply notifies the parent's author; postReady always tells the
            // uploader their post is live; uploadPartlyFailed warns them if some of
            // their files were dropped along the way. Rows and push queue entries
            // commit with the post; the live WebSocket signal waits for commit.
            if ($parent_user_id !== null) {
                Notification::create($parent_user_id, $user_id, 'reply', $parent_id);
            }

            Mention::notify($mentioned_user_ids, $user_id, $post_id);

            Notification::create($user_id, $user_id, 'postReady', $post_id, true);

            if ($any_failed) {
                Notification::create($user_id, $user_id, 'uploadPartlyFailed', $post_id, true);
            }

            // The same announcement api/create-post.php queues for a synchronous
            // post - without it a video/audio post exists here but the author's
            // Fediverse followers are never told about it. Re-fetched so the
            // activity is built from the new row and its FeedItems, and queued in
            // the same transaction as their publication.
            $published_post = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

            if ($author !== null && $published_post !== null) {
                $post = Post::fromRowWithItems($published_post);
                $post -> author = $author;
                FediversePublisher::published($post, $author);
            }

            DB::run('UPDATE `UploadPublications` SET `postId` = ?, `finished` = 1 WHERE `batchId` = ?', 'is', $post_id, $batch_id);
        });
    }

    /**
     * Recovers a batch whose worker process died (crash / OOM-kill / nonzero
     * exit) while it held the claim. Called by the upload-worker service both
     * when it reaps an abnormally-exited child and, at startup, for every batch
     * left in processing/ by a prior run.
     */
    public static function recoverDied(string $batch_id): void
    {
        $batch_dir = self::$processingDirectory . '/' . $batch_id;

        if (!is_dir($batch_dir)) {
            return;
        }

        $metadata_path = $batch_dir . '/metadata.json';

        if (!is_file($metadata_path)) {
            $receipt = self::receipt($batch_id);
            if ($receipt !== null && $receipt -> finished) {
                $progress = json_decode((string) @file_get_contents($batch_dir . '/progress.json'), true);
                self::cleanupBatch($batch_dir, is_array($progress) ? $progress : ['files' => []]);
            } else {
                self::cleanupDir($batch_dir);
            }

            return;
        }

        $progress = self::loadProgress($batch_dir, json_decode((string) file_get_contents($metadata_path), true));

        // Publication retries consult the receipt before doing any work. A
        // repeated fatal failure is reported once after reconciling the outcome.
        if (!empty($progress['finalizing'])) {
            $progress['finalizationDeaths'] = ($progress['finalizationDeaths'] ?? 0) + 1;
            self::saveProgress($batch_dir, $progress);
            self::requeue($batch_id);
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
        // A crash after directory removal but before receipt removal leaves only
        // a small completed receipt. Never remove one while its batch exists.
        foreach (DB::rows('SELECT `batchId` FROM `UploadPublications` WHERE `finished` = 1 AND `createdAt` < NOW() - INTERVAL 1 DAY LIMIT 100', 'stdClass') as $row) {
            if (!is_dir(self::$pendingDirectory . '/' . $row -> batchId)
                && !is_dir(self::$processingDirectory . '/' . $row -> batchId)) {
                DB::run('DELETE FROM `UploadPublications` WHERE `batchId` = ? AND `finished` = 1', 's', $row -> batchId);
            }
        }
        if (!is_dir(self::$processingDirectory)) {
            return;
        }

        foreach (glob(self::$processingDirectory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
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
        $json = json_encode($progress, JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json) !== strlen($json) || !rename($tmp, $batch_dir . '/progress.json')) {
            throw new \RuntimeException('Could not persist upload progress.');
        }
    }

    /**
     * Removes a finished batch's retained seeds and any dropped file's partial
     * output, then its input directory, and finally its completed receipt.
     */
    private static function cleanupBatch(string $batch_dir, array $progress): void
    {
        foreach ($progress['files'] as $file) {
            if (isset($file['seed'])) {
                UploadProcessor::purgeStaged($file['seed']);
            }
        }

        self::cleanupDir($batch_dir);
        DB::run('DELETE FROM `UploadPublications` WHERE `batchId` = ? AND `finished` = 1', 's', basename($batch_dir));
    }

    /**
     * How many batches are sitting in each stage of the queue right now - a
     * cheap directory count (the queue itself is directory-based, see the
     * class docblock), not a DB query. Lets the Admin Settings page tell
     * "dead" from "alive but backlogged" instead of SSHing in to check.
     *
     * @return array{staging: int, pending: int, processing: int}
     */
    public static function queueDepth(): array
    {
        return [
            'staging' => count(glob(self::$stagingDirectory . '/*', GLOB_ONLYDIR) ?: []),
            'pending' => count(glob(self::$pendingDirectory . '/*', GLOB_ONLYDIR) ?: []),
            'processing' => count(glob(self::$processingDirectory . '/*', GLOB_ONLYDIR) ?: []),
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
     * is-active` from PHP-FPM returns nothing but "Access denied" on stderr,
     * which - discarded via 2>/dev/null - reads back as an empty string and
     * would fold into a false "dead"). bin/install.php's
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

    /**
     * Removes batch directories left behind by an upload that never completed -
     * scans staging/ (a stage that died mid-copy), pending/ (never claimed, e.g.
     * the service was down), and processing/ (left mid-assembly). process()/
     * recovery clean up a batch they finish, and claim/requeue touch a batch's
     * mtime so a live one is never aged out; anything older than the cutoff is a
     * genuine orphan (no real transcode runs anywhere near a day).
     */
    public static function sweepOrphanedBatches(): void
    {
        $cutoff = time() - 86400;

        // Claimed and pending work belongs to worker recovery. Age alone says
        // nothing about whether a publication committed; never sweep its source.
        foreach ([self::$stagingDirectory] as $base) {
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

                if (is_array($metadata) && isset($metadata['userId']) && User::load((int) $metadata['userId']) !== null) {
                    Notification::create((int) $metadata['userId'], (int) $metadata['userId'], 'uploadFailed', null, true);
                }

                self::cleanupDir($batch_dir);
            }
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            // The installer runs the web and upload worker as the same account,
            // so no cross-account or world-write permission is needed.
            mkdir($dir, 0755, true);
            @chmod($dir, 0755);
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
