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
    public int $isMod = 0;
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

    /**
     * The avatar + display name + username block used wherever a message,
     * post, or similar item needs to show who it's from - one clickable
     * link to their profile.
     */
    public function header(bool $small = false): HTMLObject
    {
        $name = $this -> displayName ?? $this -> username;

        $header = new Anchor(URL::absolute('/users/' . $this -> username . '/'));
        $header -> class = 'd-flex align-items-center gap-3';

        $header -> addContents(Avatar::forUser($this, $small));

        $info = new Div();

        $name_line = new Div();
        $name_line -> class = 'fw-semibold';
        $name_line -> contents[] = $name;
        $info -> addContents($name_line);

        $username_line = new Div();
        $username_line -> class = 'Muted text-sm';
        $username_line -> contents[] = '@' . $this -> username;
        $info -> addContents($username_line);

        $header -> addContents($info);

        return $header;
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

    /**
     * This user's accepted friends.
     *
     * @return Friend[]
     */
    public function getFriends(): array
    {
        $accepted_status = 'accepted';

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = IF(`f`.`requesterId` = ?, `f`.`addresseeId`, `f`.`requesterId`)
    WHERE `f`.`status` = ? AND (`f`.`requesterId` = ? OR `f`.`addresseeId` = ?)
');
        mysqli_stmt_bind_param($stmt, 'isii', $this -> userId, $accepted_status, $this -> userId, $this -> userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $friends = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $friends[] = Friend::fromRow($row);
        }

        return $friends;
    }

    /**
     * Pending friend requests sent to this user, awaiting their response.
     *
     * @return FriendRequest[]
     */
    public function getIncomingFriendRequests(): array
    {
        $pending_status = 'pending';

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ?
');
        mysqli_stmt_bind_param($stmt, 'is', $this -> userId, $pending_status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $requests = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = FriendRequest::fromRow($row);
        }

        return $requests;
    }

    /**
     * Pending friend requests this user sent, awaiting the other person's response.
     *
     * @return OtherUser[]
     */
    public function getOutgoingFriendRequests(): array
    {
        $pending_status = 'pending';

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ?
');
        mysqli_stmt_bind_param($stmt, 'is', $this -> userId, $pending_status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $requests = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = OtherUser::fromRow($row);
        }

        return $requests;
    }

    /**
     * Suggested accounts for the empty-query state of user search: friends
     * of this user's friends, ranked by how many mutual friends they share -
     * excluding this user, their existing friends, banned accounts, and
     * anyone blocked in either direction. Falls back to some random
     * accounts if this user has no friends yet (or no FOAF candidates turn
     * up), so the list is never empty.
     *
     * @return OtherUser[]
     */
    public function getSuggestedUsers(int $limit = 20): array
    {
        $friend_ids = array_map(fn ($friend) => (int) $friend -> userId, $this -> getFriends());

        $mutual_counts = [];

        foreach ($friend_ids as $friend_id) {
            $friend = self::load($friend_id);

            if ($friend === null) {
                continue;
            }

            foreach ($friend -> getFriends() as $candidate) {
                $candidate_id = (int) $candidate -> userId;

                if ($candidate_id === (int) $this -> userId || in_array($candidate_id, $friend_ids, true)) {
                    continue;
                }

                $mutual_counts[$candidate_id] = ($mutual_counts[$candidate_id] ?? 0) + 1;
            }
        }

        if ($mutual_counts === []) {
            return $this -> getRandomUsers($limit);
        }

        arsort($mutual_counts);
        $eligible = self::eligibleSuggestions(array_keys($mutual_counts), (int) $this -> userId);

        $suggestions = [];

        foreach (array_keys($mutual_counts) as $candidate_id) {
            if (count($suggestions) >= $limit) {
                break;
            }

            if (isset($eligible[$candidate_id])) {
                $suggestions[] = $eligible[$candidate_id];
            }
        }

        return $suggestions !== [] ? $suggestions : $this -> getRandomUsers($limit);
    }

    /**
     * @param int[] $user_ids
     * @return array<int, OtherUser> userId => OtherUser, limited to those among
     *                                $user_ids who are not banned and not
     *                                blocked in either direction with $viewer_id
     */
    private static function eligibleSuggestions(array $user_ids, int $viewer_id): array
    {
        if ($user_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($user_ids), '?'));
        $not_banned = 0;

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `u`.*
    FROM `Users` `u`
    WHERE `u`.`userId` IN (' . $placeholders . ') AND `u`.`banned` = ?
        AND NOT EXISTS (
            SELECT 1
                FROM `Blocks` `b`
                WHERE (`b`.`blockerId` = ? AND `b`.`blockedId` = `u`.`userId`) OR (`b`.`blockerId` = `u`.`userId` AND `b`.`blockedId` = ?)
        )
');
        $params = array_merge($user_ids, [$not_banned, $viewer_id, $viewer_id]);
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($user_ids)) . 'iii', ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $users = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $users[(int) $row['userId']] = OtherUser::fromRow($row);
        }

        return $users;
    }

    /**
     * @return OtherUser[]
     */
    public function getRandomUsers(int $limit = 20): array
    {
        $not_banned = 0;

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `u`.*
    FROM `Users` `u`
    WHERE `u`.`userId` != ? AND `u`.`banned` = ?
        AND NOT EXISTS (
            SELECT 1
                FROM `Blocks` `b`
                WHERE (`b`.`blockerId` = ? AND `b`.`blockedId` = `u`.`userId`) OR (`b`.`blockerId` = `u`.`userId` AND `b`.`blockedId` = ?)
        )
    ORDER BY RAND()
    LIMIT ?
');
        mysqli_stmt_bind_param($stmt, 'iiiii', $this -> userId, $not_banned, $this -> userId, $this -> userId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $users = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = OtherUser::fromRow($row);
        }

        return $users;
    }
}
