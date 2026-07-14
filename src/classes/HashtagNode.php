<?php

declare(strict_types=1);

/**
 * One tag in the /tags/ force-directed graph (HashtagGraph): a clickable "#tag"
 * link to the tag's page, carrying its post count as a data attribute so the
 * client sizes and weights the node by how often the tag is used. The server
 * renders it as a plain link (a readable, crawlable list with no JS); the
 * graph script positions it in 3D once it takes over.
 */
class HashtagNode extends Anchor
{
    public ?string $class = 'HashtagNode';

    public function __construct(string $tag, int $post_count)
    {
        parent::__construct(ServerURL::absolute('/tags/' . $tag));

        $this -> contents[] = '#' . $tag;
        $this -> attributes['data-count'] = (string) $post_count;
    }
}
