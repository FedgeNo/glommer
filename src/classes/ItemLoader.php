<?php

declare(strict_types=1);

/**
 * An element whose contents come from a query: it loads its own items when it's
 * constructed, then hands them to whichever output step is asked for.
 *
 * Every list on the site descends from here, down either lineage - the bare
 * <ul> lists through UnorderedList, the titled <section> lists through Section
 * - so all of them are built, paged and serialized the same way and differ
 * only in what rows() selects.
 *
 * The query goes in rows(), which the constructor calls. Whatever that query
 * needs - whose profile the list belongs to, which page of it, who's looking -
 * is a property, seeded through the constructor
 * (new FriendList(['user' => $profile_user, 'offset' => 20])), never an
 * argument. Loading in the constructor means an object is fully populated the
 * moment it exists, so nothing downstream has to know whether it has been
 * "filled in" yet.
 */
abstract class ItemLoader extends HTMLObject
{
    /** How many items a page holds. */
    public const PAGE_SIZE = 20;

    /** Where this page starts - how many items the client already shows. */
    public int $offset = 0;

    /** @var mixed[] one page of them */
    public array $items = [];

    /** Whether there's a page after the one held here. */
    public bool $hasMore = false;

    /** Set on an element handed someone else's paging to advertise. */
    public bool $advertisesPaging = false;

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
     * This element's items: PAGE_SIZE + 1 of them starting at $offset, read off
     * the properties the constructor has just seeded.
     *
     * Protected rather than private because subclasses override it - PHP forbids
     * both an abstract private method and a child narrowing an inherited one to
     * private. Nothing outside the hierarchy calls it; toDOM() and toJSON() are
     * how a loaded element is spent.
     *
     * Empty by default so the plain wrappers (a bare <section>, the inner <ul>
     * a ListSection builds) stay usable as themselves. An abstract list base
     * re-declares it abstract so a real list can't forget it.
     *
     * @return mixed[]
     */
    protected function rows(): array
    {
        return [];
    }

    /**
     * Whether anything came back - lets a page hide a whole section rather
     * than show its heading over nothing.
     */
    public function hasItems(): bool
    {
        return $this -> items !== [];
    }

    /**
     * One page of items for the endpoints that hand a list to the client.
     *
     * @return array{items: mixed[], hasMore: bool}
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
        // Only something that selects its own items has a page to advertise.
        // The plain wrappers - the inner <ul> a ListSection builds around its
        // items, a bare <section> - would otherwise stamp a data-has-more of
        // their own for the scroll handlers to find and believe.
        if ($this -> advertisesPaging || $this -> loadsOwnItems()) {
            $this -> attributes['data-offset'] = (string) ($this -> offset + count($this -> items));
            $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';
        }

        return parent::toDOM();
    }

    private function loadsOwnItems(): bool
    {
        return (new \ReflectionMethod(static::class, 'rows')) -> getDeclaringClass() -> getName() !== self::class;
    }
}
