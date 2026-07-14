<?php

declare(strict_types=1);

/**
 * A UserList that loads 20 at a time and grows on scroll, cursored on the
 * Friendships row id (friendshipId). Every subclass (friends, incoming
 * requests, outgoing requests) fetches the same way, so the scroll handler in
 * main.js can drive all of them generically off the data-* attributes and the
 * shared marker class this puts on the element. Each section on a page loads
 * populated with its first page, and the handler advances only the one the
 * reader has scrolled to, so two never load at once.
 */
abstract class PaginatedUserList extends UserList
{
    protected const PAGE_SIZE = 20;

    /** One of 'friends' | 'incoming' | 'outgoing' - which history to page. */
    protected string $listType = '';

    public ?int $userId = null;
    public ?int $oldestFriendshipId = null;
    public bool $hasMore = false;

    /**
     * Fetches up to $limit items for $user (newest first), each carrying a
     * friendshipId, optionally before a cursor.
     *
     * @return (Friend|FriendRequest|SentFriendRequest)[]
     */
    abstract protected static function fetch(User $user, int $limit, ?int $before_friendship_id): array;

    public static function forUser(User $user, ?int $before_friendship_id = null): static
    {
        $list = new static();
        $list -> userId = (int) $user -> userId;

        // One extra row tells us whether there's another page without a
        // separate count query (same trick the feed uses).
        $items = static::fetch($user, self::PAGE_SIZE + 1, $before_friendship_id);
        $list -> hasMore = count($items) > self::PAGE_SIZE;

        if ($list -> hasMore) {
            array_pop($items);
        }

        $list -> items = $items;

        if ($items !== []) {
            $list -> oldestFriendshipId = (int) end($items) -> friendshipId;
        }

        return $list;
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-list-type'] = $this -> listType;
        $this -> attributes['data-user-id'] = (string) $this -> userId;
        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        if ($this -> oldestFriendshipId !== null) {
            $this -> attributes['data-oldest-friendship-id'] = (string) $this -> oldestFriendshipId;
        }

        return parent::toDOM();
    }
}
