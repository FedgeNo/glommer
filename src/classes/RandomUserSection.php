<?php

declare(strict_types=1);

class RandomUserSection extends UserSection
{
    protected string $heading = 'People to discover';


    protected function list(): ItemLoader
    {
        return new RandomUserList(['offset' => $this -> offset]);
    }
}
