<?php

declare(strict_types=1);

/**
 * The Banned Users page's results area: 20 banned accounts at a time,
 * cursored on userId (newest accounts first) and grown by infinite scroll in
 * main.js off the data-* attributes here - the same shape PaginatedUserList
 * uses for friends, with a userId cursor instead of a friendshipId one. The
 * search box (BannedUserSearch) repopulates this same container with matches.
 */
class BannedUserList extends HTMLObject
{
    public const PAGE_SIZE = 20;

    public string $tagName = 'div';
    public ?string $class = 'BannedUserList';

    /** @var BannedUser[] */
    public array $items = [];
    public ?int $oldestUserId = null;
    public bool $hasMore = false;

    public static function page(?int $before_user_id = null): self
    {
        $list = new self();

        // One extra row tells us whether there's another page without a
        // separate count query (same trick the feed uses).
        $items = self::fetch(self::PAGE_SIZE + 1, $before_user_id);
        $list -> hasMore = count($items) > self::PAGE_SIZE;

        if ($list -> hasMore) {
            array_pop($items);
        }

        $list -> items = $items;

        if ($items !== []) {
            $list -> oldestUserId = (int) end($items) -> userId;
        }

        return $list;
    }

    /**
     * @return BannedUser[]
     */
    public static function fetch(int $limit, ?int $before_user_id = null): array
    {
        $mysqli = DB::connection();
        $banned = 1;

        if ($before_user_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `banned` = ? AND `userId` < ?
    ORDER BY `userId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iii', $banned, $before_user_id, $limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `banned` = ?
    ORDER BY `userId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'ii', $banned, $limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = BannedUser::fromRow($row);
        }

        return $items;
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        if ($this -> oldestUserId !== null) {
            $this -> attributes['data-oldest-user-id'] = (string) $this -> oldestUserId;
        }

        if ($this -> items === []) {
            $this -> contents[] = new Notice('No banned users.');
        } else {
            foreach ($this -> items as $item) {
                $this -> contents[] = $item;
            }
        }

        return parent::toDOM();
    }
}
