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

        return $written && is_file($destination);
    }
}
