<?php

declare(strict_types=1);

class OutgoingFriendRequestList extends UserList
{
    protected string $heading = 'Sent requests (awaiting response)';
    protected string $emptyMessage = 'No outgoing requests.';

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
