<?php

declare(strict_types=1);

/**
 * The "See More..." link a truncated post preview appends when it has cut
 * content off - it links to the full post. Self-assembling: hand it the
 * post's URL and it builds its own href, text, and (right-aligning, via
 * style.css) SeeMore class.
 */
class SeeMore extends Anchor
{
    public ?string $class = 'SeeMore';

    public function __construct(string $url)
    {
        parent::__construct($url, 'See More...');
    }
}
