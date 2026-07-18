<?php

declare(strict_types=1);

/**
 * The Banned Users page's results area: 20 banned accounts at a time (newest
 * accounts first), grown by infinite scroll in main.js off the data-*
 * attributes here - the next page is fetched by offset, how many accounts
 * are already shown. The search box (BannedUserSearch) repopulates this same
 * container with matches.
 */
class BannedUserList extends ItemList
{
    public const PAGE_SIZE = 20;

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
        }

        return parent::toDOM();
    }

    /**
     * @return BannedUser[]
     */
    public static function fetch(int $limit, int $offset = 0): array
    {
        $banned = 1;

        return DB::rows('
SELECT *
    FROM `Users`
    WHERE `banned` = ?
    ORDER BY `userId` DESC
    LIMIT ? OFFSET ?
', 'BannedUser', 'iii', $banned, $limit, $offset);
    }
}
