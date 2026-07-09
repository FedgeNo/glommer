<?php

declare(strict_types=1);

class FriendList extends UserList
{
    protected string $heading = 'Friends';
    protected string $emptyMessage = 'You haven\'t got any friends yet.';

    /**
     * @param array[] $rows
     */
    public static function fromRows(array $rows): self
    {
        $list = new self();

        foreach ($rows as $row) {
            $list -> items[] = OtherUser::fromRow($row);
        }

        return $list;
    }
}
