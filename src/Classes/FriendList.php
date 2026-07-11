<?php

declare(strict_types=1);

class FriendList extends UserList
{
    protected string $heading = 'Friends';
    protected string $emptyMessage = 'You haven\'t got any friends yet.';
}
