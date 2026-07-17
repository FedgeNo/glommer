<?php

declare(strict_types=1);

/**
 * The results area of a UserSearch - a <ul> of user cards. It starts as the
 * ranked suggestions shown before anything is typed, then the client rebuilds
 * it with the matches for the current query (see main.js).
 */
class UserSearchResults extends ItemList
{
    public ?string $class = 'UserSearchResults';

    /**
     * @param OtherUser[] $suggestions
     */
    public function __construct(array $suggestions = [])
    {
        parent::__construct();

        // The suggestion list is a fixed, ranked set, not cursor-paginated
        // (see api/search-users.php) - infinite scroll only ever kicks in once a
        // typed query gets a paginated result set of its own.
        $this -> attributes['data-has-more'] = '0';
        $this -> addContents($suggestions);
    }
}
