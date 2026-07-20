<?php

declare(strict_types=1);

/**
 * A titled list of users - a <section> with an <h2> over a <ul> of user cards.
 *
 * Subclasses differ only in which users they select: each supplies its own
 * query through fetchUsers() and inherits the paging from here, so a new kind
 * of user list is a query and a heading rather than another copy of the
 * offset/has-more bookkeeping.
 *
 * Selecting users is this class's job, not User's - a User is one account, and
 * "the twenty accounts to show this viewer" is a property of the list being
 * built, not of any of the accounts in it.
 */
abstract class UserList extends ListSection
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'UserList';

    protected string $itemsClass = 'UserItems';

    /** Which page toDOM() renders; rows()/toJSON() can be asked for another. */
    public int $offset = 0;

    protected int $viewerId = 0;

    /** @var OtherUser[] */
    public array $items = [];

    /** Pages already fetched, keyed by offset, so asking twice costs one query. */
    private array $fetchedByOffset = [];

    public function __construct(int $viewer_id, int $offset = 0)
    {
        parent::__construct();

        $this -> viewerId = $viewer_id;
        $this -> offset = $offset;
    }

    /**
     * The users this list is for. Each subclass answers with its own query.
     *
     * @return OtherUser[]
     */
    abstract protected function fetchUsers(int $limit, int $offset): array;

    /**
     * One page of users, starting at $offset. The extra row fetched to detect
     * a next page is not included.
     *
     * @return OtherUser[]
     */
    public function rows(?int $offset = null): array
    {
        return array_slice($this -> page($offset ?? $this -> offset), 0, static::PAGE_SIZE);
    }

    public function hasMore(?int $offset = null): bool
    {
        return count($this -> page($offset ?? $this -> offset)) > static::PAGE_SIZE;
    }

    /**
     * The same page as a JSON payload, for the endpoints that hand users to
     * the client. $viewer is who it's built for - each card's friendship state
     * is relative to them - and the returned offset is where the next page
     * begins.
     *
     * @return array{users: array[], hasMore: bool, offset: int}
     */
    public function toJSON(User $viewer, ?int $offset = null): array
    {
        $offset = $offset ?? $this -> offset;
        $rows = $this -> rows($offset);

        return [
            'users' => array_map(static fn (OtherUser $user): array => OtherUser::payloadFor($user, $viewer), $rows),
            'hasMore' => $this -> hasMore($offset),
            'offset' => $offset + count($rows),
        ];
    }

    /**
     * PAGE_SIZE + 1 rows at $offset: the extra one's presence is what says
     * there's another page.
     *
     * @return OtherUser[]
     */
    private function page(int $offset): array
    {
        if (!array_key_exists($offset, $this -> fetchedByOffset)) {
            $this -> fetchedByOffset[$offset] = $this -> fetchUsers(static::PAGE_SIZE + 1, $offset);
        }

        return $this -> fetchedByOffset[$offset];
    }

    public function toDOM(): \DOMElement
    {
        $this -> items = $this -> rows();

        $this -> attributes['data-offset'] = (string) ($this -> offset + count($this -> items));
        $this -> attributes['data-has-more'] = $this -> hasMore() ? '1' : '0';

        return parent::toDOM();
    }
}
