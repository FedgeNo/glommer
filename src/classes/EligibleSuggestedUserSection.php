<?php

declare(strict_types=1);

class EligibleSuggestedUserSection extends UserSection
{
    protected string $heading = 'People you may know';

    public int $viewerId = 0;

    protected function list(): ItemLoader
    {
        return new EligibleSuggestedUserList(['viewerId' => $this -> viewerId, 'offset' => $this -> offset]);
    }
}
