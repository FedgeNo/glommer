<?php

declare(strict_types=1);

/**
 * A group of uploaded files (video/audio, possibly mixed with images) that can't be
 * transcoded fast enough to handle inline, so the whole post is staged to disk and
 * finished by the async worker in bin/process-upload.php instead.
 */
class UploadBatch
{
    private const PENDING_DIR = __DIR__ . '/../../uploads/private/pending';

    public static function stage(int $user_id, ?int $parent_id, ?string $title, ?string $description, ?string $link_url, array $files): string
    {
        // Same lottery sweep as UploadProcessor::sweepStagedLinkImages().
        // Triggered here (the web path that stages a batch), not from the
        // worker - the failure this cleans up after is precisely "the worker
        // never ran", so a worker-side sweep couldn't help. A batch is only
        // ever created here too, so sweep frequency tracks batch-creation
        // frequency.
        if (mt_rand(1, 100) === 1) {
            self::sweepOrphanedBatches();
        }

        if (!is_dir(self::PENDING_DIR)) {
            mkdir(self::PENDING_DIR, 0755, true);
        }

        $batch_id = bin2hex(random_bytes(16));
        $batch_dir = self::PENDING_DIR . '/' . $batch_id;
        mkdir($batch_dir, 0755, true);

        $staged_files = [];

        foreach (array_values($files) as $index => $file) {
            copy($file['tmpPath'], $batch_dir . '/' . $index);
            $staged_files[] = ['originalFilename' => $file['originalFilename']];
        }

        file_put_contents($batch_dir . '/metadata.json', json_encode([
            'userId' => $user_id,
            'parentId' => $parent_id,
            'title' => $title,
            'description' => $description,
            'linkURL' => $link_url,
            'files' => $staged_files,
        ]));

        return $batch_id;
    }

    public static function process(string $batch_id): void
    {
        $batch_dir = self::PENDING_DIR . '/' . $batch_id;
        $metadata_path = $batch_dir . '/metadata.json';

        if (!is_file($metadata_path)) {
            return;
        }

        $metadata = json_decode((string) file_get_contents($metadata_path), true);
        $mysqli = Database::connection();

        $processed = [];
        $failed = false;

        foreach ($metadata['files'] as $index => $file_meta) {
            $seed = bin2hex(random_bytes(8)) . '-' . $index;
            $result = UploadProcessor::process($batch_dir . '/' . $index, $seed, $file_meta['originalFilename']);

            if ($result === null) {
                $failed = true;
                break;
            }

            $processed[] = [
                'seed' => $seed,
                'result' => $result,
                'originalExtension' => UploadProcessor::safeExtension($file_meta['originalFilename']),
            ];
        }

        if ($failed) {
            foreach ($processed as $item) {
                UploadProcessor::delete($item['seed'], $item['result']['itemType'], $item['originalExtension']);
            }

            Notification::create((int) $metadata['userId'], (int) $metadata['userId'], 'uploadFailed', null, true);
            self::cleanupDir($batch_dir);

            return;
        }

        $title_value = $metadata['title'] !== null && $metadata['title'] !== '' ? $metadata['title'] : null;
        $description_value = $metadata['description'] !== null && $metadata['description'] !== '' ? $metadata['description'] : null;
        $link_url_value = $metadata['linkURL'] !== null && $metadata['linkURL'] !== '' ? $metadata['linkURL'] : null;
        $parent_id = $metadata['parentId'];

        $post_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `linkURL`)
    VALUES (?, ?, ?, ?, ?)
');
        mysqli_stmt_bind_param($post_stmt, 'iisss', $metadata['userId'], $parent_id, $title_value, $description_value, $link_url_value);
        mysqli_stmt_execute($post_stmt);
        $post_id = (int) mysqli_insert_id($mysqli);

        if ($parent_id !== null) {
            $parent_stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
');
            mysqli_stmt_bind_param($parent_stmt, 'i', $parent_id);
            mysqli_stmt_execute($parent_stmt);
            $parent_result = mysqli_stmt_get_result($parent_stmt);
            $parent_row = mysqli_fetch_assoc($parent_result);

            if ($parent_row !== null) {
                Notification::create((int) $parent_row['userId'], (int) $metadata['userId'], 'reply', $parent_id);
            }
        } else {
            Timeline::fanOutPost((int) $metadata['userId'], $post_id);
        }

        foreach ($processed as $item) {
            $placeholder_item_type = $item['result']['itemType'];
            $placeholder_stmt = mysqli_prepare($mysqli, '
INSERT INTO `FeedItems` (`postId`, `itemType`)
    VALUES (?, ?)
');
            mysqli_stmt_bind_param($placeholder_stmt, 'is', $post_id, $placeholder_item_type);
            mysqli_stmt_execute($placeholder_stmt);
            $item_id = (int) mysqli_insert_id($mysqli);

            UploadProcessor::rename($item['seed'], $item_id, $item['result']['itemType'], $item['originalExtension']);
        }

        Notification::create((int) $metadata['userId'], (int) $metadata['userId'], 'postReady', $post_id, true);

        self::cleanupDir($batch_dir);
    }

    /**
     * Removes pending batch directories left behind by an async upload whose
     * worker never ran or died mid-transcode - process() only cleans up a
     * batch it actually finishes, so without this a stalled one leaks its
     * directory and copied source files forever. A batch older than the
     * cutoff is an orphan by definition: no real transcode runs anywhere near
     * a day, and a healthy batch has already been cleaned up by process(). A
     * batch dir's mtime is its stage time (nothing modifies the dir while the
     * worker reads from it), so it's the right clock to age against.
     */
    public static function sweepOrphanedBatches(): void
    {
        $cutoff = time() - 86400;

        foreach (glob(self::PENDING_DIR . '/*', GLOB_ONLYDIR) ?: [] as $batch_dir) {
            $modified_at = filemtime($batch_dir);

            if ($modified_at !== false && $modified_at < $cutoff) {
                self::cleanupDir($batch_dir);
            }
        }
    }

    private static function cleanupDir(string $dir): void
    {
        foreach (glob($dir . '/*') as $file) {
            unlink($file);
        }

        rmdir($dir);
    }
}
