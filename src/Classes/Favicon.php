<?php

declare(strict_types=1);

/**
 * The site's browser-tab icon. Ships with a default /favicon.ico; the admin
 * can replace it from Site Settings, which stores a processed PNG under
 * uploads/site/ (the uploads tree is the writable area - the project root
 * isn't writable on a locked-down install). Every page's <link rel="icon">
 * points at whichever is current.
 */
class Favicon
{
    public const CUSTOM_SETTING = 'hasCustomFavicon';

    private const CUSTOM_DIR = __DIR__ . '/../../uploads/site';
    private const CUSTOM_PATH = self::CUSTOM_DIR . '/favicon.png';
    private const CUSTOM_URL_PATH = '/uploads/site/favicon.png';
    private const SIZE = 64;

    public static function URL(): string
    {
        if ((string) Settings::get(self::CUSTOM_SETTING, '') === '1' && is_file(self::CUSTOM_PATH)) {
            return URL::absolute(self::CUSTOM_URL_PATH);
        }

        return URL::absolute('/favicon.ico');
    }

    /**
     * Processes an uploaded image into the custom favicon: center-cropped
     * square, resized to 64x64, re-encoded as PNG (never the original bytes,
     * same principle as every other upload). Returns false if the upload
     * isn't a readable image.
     */
    public static function updateFromUpload(string $tmp_path): bool
    {
        $source = ImageProcessor::load($tmp_path);

        if ($source === false) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $crop = min($width, $height);

        $icon = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagesavealpha($icon, true);
        imagealphablending($icon, false);
        $transparent = imagecolorallocatealpha($icon, 0, 0, 0, 127);
        imagefill($icon, 0, 0, $transparent);
        imagealphablending($icon, true);

        imagecopyresampled(
            $icon,
            $source,
            0,
            0,
            (int) (($width - $crop) / 2),
            (int) (($height - $crop) / 2),
            self::SIZE,
            self::SIZE,
            $crop,
            $crop
        );

        imagedestroy($source);

        if (!is_dir(self::CUSTOM_DIR)) {
            mkdir(self::CUSTOM_DIR, 0755, true);
        }

        imagealphablending($icon, false);
        $written = imagepng($icon, self::CUSTOM_PATH);
        imagedestroy($icon);

        if (!$written) {
            return false;
        }

        Settings::set(self::CUSTOM_SETTING, '1');

        return true;
    }
}
