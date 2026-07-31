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
     * The next-page request's fixed fields, for a list the client can grow;
     * null for one that renders everything it has at once. Declared here rather
     * than by overriding dataAttributes() so a paging subclass can't drop
     * data-list-type on its way past - that attribute is what tells the
     * accept-a-friend-request handler which list a card sits in.
     *
     * @return array<string, mixed>|null
     */
    protected function scrollConfig(): ?array
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        $attributes = [];

        if ($this -> listType !== '') {
            $attributes['data-list-type'] = $this -> listType;
        }

        $scroll_config = $this -> scrollConfig();

        if ($scroll_config !== null) {
            $attributes['data-infinite-scroll'] = (string) json_encode($scroll_config);
        }

        return $attributes;
    }
}
