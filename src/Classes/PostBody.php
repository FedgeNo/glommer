<?php

declare(strict_types=1);

class PostBody extends HTMLCleaner
{
    public ?string $class = 'PostBody';

    public array $whitelist = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'a' => ['href'],
        'ol' => [],
        'ul' => [],
        'li' => ['data-list'],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'span' => [],
    ];
}
