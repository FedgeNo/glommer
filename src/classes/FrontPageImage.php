<?php

declare(strict_types=1);

/**
 * The image that stands for the site when the front page is shared - the
 * og:image link previews show. Nothing ships by default and nothing renders
 * on the page itself: it exists only in metadata, only once an admin has
 * uploaded one, and until then the front page simply advertises no image
 * (which beats a crawler electing whatever picture the feed led with).
 *
 * Stored under uploads/site/ like the custom favicon - the uploads tree is
 * the writable area - re-encoded to a 1200x630 PNG, never the original bytes.
 */
class FrontPageImage
{
    public const CUSTOM_SETTING = 'hasFrontPageImage';

    private const CUSTOM_DIR = __DIR__ . '/../../uploads/site';
    private const CUSTOM_PATH = self::CUSTOM_DIR . '/front-page.png';
    private const CUSTOM_URL_PATH = '/uploads/site/front-page.png';

    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /** The image's URL, or null when no admin has provided one. */
    public static function URL(): ?string
    {
        if ((string) Settings::get(self::CUSTOM_SETTING, '') === '1' && is_file(self::CUSTOM_PATH)) {
            return ServerURL::absolute(self::CUSTOM_URL_PATH);
        }

        return null;
    }

    /**
     * Center-crops the upload to the 1200x630 the preview cards expect and
     * re-encodes it as PNG. Returns false if the upload isn't a readable
     * image.
     */
    public static function updateFromUpload(string $tmp_path): bool
    {
        $source = ImageProcessor::load($tmp_path);

        if ($source === false) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // The largest window of the source with the target's shape, centered.
        $target_ratio = self::WIDTH / self::HEIGHT;
        $crop_width = $width;
        $crop_height = (int) round($width / $target_ratio);

        if ($crop_height > $height) {
            $crop_height = $height;
            $crop_width = (int) round($height * $target_ratio);
        }

        $banner = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        imagecopyresampled(
            $banner,
            $source,
            0,
            0,
            (int) (($width - $crop_width) / 2),
            (int) (($height - $crop_height) / 2),
            self::WIDTH,
            self::HEIGHT,
            $crop_width,
            $crop_height
        );

        imagedestroy($source);

        if (!is_dir(self::CUSTOM_DIR)) {
            mkdir(self::CUSTOM_DIR, 0755, true);
        }

        $written = imagepng($banner, self::CUSTOM_PATH);
        imagedestroy($banner);

        if (!$written) {
            return false;
        }

        Settings::set(self::CUSTOM_SETTING, '1');

        return true;
    }
}
