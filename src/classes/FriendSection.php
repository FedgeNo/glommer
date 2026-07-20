<?php

declare(strict_types=1);

class FriendSection extends UserSection
{
    protected string $heading = 'Friends';

    protected function list(): ItemLoader
    {
        return new FriendList(['user' => $this -> user, 'offset' => $this -> offset]);
    }
}
