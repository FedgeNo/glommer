<?php

declare(strict_types=1);

class PendingFriendRequestSection extends UserSection
{
    protected string $heading = 'Pending requests';

    protected function list(): ItemLoader
    {
        return new PendingFriendRequestList(['user' => $this -> user, 'offset' => $this -> offset]);
    }
}
