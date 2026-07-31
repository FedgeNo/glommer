<?php

declare(strict_types=1);

class SentFriendRequestSection extends UserSection
{
    protected string $heading = 'Sent requests (awaiting response)';

    protected function list(): ItemLoader
    {
        return new SentFriendRequestList(['user' => $this -> user, 'offset' => $this -> offset]);
    }
}
