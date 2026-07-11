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

    /**
     * @param User[] $items
     */
    public static function withItems(array $items): static
    {
        $list = new static();
        $list -> items = $items;

        return $list;
    }

    public function toDOM(): \DOMElement
    {
        $heading = new Heading2();
        $heading -> contents[] = $this -> heading;
        $this -> contents[] = $heading;

        if ($this -> items === []) {
            $this -> contents[] = new Notice($this -> emptyMessage);
        } else {
            foreach ($this -> items as $item) {
                $this -> contents[] = $item;
            }
        }

        return parent::toDOM();
    }
}
