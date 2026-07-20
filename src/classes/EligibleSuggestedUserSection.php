<?php

declare(strict_types=1);

class EligibleSuggestedUserSection extends UserSection
{
    protected string $heading = 'People you may know';


    protected function list(): ItemLoader
    {
        return new EligibleSuggestedUserList(['offset' => $this -> offset]);
    }
}
