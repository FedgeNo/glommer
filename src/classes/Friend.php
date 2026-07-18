<?php

declare(strict_types=1);

// The Remove Friend button itself comes from OtherUser, which shows it on
// every card with an accepted friendship - Friend stays as the semantic
// class for friends-list entries. friendshipId is declared because
// FriendList's query selects it (the UNION halves merge-sort on it).
class Friend extends OtherUser
{
    public ?int $friendshipId = null;
}
