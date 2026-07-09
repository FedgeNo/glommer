<?php

declare(strict_types=1);

class PendingFriendRequestList extends UserList
{
    protected string $heading = 'Pending requests';
    protected string $emptyMessage = 'No pending requests.';

    /**
     * @param array[] $rows
     */
    public static function fromRows(array $rows): self
    {
        $list = new self();

        foreach ($rows as $row) {
            $list -> items[] = FriendRequest::fromRow($row);
        }

        return $list;
    }
}
