<?php

declare(strict_types=1);

/**
 * The posts a member has pinned, shown above their ordinary posts.
 *
 * Not an ItemLoader: the list is capped at PinnedPost::MAX_PINNED, so there is
 * nothing to page through and no more to load. It renders what there is or
 * nothing at all.
 */
class PinnedPostList extends UnorderedList
{
    public ?string $class = 'PinnedPostList';
    public array $mixins = ['d-flex', 'flex-column'];

    public ?int $userId = null;

    public function toDOM(): \DOMElement
    {
        foreach (PinnedPost::postsFor((int) $this -> userId) as $post) {
            $item = new ListItem();
            $item -> addContent($post);
            $this -> contents[] = $item;
        }

        return parent::toDOM();
    }
}
