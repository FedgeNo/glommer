<?php

declare(strict_types=1);

/**
 * A titled list of users - a <section> with an <h2> over a <ul> of user cards.
 * Every list of users on the site is one of these: a profile's friends or
 * requests, the discovery lists, search results, the admin banned list.
 *
 * Subclasses differ only in which users they select. Each supplies its own
 * query as rows(), which this constructor runs, so a subclass is a query and a
 * heading rather than another copy of the offset/has-more bookkeeping.
 * Whatever the query needs - whose profile the list belongs to, which page of
 * it, who's looking - is a property, seeded through the constructor
 * (new FriendList(['user' => $profile_user, 'offset' => 20])), not an argument.
 *
 * Selecting users is this class's job, not User's - a User is one account, and
 * "the twenty accounts to show here" is a property of the list being built,
 * not of any account in it.
 *
 * Grown on scroll and paginated by offset (how many cards the client already
 * shows); the scroll handlers in main.js drive every one of them generically
 * off the data-* attributes and the shared .UserListSection marker.
 */
abstract class UserListSection extends ListSection
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'UserListSection';

    protected string $itemsClass = 'UserItems';

    /** Which history this pages, for the lists the client can grow by type. */
    protected string $listType = '';

    /** Whose list this is, for the lists that belong to one profile. */
    public ?User $user = null;

    public int $offset = 0;

    /** @var User[] one page of them */
    public array $items = [];

    /** Whether there's a page after the one held here. */
    public bool $hasMore = false;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        // The query fetches one row past the page; that row's existence is the
        // whole answer to "is there more?", so it's spent on that here and
        // never reaches an output step.
        $this -> items = $this -> rows();
        $this -> hasMore = count($this -> items) > static::PAGE_SIZE;
        $this -> items = array_slice($this -> items, 0, static::PAGE_SIZE);
    }

    /**
     * This list's users: PAGE_SIZE + 1 rows starting at $offset, read off the
     * properties the constructor has just seeded.
     *
     * Protected rather than private because a subclass has to be able to
     * override it - PHP forbids both an abstract private method and a child
     * narrowing an inherited one to private. Nothing outside the hierarchy
     * calls it; toDOM() and toJSON() are how a list is consumed.
     *
     * @return User[]
     */
    abstract protected function rows(): array;

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

    /**
     * One page of users for the endpoints that hand a list to the client.
     *
     * @return array{items: User[], hasMore: bool}
     */
    public function toJSON(): array
    {
        $this -> markRendered();

        return [
            'items' => $this -> items,
            'hasMore' => $this -> hasMore,
        ];
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> listType !== '') {
            $this -> attributes['data-list-type'] = $this -> listType;
        }

        if ($this -> user !== null) {
            $this -> attributes['data-user-id'] = (string) $this -> user -> userId;
        }

        $this -> attributes['data-offset'] = (string) ($this -> offset + count($this -> items));
        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }
}
