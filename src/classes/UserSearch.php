<?php

declare(strict_types=1);

class UserSearch extends HTMLObject
{
    public ?string $class = 'UserSearch';


    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new UserSearchBox();

        $this -> contents[] = new UserSearchSection();

        return parent::toDOM();
    }
}
