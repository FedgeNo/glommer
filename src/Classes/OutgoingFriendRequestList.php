<?php

declare(strict_types=1);

class OutgoingFriendRequestList extends UserList
{
    protected string $heading = 'Sent requests (awaiting response)';
    protected string $emptyMessage = 'No outgoing requests.';
}
