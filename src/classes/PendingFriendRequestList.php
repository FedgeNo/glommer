<?php

declare(strict_types=1);

class PendingFriendRequestList extends PaginatedUserList
{
    protected string $listType = 'incoming';
    protected string $heading = 'Pending requests';
    protected string $emptyMessage = 'No pending requests.';

    protected static function fetch(User $user, int $limit, ?int $before_friendship_id): array
    {
        return $user -> getIncomingFriendRequests($limit, $before_friendship_id);
    }
}
