<?php

declare(strict_types=1);

/**
 * The results area of a UserSearch. It stands as the ranked suggestions until
 * something is typed, then the client rebuilds it with the matches for the
 * current query (see main.js).
 */
class UserSearchList extends EligibleSuggestedUserList
{
}
