<?php

declare(strict_types=1);

class ReceivedFriendRequestSection extends UserSection
{
    protected string $heading = 'Pending requests';

    protected function list(): ItemLoader
    {
        return new ReceivedFriendRequestList(['user' => $this -> user, 'offset' => $this -> offset]);
    }
}
