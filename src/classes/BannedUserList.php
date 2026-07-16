<?php

declare(strict_types=1);

/**
 * The Banned Users page's results area: 20 banned accounts at a time,
 * cursored on userId (newest accounts first) and grown by infinite scroll in
 * main.js off the data-* attributes here - the same shape UserList uses for
 * friends, with a userId cursor instead of a friendshipId one. The
 * search box (BannedUserSearch) repopulates this same container with matches.
 */
class BannedUserList extends HTMLObject
{
    public const PAGE_SIZE = 20;

    public string $tagName = 'div';
    public ?string $class = 'BannedUserList';

    public function toDOM(): \DOMElement
    {
        // Pull one extra row so a leftover signals another page without a
        // separate count query (same trick the feed uses); it's dropped back
        // off once it has told us there's more.
        $this -> addContents(self::fetch(self::PAGE_SIZE + 1));

        $has_more = count($this -> contents) > self::PAGE_SIZE;
        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        if ($has_more) {
            array_pop($this -> contents);
        }

        if ($this -> contents === []) {
            $this -> addContent(new Notice('No banned users.'));

            return parent::toDOM();
        }

        $this -> attributes['data-oldest-user-id'] = (string) $this -> contents[count($this -> contents) - 1] -> userId;

        return parent::toDOM();
    }

    /**
     * @return BannedUser[]
     */
    public static function fetch(int $limit, ?int $before_user_id = null): array
    {
        $banned = 1;

        if ($before_user_id !== null) {
            return DB::rows('
SELECT *
    FROM `Users`
    WHERE `banned` = ? AND `userId` < ?
    ORDER BY `userId` DESC
    LIMIT ?
', 'BannedUser', 'iii', $banned, $before_user_id, $limit);
        }

        return DB::rows('
SELECT *
    FROM `Users`
    WHERE `banned` = ?
    ORDER BY `userId` DESC
    LIMIT ?
', 'BannedUser', 'ii', $banned, $limit);
    }
}
