<?php

declare(strict_types=1);

/**
 * A titled list of users - a <section> with an <h2> over a <ul> of user cards.
 * Every list of users on the site is one of these: a profile's friends or
 * requests, the discovery lists, search results, the admin banned list.
 *
 * Subclasses differ only in which users they select, so a subclass is a query
 * and a heading. Selecting users is this class's job, not User's - a User is
 * one account, and "the twenty accounts to show here" is a property of the list
 * being built, not of any account in it.
 *
 * The scroll handlers in main.js grow every one of them generically, off the
 * data-* attributes and the shared .UserListSection marker.
 */
abstract class UserListSection extends ListSection
{
    public ?string $class = 'UserListSection';

    protected string $itemsClass = 'UserItems';

    /** Which history this pages, for the lists the client can grow by type. */
    protected string $listType = '';

    /** Whose list this is, for the lists that belong to one profile. */
    public ?User $user = null;

    /** @var User[] */
    public array $items = [];

    /**
     * The client fills these lists in after load - a search rebuilds its
     * results and retitles them, accepting a request moves a card between two
     * of them - so the heading and the empty <ul> under it have to be there to
     * be found.
     */
    protected function headsEmptyList(): bool
    {
        return true;
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> listType !== '') {
            $this -> attributes['data-list-type'] = $this -> listType;
        }

        if ($this -> user !== null) {
            $this -> attributes['data-user-id'] = (string) $this -> user -> userId;
        }

        return parent::toDOM();
    }
}
