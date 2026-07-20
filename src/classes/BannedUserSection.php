<?php

declare(strict_types=1);

class BannedUserSection extends UserSection
{
    protected string $heading = 'Banned Users';

    protected function list(): ItemLoader
    {
        return new BannedUserList(['offset' => $this -> offset]);
    }
}
