<?php

declare(strict_types=1);

/**
 * A <ul> of user cards. Subclasses differ in which users they select, so a
 * subclass is a query and nothing else.
 *
 * Selecting users is this class's job, not User's - a User is one account, and
 * "the twenty accounts to show here" is a property of the list being built, not
 * of any account in it.
 *
 * The scroll handlers in main.js grow every one of them generically, off the
 * data-* attributes and the shared .UserList marker.
 */
abstract class UserList extends ItemList
{
    public ?string $class = 'UserList';

    /** Whose list this is, for the lists that belong to one profile. */
    public ?User $user = null;

    /** Which history this pages, for the lists the client can grow by type. */
    protected string $listType = '';

    /** @var User[] */
    public array $items = [];

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        $attributes = [];

        if ($this -> listType !== '') {
            $attributes['data-list-type'] = $this -> listType;
        }

        if ($this -> user !== null) {
            $attributes['data-user-id'] = (string) $this -> user -> userId;
        }

        return $attributes;
    }
}
