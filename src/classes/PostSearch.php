<?php

declare(strict_types=1);

class PostSearch extends HTMLObject
{
    public ?string $class = 'PostSearch';

    /**
     * Scopes the search to one profile's posts; 0 searches everyone. The client
     * reads data-user-id to pass ?userId to /api/search-posts.
     */
    public int $userId = 0;

    public string $placeholder = 'Search posts...';

    public function toDOM(): \DOMElement
    {
        if ($this -> userId !== 0) {
            $this -> attributes['data-user-id'] = (string) $this -> userId;
        }

        $this -> contents[] = new PostSearchBox(['placeholder' => $this -> placeholder]);

        return parent::toDOM();
    }
}
