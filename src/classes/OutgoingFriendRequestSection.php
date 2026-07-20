<?php

declare(strict_types=1);

class OutgoingFriendRequestSection extends UserSection
{
    protected string $heading = 'Sent requests (awaiting response)';

    protected function list(): ItemLoader
    {
        return new OutgoingFriendRequestList(['user' => $this -> user, 'offset' => $this -> offset]);
    }
}
