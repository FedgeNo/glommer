<?php

declare(strict_types=1);

class PendingFriendRequestList extends UserList
{
    protected string $heading = 'Pending requests';
    protected string $emptyMessage = 'No pending requests.';
}
