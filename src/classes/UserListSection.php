<?php

declare(strict_types=1);

/**
 * A list of users tied to one profile - friends, incoming requests, outgoing
 * requests. Each subclass fetches its own rows straight into $items in its
 * constructor (new FriendList(['user' => $profileUser])); this base paginates
 * them and hands the render off to ListSection. Grown on scroll, paginated by
 * offset (how many cards the section already shows), and the scroll handler
 * in main.js drives all three generically off the data-* attributes and the
 * shared .UserListSection marker.
 */
abstract class UserListSection extends ListSection
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'UserListSection';

    protected string $itemsClass = 'UserItems';

    /** One of 'friends' | 'incoming' | 'outgoing' - which history this pages. */
    protected string $listType = '';

    public ?User $user = null;
    public int $offset = 0;

    // Filled by the subclass constructor with PAGE_SIZE + 1 rows; the extra
    // one signals another page and is dropped in toDOM.
    /** @var User[] */
    public array $items = [];

    public function toDOM(): \DOMElement
    {
        $has_more = count($this -> items) > static::PAGE_SIZE;

        if ($has_more) {
            array_pop($this -> items);
        }

        $this -> attributes['data-list-type'] = $this -> listType;
        $this -> attributes['data-user-id'] = (string) $this -> user -> userId;
        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
