<?php

declare(strict_types=1);

class FriendSearch extends Div
{
    public ?string $class = 'FriendSearch';

    /** Whose friends are searched. The client reads data-user-id to pass it on. */
    public ?User $user = null;

    public string $placeholder = '';

    public function __construct(array|object|null $properties = null)
    {
        $this -> placeholder = (string) (Strings::for(FriendSearchBox::class)['placeholder'] ?? '');
        parent::__construct($properties);
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-user-id'] = (string) $this -> user -> userId;

        $this -> contents[] = new FriendSearchBox(['placeholder' => $this -> placeholder]);

        return parent::toDOM();
    }
}
