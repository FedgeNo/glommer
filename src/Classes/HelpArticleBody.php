<?php

declare(strict_types=1);

/**
 * Renders an article's trusted HTML body (authored in HelpContent, never user
 * input) into the document. HTMLLoader parses the HTML string and imports it
 * as-is; the body is trusted, so there's nothing to sanitize.
 */
class HelpArticleBody extends HTMLLoader
{
    public ?string $class = 'HelpArticleBody';
}
