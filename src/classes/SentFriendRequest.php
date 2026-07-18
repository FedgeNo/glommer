<?php

declare(strict_types=1);

// An outgoing (still-pending) friend request this user sent. It renders
// exactly like any OtherUser whose friendship with the viewer is pending and
// was sent by the viewer - i.e. a "Cancel" button falls out of OtherUser's
// own logic, so no toDOM override is needed. friendshipId is declared
// because OutgoingFriendRequestList's query selects it (its sort key).
class SentFriendRequest extends OtherUser
{
    public ?int $friendshipId = null;
}
