<?php

declare(strict_types=1);

class RandomUserSection extends UserSection
{
    protected string $heading = 'People to discover';

    public int $viewerId = 0;

    protected function list(): ItemLoader
    {
        return new RandomUserList(['viewerId' => $this -> viewerId, 'offset' => $this -> offset]);
    }
}
