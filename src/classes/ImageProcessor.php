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
        $data = @file_get_contents($path);

        if ($data === false) {
            return false;
        }

        // Header-only dimension check before the full decode: GD allocates
        // ~width*height*4 bytes for the raster, so a tiny file declaring huge
        // dimensions (a decompression bomb) is rejected before the bytes ever
        // reach imagecreatefromstring. Fail closed when the dimensions can't be
        // measured at all: GD's own .gd/.gd2 formats aren't readable by
        // getimagesizefromstring but ARE decoded by imagecreatefromstring (and
        // .gd2 is zlib-compressed - a genuine bomb: a tiny file expands to a
        // multi-gigabyte raster), so an unmeasurable header must be rejected
        // rather than allowed to skip the cap. Every real web image format
        // (JPEG/PNG/GIF/WebP/BMP) measures fine here, so nothing legitimate is lost.
        $size = @getimagesizefromstring($data);

        if ($size === false || ($size[0] * $size[1]) > self::MAX_SOURCE_PIXELS) {
            return false;
        }

        // GD's own decoder is the arbiter of what's a valid image:
        // imagecreatefromstring auto-detects the format and decodes whatever
        // this GD build supports (JPEG/PNG/GIF/WebP/BMP/...), returning false
        // for anything it can't - if it fails, it isn't an image we can use.
        return @imagecreatefromstring($data);
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
