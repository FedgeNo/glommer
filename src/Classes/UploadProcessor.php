<?php

declare(strict_types=1);

class UploadProcessor
{
    private const UPLOAD_DIR = __DIR__ . '/../../uploads';
    private const ORIGINALS_DIR = __DIR__ . '/../../uploads/private/originals';
    private const UPLOAD_URL_PREFIX = '/uploads/';

    private const VIDEO_MAX_WIDTH = 1280;
    private const VIDEO_MAX_HEIGHT = 720;
    private const VIDEO_MAX_FRAMERATE = 30;

    private const DISPLAY_EXTENSIONS = [
        'ImageItem' => 'jpg',
        'VideoItem' => 'mp4',
        'AudioItem' => 'mp3',
    ];

    /**
     * Uploads are refused while the uploads volume has less than this much
     * free space left - the database (typically on the same disk) needs
     * headroom far more than the site needs one more video. 10 GiB.
     */
    private const MIN_FREE_DISK_BYTES = 10 * 1024 * 1024 * 1024;

    /**
     * Whether the uploads volume has room for $incoming_bytes of new upload
     * while keeping MIN_FREE_DISK_BYTES free. The incoming size is doubled to
     * cover processing copies (the preserved original plus the transcoded
     * display file can briefly both exist alongside the tmp upload).
     */
    public static function hasFreeDiskSpace(int $incoming_bytes = 0): bool
    {
        $free = disk_free_space(self::UPLOAD_DIR);

        if ($free === false) {
            // Can't measure - don't turn a stat failure into a site-wide
            // upload outage.
            return true;
        }

        return ($free - $incoming_bytes * 2) >= self::MIN_FREE_DISK_BYTES;
    }

    public static function srcPath(int|string $item_id, string $item_type): string
    {
        return self::UPLOAD_URL_PREFIX . $item_id . '.' . self::DISPLAY_EXTENSIONS[$item_type];
    }

    public static function thumbnailPath(int|string $item_id, string $item_type): ?string
    {
        return $item_type === 'AudioItem' ? null : self::UPLOAD_URL_PREFIX . $item_id . '-thumb.jpg';
    }

    /**
     * Determines what kind of media a file is without fully processing it, so callers can
     * decide sync vs. async handling before committing to a (possibly slow) transcode.
     */
    public static function classify(string $tmp_path): ?string
    {
        $image = ImageProcessor::load($tmp_path);

        if ($image !== false) {
            imagedestroy($image);

            return 'image';
        }

        return self::probeMedia($tmp_path);
    }

    public static function safeExtension(string $original_filename): string
    {
        $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);

