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

    // A source frame larger than this (~4K in any orientation) is rejected
    // before decode. ffmpeg buffers frames at the SOURCE resolution before the
    // scale filter runs, so a tiny file declaring an enormous resolution is a
    // decode bomb; we downscale to 720p regardless, so nothing this big is
    // worth decoding. Keeps the worst case under the address-space ulimit
    // instead of relying on it to interrupt a multi-gigabyte allocation.
    private const VIDEO_MAX_SOURCE_PIXELS = 4096 * 2304;

    private const DISPLAY_EXTENSIONS = [
        'ImageItem' => 'jpg',
        'VideoItem' => 'mp4',
        'AudioItem' => 'mp3',
    ];

    /** MIME types safe to render inline from a preserved, untrusted original. */
    private const INLINE_ORIGINAL_TYPES = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'image/avif' => 'image',
        'video/mp4' => 'video',
        'video/webm' => 'video',
        'video/ogg' => 'video',
        'video/quicktime' => 'video',
        'audio/mpeg' => 'audio',
        'audio/mp4' => 'audio',
        'audio/ogg' => 'audio',
        'audio/aac' => 'audio',
        'audio/flac' => 'audio',
        'audio/wav' => 'audio',
        'audio/x-wav' => 'audio',
    ];

    // --- Hardening for every ffmpeg/ffprobe run against an untrusted upload ---
    // A PHP memory_limit does NOT bound these child processes (see
    // bin/process-upload.php), and ffmpeg's real attack surface is its
    // demuxers/protocols - not the output text - so each invocation is locked
    // down: only the local `file` protocol (no http/tcp/rtp/... => no SSRF), a
    // container-format allowlist (no concat/hls/image2 local-file-read
    // demuxers), bounded format probing, and wall-clock / CPU-time /
    // address-space / thread caps applied at the OS level. Requires timeout(1)
    // (coreutils) and bash on the host (checked by EnvironmentChecker).
    private const FF_PROTOCOL_WHITELIST = 'file';
    private const FF_PROBE_SIZE = 15000000;
    private const FF_ANALYZE_DURATION = 15000000;
    private const FF_PROBE_TIMEOUT = 30;
    private const FF_WALL_TIMEOUT = 300;
    private const FF_CPU_TIMELIMIT = 300;
    private const FF_MAX_ADDRESS_SPACE_KB = 4194304;
    private const FF_THREADS = 2;

    // ffprobe's format_name is a comma-joined list of the demuxers that claim
    // the file; a file is accepted if ANY token is a known media container
    // here, and rejected otherwise - crucially the local-file-reading / network
    // demuxers (concat, hls/applehttp, image2, sdp, rtp/rtsp, dash, ...) that
    // are the real ffmpeg upload-attack vector are absent, so they fail closed.
    private const SAFE_CONTAINER_FORMATS = [
        // audio
        'mp3', 'wav', 'w64', 'flac', 'ogg', 'aac', 'aiff', 'aif', 'ac3',
        'amr', 'caf', 'au', 'mp2', 'wv', 'ape', 'tta', 'mpc',
        // video / mixed containers
        'mov', 'mp4', 'm4a', 'm4v', '3gp', '3g2', 'mj2', 'matroska', 'webm',
        'avi', 'flv', 'asf', 'mpeg', 'mpegts', 'mpegvideo',
    ];

    /**
     * The most headroom uploads will ever insist on: past this, a bigger disk
     * is just a bigger disk and there is no more to protect. 10 GiB.
     */
    private const MAX_RESERVED_BYTES = 10 * 1024 * 1024 * 1024;

    /**
     * And the most of a volume it will ever insist on, whatever that comes to.
     * A flat floor asks the impossible of a small disk - ten gigabytes held
     * back on a fifteen gigabyte volume refuses every upload from a third full
     * onward, with the disk in no trouble at all.
     */
    private const RESERVED_FRACTION = 0.1;

    /**
     * The free space uploads must leave behind on a volume of this size - the
     * database usually shares the disk and needs headroom far more than the
     * site needs one more video.
     */
    public static function reservedFor(float $total_bytes): int
    {
        return (int) min(self::MAX_RESERVED_BYTES, $total_bytes * self::RESERVED_FRACTION);
    }

    /**
     * Whether the uploads volume has room for $incoming_bytes of new upload
     * while leaving its reserve free. The incoming size is doubled to cover
     * processing copies (the preserved original plus the transcoded display
     * file can briefly both exist alongside the tmp upload).
     */
    public static function hasFreeDiskSpace(int $incoming_bytes = 0): bool
    {
        $free = disk_free_space(self::UPLOAD_DIR);
        $total = disk_total_space(self::UPLOAD_DIR);

        if ($free === false || $total === false) {
            // Can't measure - don't turn a stat failure into a site-wide
            // upload outage.
            return true;
        }

        return ($free - $incoming_bytes * 2) >= self::reservedFor($total);
    }

    /**
     * The shard directory an id's files live under - two hex characters (256
     * buckets), so no upload directory's entry count grows with the site. A
     * numeric itemId hashes by modulus; a staging seed (lp-<hex>, <hex>-<n>)
     * uses its own first two hex characters, which are already uniformly
     * random. Deterministic from the id alone, so every path builder and
     * cleanup glob lands on the same bucket.
     */
    public static function shard(int|string $id): string
    {
        if (is_int($id)) {
            return sprintf('%02x', $id % 256);
        }

        return substr(preg_replace('/[^0-9a-f]/', '', strtolower($id)) . '00', 0, 2);
    }

    public static function srcPath(int|string $item_id, string $item_type): string
    {
        // A type with no extension here has no address to give. Said out loud
        // rather than warned about and appended as nothing, which builds a URL
        // ending in a full stop and leaves whoever added the media type
        // looking at a broken image instead of a message naming their mistake.
        if (!isset(self::DISPLAY_EXTENSIONS[$item_type])) {
            throw new \InvalidArgumentException($item_type . ' has no display extension - add one to UploadProcessor::DISPLAY_EXTENSIONS.');
        }

        return self::UPLOAD_URL_PREFIX . self::shard($item_id) . '/' . $item_id . '.' . self::DISPLAY_EXTENSIONS[$item_type];
    }

    public static function thumbnailPath(int|string $item_id, string $item_type): ?string
    {
        return $item_type === 'AudioItem' ? null : self::UPLOAD_URL_PREFIX . self::shard($item_id) . '/' . $item_id . '-thumb.jpg';
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
    public static function process(string $tmp_path, int|string $id, ?string $original_filename = null): ?array
    {
        self::ensureDir(self::UPLOAD_DIR . '/' . self::shard($id));
        self::ensureDir(self::ORIGINALS_DIR . '/' . self::shard($id));

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

        // The new id shards into its own bucket, which may not exist yet.
        self::ensureDir(self::UPLOAD_DIR . '/' . self::shard($new_id));
        self::ensureDir(self::ORIGINALS_DIR . '/' . self::shard($new_id));

        foreach (['display', 'thumbnail'] as $key) {
            if ($old_paths[$key] !== null && is_file($old_paths[$key])) {
                rename($old_paths[$key], $new_paths[$key]);
            }
        }

        // Accept old staged originals whose filename still carried the upload
        // extension, but every newly finalized name uses the inert .bin suffix.
        $old_original = self::originalMatches($old_id)[0] ?? null;

        if ($old_original !== null && $new_paths['original'] !== null) {
            rename($old_original, $new_paths['original']);
        }
    }

    /** Creates a shard owned and writable by the shared web/worker account. */
    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            @chmod($dir, 0755);
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

        // Compatibility cleanup for originals created before filenames were
        // changed to the fixed .bin suffix.
        foreach (self::originalMatches($id) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Deletes every on-disk file named after a staging seed (display, thumbnail,
     * and preserved original, whatever the extension). Unlike delete(), it needs
     * no itemType/extension - it globs - so it can clean up the partial output a
     * worker killed mid-transcode leaves behind (where the final type isn't
     * known). A seed already renamed onto a real itemId matches nothing here.
     */
    public static function purgeStaged(string $seed): void
    {
        $shard = self::shard($seed);

        $globs = [
            self::UPLOAD_DIR . '/' . $shard . '/' . $seed . '.*',
            self::UPLOAD_DIR . '/' . $shard . '/' . $seed . '-thumb.*',
            // The video poster-frame temp (processVideo), in case a crash between
            // writing and unlinking it leaves it behind.
            self::UPLOAD_DIR . '/' . $shard . '/' . $seed . '-raw-frame.*',
            self::ORIGINALS_DIR . '/' . $shard . '/' . $seed . '-original.*',
        ];

        foreach ($globs as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
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

        foreach (glob(self::UPLOAD_DIR . '/[0-9a-f][0-9a-f]/lp-*') ?: [] as $path) {
            $modified_at = filemtime($path);

            if ($modified_at !== false && $modified_at < $cutoff) {
                unlink($path);
            }
        }
    }

    /**
     * Removes the web-served copies (display + thumbnail) of an existing FeedItem
     * when its post is deleted, ending public access to the media. The private
     * original is deliberately kept as the forensic record (see below).
     */
    public static function deleteForItem(int $item_id, string $item_type): void
    {
        $paths = self::outputPaths($item_id, $item_type, null);

        foreach (['display', 'thumbnail'] as $key) {
            if ($paths[$key] !== null && is_file($paths[$key])) {
                unlink($paths[$key]);
            }
        }

        // The web-served copies (display/thumbnail) go, ending public access,
        // but the private original under uploads/private/originals is kept: a
        // report's snapshot records the attachment ids, and the originals are
        // the forensic record a moderator recovers deleted media from.
    }

    /**
     * Locates a FeedItem's preserved original by globbing the originals dir (its
     * extension is stored nowhere else) and classifies it by MIME. This is the
     * one lookup shared by the report card (which uses mediaType to pick the
     * element) and the mod-only passthrough api/report-attachment.php (which
     * streams path with mimeType) - so "what type is attachment N" is answered
     * in exactly one place. Null when no original is on disk.
     *
     * @return array{path: string, mimeType: string, mediaType: string}|null
     *         mediaType is one of 'image', 'video', 'audio', 'file'
     */
    public static function originalForItem(int $item_id): ?array
    {
        $matches = self::originalMatches($item_id);

        if ($matches === []) {
            return null;
        }

        $path = $matches[0];
        $mime = self::mimeType($path);

        $media_type = self::INLINE_ORIGINAL_TYPES[strtolower($mime)] ?? 'file';

        return ['path' => $path, 'mimeType' => $mime, 'mediaType' => $media_type];
    }

    private static function mimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime !== false ? $mime : 'application/octet-stream';
    }

    /** @return string[] */
    private static function originalMatches(int|string $id): array
    {
        return glob(self::ORIGINALS_DIR . '/' . self::shard($id) . '/' . $id . '-original.*') ?: [];
    }

    private static function outputPaths(int|string $id, string $item_type, ?string $original_extension): array
    {
        $display_dir = self::UPLOAD_DIR . '/' . self::shard($id);

        // MIME is detected from bytes when a moderator requests the original;
        // its attacker-supplied extension has no operational value. A fixed
        // inert suffix keeps a web-server configuration mistake from turning a
        // preserved upload into executable source.
        $original = $original_extension !== null
            ? self::ORIGINALS_DIR . '/' . self::shard($id) . '/' . $id . '-original.bin'
            : null;

        return match ($item_type) {
            'ImageItem' => [
                'display' => $display_dir . '/' . $id . '.jpg',
                'thumbnail' => $display_dir . '/' . $id . '-thumb.jpg',
                'original' => $original,
            ],
            'VideoItem' => [
                'display' => $display_dir . '/' . $id . '.mp4',
                'thumbnail' => $display_dir . '/' . $id . '-thumb.jpg',
                'original' => $original,
            ],
            'AudioItem' => [
                'display' => $display_dir . '/' . $id . '.mp3',
                'thumbnail' => null,
                'original' => $original,
            ],
        };
    }

    private static function processImage(\GdImage $image, int|string $id, string $tmp_path, ?string $original_filename): ?array
    {
        $paths = self::outputPaths($id, 'ImageItem', $original_filename === null ? null : self::safeExtension($original_filename));

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

        // The original is the forensic copy a moderator may later need, so
        // failing to keep it fails the whole upload - succeeding without it
        // would be a silent gap discovered exactly when the copy matters. A
        // failed copy() can leave a partial file, so it is cleaned up too.
        if ($paths['original'] !== null && !copy($tmp_path, $paths['original'])) {
            foreach (['display', 'thumbnail', 'original'] as $key) {
                if ($paths[$key] !== null && is_file($paths[$key])) {
                    unlink($paths[$key]);
                }
            }

            return null;
        }

        return ['itemType' => 'ImageItem'];
    }

    /**
     * The locked-down ffprobe prefix shared by every probe: quiet, time-limited,
     * restricted to the local file protocol, and bounded in how much it reads to
     * detect the format. Callers append their own `-show_entries`/`-of` and the
     * (escapeshellarg'd) path plus `2>&1`.
     */
    private static function ffprobePrefix(): string
    {
        return sprintf(
            'timeout %d ffprobe -v error -protocol_whitelist %s -analyzeduration %d -probesize %d',
            self::FF_PROBE_TIMEOUT,
            self::FF_PROTOCOL_WHITELIST,
            self::FF_ANALYZE_DURATION,
            self::FF_PROBE_SIZE
        );
    }

    /**
     * Whether the file's container is one we accept (see SAFE_CONTAINER_FORMATS).
     * ffprobe reports format_name as a comma-joined list of the demuxers that
     * claim the file; the file passes if any token is allowlisted. Fails closed:
     * an unreadable, errored, or unrecognised container returns false, so the
     * dangerous local-file-reading / network demuxers never reach a transcode.
     */
    private static function containerAllowed(string $path): bool
    {
        $output = (string) shell_exec(
            self::ffprobePrefix() . ' -show_entries format=format_name -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>&1'
        );

        $formats = array_values(array_filter(array_map('trim', explode(',', trim($output)))));

        if (!array_intersect($formats, self::SAFE_CONTAINER_FORMATS)) {
            return false;
        }

        $prefix = file_get_contents($path, false, null, 0, 512);

        return is_string($prefix) && self::containerMagicMatches($prefix, $formats);
    }

    /**
     * Requires the claimed demuxer to have its signature at the container's
     * real start. ffprobe deliberately scans through leading junk, which is
     * useful for damaged media but would let an HTML/script prefix survive in
     * the preserved original while the tail is treated as audio or video.
     *
     * @param string[] $formats
     */
    private static function containerMagicMatches(string $prefix, array $formats): bool
    {
        $starts = static fn (string $magic): bool => str_starts_with($prefix, $magic);
        $bytes = static fn (int $offset, string $magic): bool => substr($prefix, $offset, strlen($magic)) === $magic;
        $frame_sync = strlen($prefix) >= 2 && ord($prefix[0]) === 0xff && (ord($prefix[1]) & 0xe0) === 0xe0;
        $iso_size = strlen($prefix) >= 8 ? (unpack('Nsize', substr($prefix, 0, 4))['size'] ?? 0) : 0;
        $iso_atom = $iso_size >= 8 && in_array(substr($prefix, 4, 4), ['ftyp', 'moov', 'mdat', 'free', 'wide'], true);

        foreach ($formats as $format) {
            $matches = match ($format) {
                'mp3', 'mp2' => $starts('ID3') || $frame_sync,
                'wav' => $starts('RIFF') && $bytes(8, 'WAVE'),
                'w64' => $starts('riff'),
                'flac' => $starts('fLaC'),
                'ogg' => $starts('OggS'),
                'aac' => $starts('ADIF') || $frame_sync,
                'aiff', 'aif' => $starts('FORM') && ($bytes(8, 'AIFF') || $bytes(8, 'AIFC')),
                'ac3' => $starts("\x0b\x77"),
                'amr' => $starts('#!AMR'),
                'caf' => $starts('caff'),
                'au' => $starts('.snd'),
                'wv' => $starts('wvpk'),
                'ape' => $starts('MAC '),
                'tta' => $starts('TTA1'),
                'mpc' => $starts('MPCK') || $starts('MP+'),
                'mov', 'mp4', 'm4a', 'm4v', '3gp', '3g2', 'mj2' => $iso_atom,
                'matroska', 'webm' => $starts("\x1a\x45\xdf\xa3"),
                'avi' => $starts('RIFF') && $bytes(8, 'AVI '),
                'flv' => $starts('FLV'),
                'asf' => $starts("\x30\x26\xb2\x75\x8e\x66\xcf\x11"),
                'mpeg', 'mpegvideo' => $starts("\x00\x00\x01\xba") || $starts("\x00\x00\x01\xb3") || $starts("\x00\x00\x01\xb0"),
                'mpegts' => (strlen($prefix) > 188 && ord($prefix[0]) === 0x47 && ord($prefix[188]) === 0x47)
                    || (strlen($prefix) > 196 && ord($prefix[4]) === 0x47 && ord($prefix[196]) === 0x47),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private static function probeMedia(string $path): ?string
    {
        // Reject unrecognised / dangerous containers before anything decodes.
        if (!self::containerAllowed($path)) {
            return null;
        }

        // Include each stream's attached_pic disposition. A "video" stream that
        // is an attached picture is embedded cover art (common in music MP3s),
        // not real video - such a file is audio. Only a non-attached-picture
        // video stream makes it a video, so a cover-art MP3 isn't misrouted to
        // the video transcoder, which fails on it.
        $output = (string) shell_exec(
            self::ffprobePrefix() . ' -show_entries stream=codec_type:stream_disposition=attached_pic -of csv=p=0 ' . escapeshellarg($path) . ' 2>&1'
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
            self::ffprobePrefix() . ' -show_entries format=duration -of csv=p=0 ' . escapeshellarg($path) . ' 2>&1'
        );

        // A failed probe or non-numeric output casts to 0.0, which makes the
        // caller seek to the very first frame - a safe fallback, never past EOF.
        return max(0.0, (float) trim($output));
    }

    /**
     * The locked-down ffmpeg input flags placed before every `-i`: no stdin, no
     * banner, the local `file` protocol only (no SSRF), and bounded format
     * probing. Shared by the transcodes and the poster-frame grab.
     */
    private static function ffmpegInputFlags(): string
    {
        return sprintf(
            '-nostdin -hide_banner -protocol_whitelist %s -analyzeduration %d -probesize %d',
            self::FF_PROTOCOL_WHITELIST,
            self::FF_ANALYZE_DURATION,
            self::FF_PROBE_SIZE
        );
    }

    /**
     * Wraps an ffmpeg command with the OS-level resource limits a PHP
     * memory_limit can't provide: a wall-clock cap via timeout(1), plus CPU-time
     * and address-space caps via a bash `ulimit` preamble. `exec` replaces the
     * shell with ffmpeg so timeout supervises ffmpeg directly and no wrapper
     * process lingers. A timed-out / over-limit run is SIGKILLed and its
     * nonzero exit makes the caller treat the transcode as failed.
     */
    private static function guardedCommand(string $ffmpeg_command): string
    {
        // Niced, so a transcode gets the cores nothing else wants. Several run
        // at once (uploadWorkerConcurrency) and each is happy to saturate one
        // for minutes; unniced, a burst of uploads is indistinguishable from
        // the site going down for everybody reading it.
        $preamble = 'ulimit -v ' . self::FF_MAX_ADDRESS_SPACE_KB . ' -t ' . self::FF_CPU_TIMELIMIT . '; exec nice -n 10 ' . $ffmpeg_command;

        return sprintf('timeout -k 10 %d bash -c %s', self::FF_WALL_TIMEOUT, escapeshellarg($preamble));
    }

    /**
     * Whether the first video stream's frame exceeds VIDEO_MAX_SOURCE_PIXELS -
     * checked before any decode so a decode bomb is rejected up front rather
     * than left to the address-space ulimit. Unreadable dimensions fall through
     * (return false); the ulimit still backstops that case.
     */
    private static function sourceVideoTooLarge(string $path): bool
    {
        $output = (string) shell_exec(
            self::ffprobePrefix() . ' -select_streams v:0 -show_entries stream=width,height -of csv=p=0 ' . escapeshellarg($path) . ' 2>&1'
        );

        $parts = explode(',', trim($output));

        if (count($parts) < 2) {
            return false;
        }

        return ((int) $parts[0]) * ((int) $parts[1]) > self::VIDEO_MAX_SOURCE_PIXELS;
    }

    private static function processVideo(string $tmp_path, int|string $id, ?string $original_filename): ?array
    {
        // Reject an oversized source frame before decoding anything (decode bomb).
        if (self::sourceVideoTooLarge($tmp_path)) {
            return null;
        }

        $extension = $original_filename === null ? null : self::safeExtension($original_filename);
        $paths = self::outputPaths($id, 'VideoItem', $extension);

        // Failing to keep the original fails the upload - see processImage.
        if ($paths['original'] !== null && !copy($tmp_path, $paths['original'])) {
            if (is_file($paths['original'])) {
                unlink($paths['original']);
            }

            return null;
        }

        $raw_frame_path = self::UPLOAD_DIR . '/' . self::shard($id) . '/' . $id . '-raw-frame.jpg';

        // Cap resolution/framerate - we're not streaming ultra HD, and bandwidth matters.
        // scale filter only ever downscales (never upscales smaller sources).
        $scale_filter = sprintf(
            'scale=\'min(%d,iw)\':\'min(%d,ih)\':force_original_aspect_ratio=decrease,scale=\'trunc(iw/2)*2\':\'trunc(ih/2)*2\'',
            self::VIDEO_MAX_WIDTH,
            self::VIDEO_MAX_HEIGHT
        );

        // -map 0:v:0 -map 0:a:0? -sn -dn: take only the first video and (if
        // present) first audio stream, dropping subtitle/data streams (subtitle
        // demuxers can reference external files, and extra streams are surface
        // we don't render). -threads bounds CPU.
        exec(self::guardedCommand(sprintf(
            'ffmpeg %s -y -i %s -map 0:v:0 -map %s -sn -dn -vf %s -r %d -threads %d -map_metadata -1 -map_chapters -1 -fflags +bitexact -flags:v +bitexact -flags:a +bitexact -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart %s 2>&1',
            self::ffmpegInputFlags(),
            escapeshellarg($tmp_path),
            escapeshellarg('0:a:0?'),
            escapeshellarg($scale_filter),
            self::VIDEO_MAX_FRAMERATE,
            self::FF_THREADS,
            escapeshellarg($paths['display'])
        )), $output_lines, $exit_code);

        if ($exit_code !== 0 || !is_file($paths['display'])) {
            // Under the non-web-served private tree: ffmpeg's output names
            // absolute server paths, which a publicly fetchable log would leak.
            $failure_log = self::ORIGINALS_DIR . '/' . self::shard($id) . '/' . $id . '-failure.log';
            file_put_contents($failure_log, implode("\n", $output_lines));
            // A timed-out / killed run can leave a partial display file; clear it
            // so failures don't accumulate on disk.
            @unlink($paths['display']);

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

        exec(self::guardedCommand(sprintf(
            'ffmpeg %s -y -ss %s -i %s -frames:v 1 -threads %d %s 2>&1',
            self::ffmpegInputFlags(),
            sprintf('%.3f', $poster_seek),
            escapeshellarg($paths['display']),
            self::FF_THREADS,
            escapeshellarg($raw_frame_path)
        )), $frame_output_lines, $frame_exit_code);

        $poster_ok = false;

        if ($frame_exit_code === 0 && is_file($raw_frame_path)) {
            $frame_image = @imagecreatefromjpeg($raw_frame_path);

            if ($frame_image !== false) {
                // Kept at the video's own size rather than shrunk to a
                // thumbnail: this file is the <video> element's poster, so it's
                // shown wherever the video is - inline and full screen - and a
                // thumbnail-sized one is visibly soft at either. The frame comes
                // from the already-transcoded file, so it's bounded by the same
                // VIDEO_MAX_WIDTH/HEIGHT the video is, and resizeAndSave only
                // ever scales down - at this cap it just re-encodes.
                $poster_ok = ImageProcessor::resizeAndSave($frame_image, $paths['thumbnail'], max(self::VIDEO_MAX_WIDTH, self::VIDEO_MAX_HEIGHT));
                imagedestroy($frame_image);
            }

            unlink($raw_frame_path);
        }

        if (!$poster_ok) {
            unlink($paths['display']);

            // resizeAndSave() can fail after imagejpeg() already created a
            // partial poster file (e.g. disk full) - clean that up too, or it's
            // left orphaned with no sweeper to remove it (same reasoning
            // processImage's failure path spells out).
            if ($paths['thumbnail'] !== null && is_file($paths['thumbnail'])) {
                unlink($paths['thumbnail']);
            }

            if ($paths['original'] !== null) {
                unlink($paths['original']);
            }

            return null;
        }

        return ['itemType' => 'VideoItem'];
    }

    private static function processAudio(string $tmp_path, int|string $id, ?string $original_filename): ?array
    {
        $extension = $original_filename === null ? null : self::safeExtension($original_filename);
        $paths = self::outputPaths($id, 'AudioItem', $extension);

        // Failing to keep the original fails the upload - see processImage.
        if ($paths['original'] !== null && !copy($tmp_path, $paths['original'])) {
            if (is_file($paths['original'])) {
                unlink($paths['original']);
            }

            return null;
        }

        // -map 0:a:0 -vn -sn -dn: take only the first audio stream and drop
        // everything else - embedded cover art (an attached-picture video
        // stream, common in music MP3s; leaving it in can make the mp3 muxing
        // fail), subtitles, and data streams. -threads bounds CPU.
        exec(self::guardedCommand(sprintf(
            'ffmpeg %s -y -i %s -map 0:a:0 -vn -sn -dn -threads %d -map_metadata -1 -id3v2_version 0 -c:a libmp3lame -b:a 320k %s 2>&1',
            self::ffmpegInputFlags(),
            escapeshellarg($tmp_path),
            self::FF_THREADS,
            escapeshellarg($paths['display'])
        )), $output_lines, $exit_code);

        if ($exit_code !== 0 || !is_file($paths['display'])) {
            // Under the non-web-served private tree: ffmpeg's output names
            // absolute server paths, which a publicly fetchable log would leak.
            $failure_log = self::ORIGINALS_DIR . '/' . self::shard($id) . '/' . $id . '-failure.log';
            file_put_contents($failure_log, implode("\n", $output_lines));
            // A timed-out / killed run can leave a partial display file; clear it
            // so failures don't accumulate on disk.
            @unlink($paths['display']);

            if ($paths['original'] !== null) {
                unlink($paths['original']);
            }

            return null;
        }

        return ['itemType' => 'AudioItem'];
    }
}
