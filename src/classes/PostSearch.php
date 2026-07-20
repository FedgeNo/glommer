<?php

declare(strict_types=1);

class PostSearch extends HTMLObject
{
    public ?string $class = 'PostSearch';

    /**
     * $author_id scopes the search to one user's posts (the per-user search on a
     * profile page); null searches everyone (the global /search page). The
     * client reads data-user-id to pass ?userId to /api/search-posts.
     */
    public function __construct(private readonly ?int $authorId = null, private readonly string $placeholder = 'Search posts...')
    {
        parent::__construct();
    }

    /**
     * The posts matching a search, newest first. $author_id of 0 searches
     * everyone; a real id scopes it to that author. Posts from banned accounts
     * are left out.
     *
     * Blocking deliberately doesn't filter this. A block exists to stop
     * interaction - replying, messaging - not to hide posts that are public
     * anyway: anyone can read them signed out, so filtering them here would
     * only inconvenience the blocker while changing nothing about who can
     * actually see what.
     *
     * Remote-origin posts ARE excluded: a followed Fediverse account's posts
     * are for whoever followed it, and search answers anyone who asks.
     *
     * @return Post[]
     */
    public static function matchingRows(string $query, int $author_id, int $limit, int $offset): array
    {
        $not_banned = 0;

        return DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE MATCH(`Posts`.`title`, `Posts`.`description`, `Posts`.`keywords`) AGAINST (? IN NATURAL LANGUAGE MODE)
        AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
        AND (? = 0 OR `Posts`.`userId` = ?)
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'siiiii', $query, $not_banned, $author_id, $author_id, $limit, $offset);
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> authorId !== null) {
            $this -> attributes['data-user-id'] = (string) $this -> authorId;
        }

        $input_card = new Div();
        $input_card -> class = 'PostSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'PostSearchInput';
        $input -> attributes['placeholder'] = $this -> placeholder;
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        $this -> contents[] = new PostSearchResults();

        return parent::toDOM();
    }
}
