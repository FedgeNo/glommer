<?php

declare(strict_types=1);

/**
 * The results area of a UserSearch. It stands as the ranked suggestions until
 * something is typed, then the client rebuilds it with the matches for the
 * current query and retitles it (see main.js).
 */
class UserSearchResults extends EligibleSuggestedUserList
{
    protected string $heading = 'Suggested Users';
}
