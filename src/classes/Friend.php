<?php

declare(strict_types=1);

// The Remove Friend button itself comes from OtherUser, which shows it on
// every card with an accepted friendship - Friend stays as the semantic
// class for friends-list entries. friendshipId is carried so a paginated
// FriendList can read the cursor (the last friend's Friendships row id).
class Friend extends OtherUser
{
    public ?int $friendshipId = null;
}
