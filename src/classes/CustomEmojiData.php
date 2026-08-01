<?php

declare(strict_types=1);

/** The columns read off a CustomEmojis row. */
class CustomEmojiData
{
    public ?int $customEmojiId = null;
    public ?string $domain = null;
    public ?string $shortcode = null;
    public ?string $imageURL = null;
    public ?string $createdAt = null;
}
