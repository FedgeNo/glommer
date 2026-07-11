<?php

declare(strict_types=1);

class FriendList extends PaginatedUserList
{
    protected string $listType = 'friends';
    protected string $heading = 'Friends';
    protected string $emptyMessage = 'You haven\'t got any friends yet.';

    protected static function fetch(User $user, int $limit, ?int $before_friendship_id): array
    {
        return $user -> getFriends($limit, $before_friendship_id);
    }

    public static function forUser(User $user, ?int $before_friendship_id = null): static
    {
        $list = parent::forUser($user, $before_friendship_id);

        // The default message is first-person, for your own friends page; a
        // third party's empty friends list names them instead.
        if (Auth::id() !== (int) $user -> userId) {
            $list -> emptyMessage = ($user -> displayName ?? $user -> username) . ' hasn\'t got any friends yet.';
        }

        return $list;
    }
}
