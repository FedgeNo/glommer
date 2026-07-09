<?php

declare(strict_types=1);

class Friendship extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Friendship';

    public ?int $friendshipId = null;
    public ?int $requesterId = null;
    public ?int $addresseeId = null;
    public ?string $status = null;
    public ?string $createdAt = null;

    /**
     * Returns the Friendship row between two users (in either direction), or null if none exists.
     */
    public static function statusBetween(int $user_a, int $user_b): ?self
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `Friendships`
    WHERE (`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?)
');
        mysqli_stmt_bind_param($stmt, 'iiii', $user_a, $user_b, $user_b, $user_a);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row === null ? null : self::fromRow($row);
    }

    public static function fromRow(array $row): self
    {
        $friendship = new self();

        foreach ($row as $key => $value) {
            $friendship -> $key = $value;
        }

        return $friendship;
    }
}
