<?php

declare(strict_types=1);

/**
 * The "Replies" heading above a post's reply list. Only rendered when
 * replies exist - Composer.js builds the identical element client-side when the
 * viewer posts the first reply, so keep the two in sync.
 */
class RepliesHeading extends Heading2
{
    public ?string $class = 'RepliesHeading';
    public array $mixins = ['fw-bold', 'text-lg'];

    public function __construct()
    {
        parent::__construct((string) (Strings::for(self::class)['heading'] ?? ''));
    }
}
