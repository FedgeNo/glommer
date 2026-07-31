<?php

declare(strict_types=1);

/**
 * The columns read off a Hashtags row. Some queries fetch only a subset of
 * these - the rest just stay null.
 */
class HashtagData
{
    public ?int $hashtagId = null;
    public ?string $slug = null;
    public ?string $title = null;
    public ?string $description = null;
}
