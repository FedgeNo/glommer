<?php

declare(strict_types=1);

class ImageProcessor
{
    public const DISPLAY_MAX_DIMENSION = 1600;
    public const THUMBNAIL_MAX_DIMENSION = 300;

    /**
     * Upper bound on source pixels before decoding. GD allocates ~width*height*4
     * bytes for the full raster before any resize, so a tiny file that declares
     * huge dimensions (a "decompression bomb") can exhaust memory. 50 MP clears
     * any real photo while rejecting the absurd (e.g. a 30000x30000 = 900 MP PNG).
     */
    public const MAX_SOURCE_PIXELS = 50_000_000;

    public static function load(string $path): \GdImage|false
    {
        $mime_type = self::detectMimeType($path);

        if (!str_starts_with($mime_type, 'image/')) {
            return false;
        }

        // Check declared dimensions from the header (cheap) before handing the
        // file to GD's full decode.
        $size = @getimagesize($path);

        if ($size === false || ($size[0] * $size[1]) > self::MAX_SOURCE_PIXELS) {
            return false;
        }

        return self::loadWithGd($path, $mime_type);
    }

    private static function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime !== false ? $mime : 'application/octet-stream';
    }

    private static function loadWithGd(string $path, string $mime_type): \GdImage|false
    {
        return match ($mime_type) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
    }

    public static function resizeAndSave(\GdImage $source, string $destination, int $max_dimension): bool
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $scale = min(1.0, $max_dimension / max($width, $height));
        $new_width = max(1, (int) round($width * $scale));
        $new_height = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        $written = imagejpeg($resized, $destination, 85);
        imagedestroy($resized);

        if (!$written || !is_file($destination)) {
            return false;
        }

        // The pixel re-encode already dropped the source's EXIF/metadata, but
        // GD's imagejpeg stamps its own "CREATOR: gd-jpeg ..." comment into the
        // file. Strip every comment/metadata marker so the saved image carries
        // no metadata whatsoever.
        self::stripJPEGMetadata($destination);

        return true;
    }

    /**
     * Removes all JPEG comment (COM) and APPn metadata segments from the file in
     * place, keeping only the baseline JFIF (APP0) header and the image data, so
     * the saved JPEG holds no metadata. Only ever runs on GD's own well-formed
     * output.
     */
    private static function stripJPEGMetadata(string $path): void
    {
        $data = (string) file_get_contents($path);

        if (strncmp($data, "\xFF\xD8", 2) !== 0) {
            return; // not a JPEG - leave it alone
        }

        $out = "\xFF\xD8";
        $offset = 2;
        $length = strlen($data);

        while ($offset + 3 < $length) {
            // A byte that isn't a marker start, or Start of Scan (0xDA, after
            // which the compressed image data runs to the end): stop parsing
            // segments and copy everything remaining verbatim.
            if ($data[$offset] !== "\xFF" || $data[$offset + 1] === "\xDA") {
                break;
            }

            $marker = $data[$offset + 1];
            $marker_ord = ord($marker);
            $segment_length = (ord($data[$offset + 2]) << 8) | ord($data[$offset + 3]);

            // Drop COM (0xFE) and APP1-APP15 (0xE1-0xEF) metadata segments; keep
            // APP0 (0xE0, the JFIF header) so the file stays a valid baseline JPEG.
            $is_metadata = $marker === "\xFE" || ($marker_ord >= 0xE1 && $marker_ord <= 0xEF);

            if (!$is_metadata) {
                $out .= substr($data, $offset, 2 + $segment_length);
            }

            $offset += 2 + $segment_length;
        }

        $out .= substr($data, $offset);
        file_put_contents($path, $out);
    }
}
