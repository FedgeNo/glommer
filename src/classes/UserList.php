<?php

declare(strict_types=1);

abstract class UserList extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'UserList';

    protected string $heading = '';
    protected string $emptyMessage = '';

    /** @var User[] */
    public array $items = [];

    public function toDOM(): \DOMElement
    {
        $heading = new Heading2();
        $heading -> contents[] = $this -> heading;
        $this -> contents[] = $heading;

        if ($this -> items === []) {
            $this -> contents[] = new Notice($this -> emptyMessage);
        } else {
            $this -> addContents($this -> items);
        }

        return parent::toDOM();
    }
}
