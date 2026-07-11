<?php

declare(strict_types=1);

class OutgoingFriendRequestList extends PaginatedUserList
{
    protected string $listType = 'outgoing';
    protected string $heading = 'Sent requests (awaiting response)';
    protected string $emptyMessage = 'No outgoing requests.';

    protected static function fetch(User $user, int $limit, ?int $before_friendship_id): array
    {
        return $user -> getOutgoingFriendRequests($limit, $before_friendship_id);
    }
}