        return strlen($extension) <= 5 ? $extension : '';
    }

    /**
     * Transcodes and strips metadata from $tmp_path, naming the output files after $id
     * (either a real itemId for synchronous processing, or a temporary seed for files that
     * will later be renamed once their real itemId is known - see rename()). The original
     * upload is preserved under uploads/private/originals, which is not web-served.
     */
    public static function process(string $tmp_path, int|string $id, string $original_filename = ''): ?array
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        if (!is_dir(self::ORIGINALS_DIR)) {
            mkdir(self::ORIGINALS_DIR, 0755, true);
        }

        $image = ImageProcessor::load($tmp_path);

        if ($image !== false) {
            return self::processImage($image, $id, $tmp_path, $original_filename);
        }

        $media_type = self::probeMedia($tmp_path);

        if ($media_type === 'video') {
            return self::processVideo($tmp_path, $id, $original_filename);
        }

        if ($media_type === 'audio') {
            return self::processAudio($tmp_path, $id, $original_filename);
        }

        return null;
    }

    /**
     * Whether the display file for a staged/finished upload still exists on disk - used
     * before finalizing a staged id, in case it was already discarded or never finished.
     */
    public static function exists(int|string $id, string $item_type): bool
    {
        return is_file(self::outputPaths($id, $item_type, null)['display']);
    }

    /**
     * Renames every file belonging to $old_id (display/thumbnail/original) to $new_id, once
     * a real itemId is known.
     */
    public static function rename(int|string $old_id, int $new_id, string $item_type, ?string $original_extension): void
    {
        $old_paths = self::outputPaths($old_id, $item_type, $original_extension);
        $new_paths = self::outputPaths($new_id, $item_type, $original_extension);

        foreach (['display', 'thumbnail', 'original'] as $key) {
            if ($old_paths[$key] !== null && is_file($old_paths[$key])) {
                rename($old_paths[$key], $new_paths[$key]);
            }
        }
    }

    /**
     * Deletes every file belonging to $id (display/thumbnail/original). Used to clean up
     * after a failed batch, since no FeedItem row ever gets created for it to cascade from.
     */
    public static function delete(int|string $id, string $item_type, ?string $original_extension): void
    {
        $paths = self::outputPaths($id, $item_type, $original_extension);

        foreach ($paths as $path) {
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Deletes staged link-preview images (lp-* seeds) that were never finalized onto a
     * post or explicitly discarded - a user who fetches a preview and then just closes
     * the tab leaves its files behind, so anything older than a day is an orphan by
     * definition (a staged seed's whole life is one composer session).
     */
    public static function sweepStagedLinkImages(): void
    {
        $cutoff = time() - 86400;

        foreach (glob(self::UPLOAD_DIR . '/lp-*') ?: [] as $path) {
            $modified_at = filemtime($path);

            if ($modified_at !== false && $modified_at < $cutoff) {
                unlink($path);
            }
        }
    }

    /**
     * Deletes every file belonging to an existing FeedItem, discovering the preserved
     * original by glob since its extension isn't stored anywhere. Used when a post is
     * deleted, so its media doesn't linger on disk after the rows cascade away.
     */
    public static function deleteForItem(int $item_id, string $item_type): void
    {
        $paths = self::outputPaths($item_id, $item_type, null);

        foreach (['display', 'thumbnail'] as $key) {
            if ($paths[$key] !== null && is_file($paths[$key])) {
                unlink($paths[$key]);
            }
        }

        foreach (glob(self::ORIGINALS_DIR . '/' . $item_id . '-original.*') ?: [] as $original_path) {
            unlink($original_path);
        }
    }

    private static function outputPaths(int|string $id, string $item_type, ?string $original_extension): array
    {
        $original = $original_extension !== null && $original_extension !== ''
            ? self::ORIGINALS_DIR . '/' . $id . '-original.' . $original_extension
            : null;

        return match ($item_type) {
            'ImageItem' => [
                'display' => self::UPLOAD_DIR . '/' . $id . '.jpg',
                'thumbnail' => self::UPLOAD_DIR . '/' . $id . '-thumb.jpg',
                'original' => $original,
            ],
            'VideoItem' => [
                'display' => self::UPLOAD_DIR . '/' . $id . '.mp4',
                'thumbnail' => self::UPLOAD_DIR . '/' . $id . '-thumb.jpg',
                'original' => $original,
            ],
            'AudioItem' => [
                'display' => self::UPLOAD_DIR . '/' . $id . '.mp3',
                'thumbnail' => null,
                'original' => $original,
            ],
        };
    }

    private static function processImage(\GdImage $image, int|string $id, string $tmp_path, string $original_filename): ?array
    {
        $paths = self::outputPaths($id, 'ImageItem', self::safeExtension($original_filename));

        $display_ok = ImageProcessor::resizeAndSave($image, $paths['display'], ImageProcessor::DISPLAY_MAX_DIMENSION);
        $thumbnail_ok = ImageProcessor::resizeAndSave($image, $paths['thumbnail'], ImageProcessor::THUMBNAIL_MAX_DIMENSION);

        imagedestroy($image);

        if (!$display_ok || !$thumbnail_ok) {
            // A partial write (e.g. display saved but thumbnail failed) would
            // leave an orphaned file no sweeper removes - clean up whatever
            // landed, same as processVideo/processAudio do on their failure path.
            foreach (['display', 'thumbnail'] as $key) {
                if ($paths[$key] !== null && is_file($paths[$key])) {
                    unlink($paths[$key]);
                }
            }

            return null;
        }

        if ($paths['original'] !== null) {
            copy($tmp_path, $paths['original']);
        }

        return ['itemType' => 'ImageItem'];
    }

    private static function probeMedia(string $path): ?string
    {
        // Include each stream's attached_pic disposition. A "video" stream that
        // is an attached picture is embedded cover art (common in music MP3s),
        // not real video - such a file is audio. Only a non-attached-picture
        // video stream makes it a video, so a cover-art MP3 isn't misrouted to
        // the video transcoder, which fails on it.
        $output = (string) shell_exec(
            'ffprobe -v error -show_entries stream=codec_type:stream_disposition=attached_pic -of csv=p=0 ' . escapeshellarg($path) . ' 2>&1'
        );

        $has_real_video = false;
        $has_audio = false;

        foreach (explode(chr(10), $output) as $line) {
            $parts = explode(',', trim($line));

            if (count($parts) < 2) {
                continue;
            }

            [$codec_type, $attached_pic] = $parts;

            if ($codec_type === 'video' && $attached_pic !== '1') {
                $has_real_video = true;
            }

            if ($codec_type === 'audio') {
                $has_audio = true;
            }
        }

        if ($has_real_video) {
            return 'video';
        }

        if ($has_audio) {
            return 'audio';
        }

        return null;
    }

    private static function probeDuration(string $path): float
    {
        $output = (string) shell_exec(
            'ffprobe -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($path) . ' 2>&1'
        );

        // A failed probe or non-numeric output casts to 0.0, which makes the
        // caller seek to the very first frame - a safe fallback, never past EOF.
        return max(0.0, (float) trim($output));
    }

    private static function processVideo(string $tmp_path, int|string $id, string $original_filename): ?array
    {
        $extension = self::safeExtension($original_filename);
        $paths = self::outputPaths($id, 'VideoItem', $extension);

        if ($paths['original'] !== null) {
            copy($tmp_path, $paths['original']);
        }

        $raw_frame_path = self::UPLOAD_DIR . '/' . $id . '-raw-frame.jpg';

        // Cap resolution/framerate - we're not streaming ultra HD, and bandwidth matters.
        // scale filter only ever downscales (never upscales smaller sources).
        $scale_filter = sprintf(
            'scale=\'min(%d,iw)\':\'min(%d,ih)\':force_original_aspect_ratio=decrease',
            self::VIDEO_MAX_WIDTH,
            self::VIDEO_MAX_HEIGHT
        );

        exec(sprintf(
            'ffmpeg -y -i %s -vf %s -r %d -map_metadata -1 -map_chapters -1 -fflags +bitexact -flags:v +bitexact -flags:a +bitexact -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart %s 2>&1',
            escapeshellarg($tmp_path),
            escapeshellarg($scale_filter),
            self::VIDEO_MAX_FRAMERATE,
            escapeshellarg($paths['display'])
        ), $output_lines, $exit_code);

        if ($exit_code !== 0 || !is_file($paths['display'])) {
            if ($paths['original'] !== null) {
                unlink($paths['original']);
            }

            return null;
        }

        // Poster frame: seek in a little to skip a possibly-black opening frame,
        // but never past the clip itself - a sub-two-second video has no 1s mark,
        // so a fixed -ss 00:00:01 seeks past EOF and yields no frame, which used
        // to get the whole (valid) short video rejected. Clamp the seek to the
        // midpoint (min(1s, duration/2)) so short clips still produce a frame.
        $poster_seek = min(1.0, self::probeDuration($paths['display']) / 2.0);

        exec(sprintf(
            'ffmpeg -y -i %s -ss %s -vframes 1 %s 2>&1',
            escapeshellarg($paths['display']),
            sprintf('%.3f', $poster_seek),
            escapeshellarg($raw_frame_path)
        ), $frame_output_lines, $frame_exit_code);

        $thumbnail_ok = false;

        if ($frame_exit_code === 0 && is_file($raw_frame_path)) {
            $frame_image = @imagecreatefromjpeg($raw_frame_path);

            if ($frame_image !== false) {
                $thumbnail_ok = ImageProcessor::resizeAndSave($frame_image, $paths['thumbnail'], ImageProcessor::THUMBNAIL_MAX_DIMENSION);
            }

            unlink($raw_frame_path);
        }

        if (!$thumbnail_ok) {
            unlink($paths['display']);

            if ($paths['original'] !== null) {
                unlink($paths['original']);
            }

            return null;
        }

        return ['itemType' => 'VideoItem'];
    }

    private static function processAudio(string $tmp_path, int|string $id, string $original_filename): ?array
    {
        $extension = self::safeExtension($original_filename);
        $paths = self::outputPaths($id, 'AudioItem', $extension);

        if ($paths['original'] !== null) {
            copy($tmp_path, $paths['original']);
        }

        // -vn drops any embedded cover art (an attached-picture video stream);
        // we only want the audio, and leaving it in can make the mp3 muxing fail.
        exec(sprintf(
            'ffmpeg -y -i %s -vn -map_metadata -1 -id3v2_version 0 -c:a libmp3lame -b:a 320k %s 2>&1',
            escapeshellarg($tmp_path),
            escapeshellarg($paths['display'])
        ), $output_lines, $exit_code);

        if ($exit_code !== 0 || !is_file($paths['display'])) {
            if ($paths['original'] !== null) {
                unlink($paths['original']);
            }

            return null;
        }

        return ['itemType' => 'AudioItem'];
    }
}
