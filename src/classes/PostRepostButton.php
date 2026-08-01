<?php

declare(strict_types=1);

/**
 * Passes a post on to your own friends and Fediverse followers. One button in
 * two states, since reposting and undoing it are the same decision.
 */
class PostRepostButton extends ButtonButton
{
    public function __construct(bool $reposted, int $count)
    {
        parent::__construct();

        $this -> attributes['data-reposted'] = $reposted ? '1' : '0';
        $this -> contents[] = $count > 0 ? 'Repost ' . $count : 'Repost';
    }
}
