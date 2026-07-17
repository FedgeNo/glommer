<?php

declare(strict_types=1);

/**
 * A list of users tied to one profile - friends, incoming requests, outgoing
 * requests. Each subclass fetches its own rows straight into $items in its
 * constructor (new FriendList(['user' => $profileUser])); this base paginates
 * and renders them. Grown on scroll, cursored on the Friendships row id: a
 * subclass includes `friendshipId < before` in its query (before defaults to a
 * sentinel above any real id, so page one and a load-more page are one query),
 * and the scroll handler in main.js drives all three generically off the data-*
 * attributes and the shared .UserList marker.
 */
abstract class UserList extends Section
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'UserList';

    /** One of 'friends' | 'incoming' | 'outgoing' - which history this pages. */
    protected string $listType = '';
    protected string $heading = '';
    protected string $emptyMessage = '';

    public ?User $user = null;
    public ?int $before = null;

    // Filled by the subclass constructor with PAGE_SIZE + 1 rows; the extra
    // one signals another page and is dropped in toDOM.
    /** @var User[] */
    public array $items = [];

    /**
     * Whether the list came back with anything - lets a page hide a whole
     * section (an empty pending/sent-requests list) rather than show its
     * heading over nothing.
     */
    public function hasItems(): bool
    {
        return $this -> items !== [];
    }

    public function toDOM(): \DOMElement
    {
        $has_more = count($this -> items) > static::PAGE_SIZE;

        if ($has_more) {
            array_pop($this -> items);
        }

        $this -> attributes['data-list-type'] = $this -> listType;
        $this -> attributes['data-user-id'] = (string) $this -> user -> userId;
        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        if ($this -> items !== []) {
            $this -> attributes['data-oldest-friendship-id'] = (string) $this -> items[count($this -> items) - 1] -> friendshipId;
        }

        // A titled section: the H2 heading, then the users as their own <ul>.
        $heading = new Heading2();
        $heading -> contents[] = $this -> heading;
        $this -> contents[] = $heading;

        $items = new ItemList();
        $items -> class = 'UserItems';

        if ($this -> items === []) {
            $items -> contents[] = new Notice($this -> emptyMessage);
        } else {
            $items -> addContents($this -> items);
        }

        $this -> contents[] = $items;

        return parent::toDOM();
    }
}
