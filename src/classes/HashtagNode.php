<?php

declare(strict_types=1);

/**
 * One tag in the /tags/ force-directed graph (HashtagGraph): a clickable "#tag"
 * link to the tag's page, carrying its post count as a data attribute so the
 * client sizes and weights the node by how often the tag is used. The server
 * renders it as a plain link (a readable, crawlable list with no JS); the
 * graph script positions it in 3D once it takes over. Read from the
 * PopularHashtags table (see HashtagGraph) -> DB::rows(); hashtagId is carried
 * only so HashtagGraph can index the co-occurrence edges against node position.
 */
class HashtagNode extends Anchor
{
    public ?string $class = 'HashtagNode';

    public ?int $hashtagId = null;
    public ?string $slug = null;
    public ?string $title = null;
    public int $postCount = 0;

    public function toDOM(): \DOMElement
    {
        $this -> href = ServerURL::absolute('/tags/' . $this -> slug);
        $this -> contents[] = '#' . $this -> title;
        $this -> attributes['data-count'] = (string) $this -> postCount;

        return parent::toDOM();
    }
}
