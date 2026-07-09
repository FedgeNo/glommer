<?php

declare(strict_types=1);

class User extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'User Card';

    public ?int $userId = null;
    public ?string $username = null;
    public ?string $email = null;
    public ?string $passwordHash = null;
    public ?string $displayName = null;
    public int $hasAvatar = 0;
    public ?string $createdAt = null;
    public int $banned = 0;
    public int $verified = 0;
    public string $theme = 'system';
    public ?string $skinTone = null;
    public int $lastNotificationId = 0;

    public function toDOM(): \DOMElement
    {
        $name = $this -> displayName ?? $this -> username;

        if ($this -> username !== null) {
            $this -> attributes['data-username'] = $this -> username;
        }

        $this -> contents[] = Avatar::forUser($this);

        $info = new Div();

        $name_heading = new Heading2();
        $name_heading -> addContents(new Anchor(URL::absolute('/users/' . $this -> username . '/'), $name));
        $info -> addContents($name_heading);

        $username_line = new Div();
        $username_line -> class = 'Muted text-sm';
        $username_line -> contents[] = '@' . $this -> username;
        $info -> addContents($username_line);

        if ($this -> createdAt !== null) {
            $joined = new Div();
            $joined -> class = 'Muted text-sm';
            $joined -> contents[] = 'Joined ' . date('F j, Y', strtotime($this -> createdAt));
            $info -> addContents($joined);
        }

        $this -> contents[] = $info;

        return parent::toDOM();
    }

    public static function avatarPath(int $user_id): string
    {
        return '/uploads/avatars/' . $user_id . '-thumb.jpg';
    }

    public function avatarURL(): ?string
    {
        return $this -> hasAvatar ? URL::absolute(self::avatarPath((int) $this -> userId)) : null;
    }

    public static function fromRow(array $row): static
    {
        $user = new static();

        foreach ($row as $key => $value) {
            $user -> $key = $value;
        }

        return $user;
    }

    public static function load(int $user_id): ?self
    {
        return self::loadMany([$user_id])[$user_id] ?? null;
    }

    /**
     * @param int[] $user_ids
     * @return array<int, self> userId => User
     */
    public static function loadMany(array $user_ids): array
    {
        if ($user_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($user_ids), '?'));

        $stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `Users`
    WHERE `userId` IN (' . $placeholders . ')
');
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($user_ids)), ...$user_ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $users = [];

        while (($user = mysqli_fetch_object($result, self::class)) !== null && $user !== false) {
            $users[(int) $user -> userId] = $user;
        }

        return $users;
    }

    public static function loadByUsername(string $username): ?self
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `Users`
    WHERE `username` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_object($result, User::class);

        return $user === false ? null : $user;
    }
}
