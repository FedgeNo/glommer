<?php

declare(strict_types=1);

/**
 * The "Replies" heading above a post's reply list. Only rendered when
 * replies exist - main.js builds the identical element client-side when the
 * viewer posts the first reply, so keep the two in sync.
 */
class RepliesHeading extends Heading2
{
    public ?string $class = 'RepliesHeading fw-bold text-lg';

    public function __construct()
    {
        parent::__construct('Replies');
    }
}
