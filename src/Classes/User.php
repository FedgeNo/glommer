<?php

declare(strict_types=1);

class User extends HTMLObject
{
    /** Most friends we ever load/show for one person (the friends-list cap). */
    public const MAX_FRIENDS = 5000;

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
    public int $friendCount = 0;
    public int $sessionVersion = 0;

    public function toDOM(): \DOMElement
    {
        $name = $this -> displayName ?? $this -> username;

        if ($this -> username !== null) {
            $this -> attributes['data-username'] = $this -> username;
        }

        // The whole identity block - avatar, name, username, and joined date -
        // is one link to the profile (same as header(), just the fuller card).
        $link = new Anchor(ServerURL::absolute('/users/' . $this -> username . '/'));
        $link -> class = 'UserLink';

        $link -> addContent(Avatar::forUser($this));

        $info = new Div();

        $name_heading = new Heading2();
        $name_heading -> contents[] = $name;
        $info -> addContent($name_heading);

        $username_line = new Div();
        $username_line -> class = 'Muted text-sm';
        $username_line -> contents[] = '@' . $this -> username;
        $info -> addContent($username_line);

        if ($this -> createdAt !== null) {
            $joined = new Div();
            $joined -> class = 'Muted text-sm';
            $joined -> contents[] = 'Joined ' . date('F j, Y', strtotime($this -> createdAt));
            $info -> addContent($joined);
        }

        $link -> addContent($info);

        $this -> contents[] = $link;

        return parent::toDOM();
    }

    /**
     * The avatar + display name + username block used wherever a message,
     * post, or similar item needs to show who it's from - one clickable
     * link to their profile.
     */
    public function header(): HTMLObject
    {
        $name = $this -> displayName ?? $this -> username;

        $header = new Anchor(ServerURL::absolute('/users/' . $this -> username . '/'));
        $header -> class = 'd-flex align-items-center gap-3';

        $header -> addContent(Avatar::forUser($this));

        $info = new Div();

        $name_line = new Div();
        $name_line -> class = 'fw-semibold';
        $name_line -> contents[] = $name;
        $info -> addContent($name_line);

        $username_line = new Div();
        $username_line -> class = 'Muted text-sm';
        $username_line -> contents[] = '@' . $this -> username;
        $info -> addContent($username_line);

        $header -> addContent($info);

        return $header;
    }

    /**
     * Versioned with the actual file's mtime (not e.g. a DB column) so a
     * re-uploaded avatar - saved in place at this same unchanged path -
     * actually busts any browser/CDN cache of the old image instead of
     * serving it stale until a hard refresh.
     */
    public static function avatarPath(int $user_id): string
    {
        $path = '/uploads/avatars/' . $user_id . '-thumb.jpg';
        $mtime = @filemtime(__DIR__ . '/../..' . $path);

        return $mtime !== false ? $path . '?v=' . $mtime : $path;
    }

    public function avatarURL(): ?string
    {
        return $this -> hasAvatar ? ServerURL::absolute(self::avatarPath((int) $this -> userId)) : null;
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
     * A user looked up by username for public profile display, as an
     * OtherUser - or null if there's no such user or they're banned (a banned
     * profile is a 404 to everyone). The single shared "load + banned gate"
     * behind the profile page, its friends page, and its RSS feed, so that
     * visibility rule lives in one place instead of being hand-copied.
     */
    public static function byUsername(string $username): ?OtherUser
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `Users`
    WHERE `username` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_object(mysqli_stmt_get_result($stmt), OtherUser::class);

        if (!$user instanceof OtherUser || $user -> banned) {
            return null;
        }

        return $user;
    }

    /**
     * This user's accepted friends, newest friendship first, excluding banned
     * accounts. Paginate by passing the friendshipId of the last friend
     * already seen as $before_friendship_id (the list is cursored on the
     * Friendships row id).
     *
     * @return Friend[]
     */
    public function getFriends(int $limit = self::MAX_FRIENDS, ?int $before_friendship_id = null): array
    {
        $accepted_status = 'accepted';
        $not_banned = 0;
        $mysqli = Database::connection();

        // No cursor means "from the newest" - a sentinel above any real
        // friendshipId keeps it one query instead of a cursorless duplicate.
        $cursor = $before_friendship_id ?? PHP_INT_MAX;

        // The two directions run as separate UNION ALL halves rather than one
        // OR: each half walks its (requesterId|addresseeId, status,
        // friendshipId) index backward and stops at the limit, so only the
        // merged 2x-limit rows ever get sorted - an OR index_merge forces
        // collecting and filesorting every accepted friendship first. The
        // banned filter stays inside each half so pagination semantics match
        // the old query exactly.
        $stmt = mysqli_prepare($mysqli, '
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
UNION ALL
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
    ORDER BY `friendshipId` DESC
    LIMIT ?
');
        mysqli_stmt_bind_param(
            $stmt,
            'isiiiisiiii',
            $this -> userId,
            $accepted_status,
            $not_banned,
            $cursor,
            $limit,
            $this -> userId,
            $accepted_status,
            $not_banned,
            $cursor,
            $limit,
            $limit
        );

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $friends = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $friends[] = Friend::fromRow($row);
        }

        return $friends;
    }

    /**
     * Pending friend requests sent to this user, awaiting their response,
     * newest first, excluding banned requesters. Cursored on friendshipId.
     *
     * @return FriendRequest[]
     */
    public function getIncomingFriendRequests(int $limit = self::MAX_FRIENDS, ?int $before_friendship_id = null): array
    {
        $pending_status = 'pending';
        $not_banned = 0;
        $mysqli = Database::connection();

        if ($before_friendship_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'isiii', $this -> userId, $pending_status, $not_banned, $before_friendship_id, $limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'isii', $this -> userId, $pending_status, $not_banned, $limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $requests = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = FriendRequest::fromRow($row);
        }

        return $requests;
    }

    /**
     * Pending friend requests this user sent, awaiting the other person's
     * response, newest first, excluding banned addressees. Cursored on
     * friendshipId.
     *
     * @return SentFriendRequest[]
     */
    public function getOutgoingFriendRequests(int $limit = self::MAX_FRIENDS, ?int $before_friendship_id = null): array
    {
        $pending_status = 'pending';
        $not_banned = 0;
        $mysqli = Database::connection();

        if ($before_friendship_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'isiii', $this -> userId, $pending_status, $not_banned, $before_friendship_id, $limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'isii', $this -> userId, $pending_status, $not_banned, $limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $requests = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = SentFriendRequest::fromRow($row);
        }

        return $requests;
    }

    /**
     * True if this user is already at the friend cap and so can't gain another
     * friend - reads the maintained friendCount cache rather than counting
     * Friendships every time. The cache is kept in step by
     * increment/decrementFriendCounts on every add/remove and healed by
     * recomputeFriendCount on sign-in.
     */
    public static function atFriendCap(int $user_id): bool
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `friendCount`
    FROM `Users`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return (int) ($row['friendCount'] ?? 0) >= self::MAX_FRIENDS;
    }

    /**
     * Rewrites the friendCount cache from the actual accepted friendships (the
     * source of truth). Called on sign-in so any drift - e.g. a friend deleted
     * by a path that missed the decrement - is corrected.
     */
    public static function recomputeFriendCount(int $user_id): void
    {
        $accepted_status = 'accepted';

        $stmt = mysqli_prepare(Database::connection(), '
UPDATE `Users`
    SET `friendCount` = (
        SELECT COUNT(*)
            FROM `Friendships`
            WHERE `status` = ? AND (`requesterId` = ? OR `addresseeId` = ?)
    )
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'siii', $accepted_status, $user_id, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
    }

    /**
     * Invalidates every existing session for the user by bumping their
     * sessionVersion - a session records the version it was created under and
     * init.php logs out any session whose recorded version no longer matches.
     * Called on password change/reset so a stolen or forgotten-open session
     * doesn't outlive the credentials that created it. Returns the new
     * version so the calling session can adopt it and stay logged in.
     */
    public static function bumpSessionVersion(int $user_id): int
    {
        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `sessionVersion` = `sessionVersion` + 1
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);

        $select_stmt = mysqli_prepare($mysqli, '
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($select_stmt, 'i', $user_id);
        mysqli_stmt_execute($select_stmt);
        $result = mysqli_stmt_get_result($select_stmt);

        return (int) (mysqli_fetch_assoc($result)['sessionVersion'] ?? 0);
    }

    /**
     * Bumps both users' friendCount cache by one - call right after a
     * friendship becomes accepted.
     */
    public static function incrementFriendCounts(int $user_a, int $user_b): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
UPDATE `Users`
    SET `friendCount` = `friendCount` + 1
    WHERE `userId` = ? OR `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $user_a, $user_b);
        mysqli_stmt_execute($stmt);
    }

    /**
     * Drops both users' friendCount cache by one (never below zero) - call
     * right after an accepted friendship is removed.
     */
    public static function decrementFriendCounts(int $user_a, int $user_b): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
UPDATE `Users`
    SET `friendCount` = `friendCount` - 1
    WHERE (`userId` = ? OR `userId` = ?) AND `friendCount` > 0
');
        mysqli_stmt_bind_param($stmt, 'ii', $user_a, $user_b);
        mysqli_stmt_execute($stmt);
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

        $mutual_counts = self::mutualFriendCounts($friend_ids, (int) $this -> userId);

        if ($mutual_counts === []) {
            return $this -> getRandomUsers($limit);
        }

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
     * Friends-of-friends, ranked by how many of $friend_ids they're accepted-
     * friends with - one query in each direction rather than a load()+
     * getFriends() round trip per friend, so cost no longer scales with how
     * many friends $viewer_id has.
     *
     * @param int[] $friend_ids
     * @return array<int, int> candidate userId => mutual friend count, ordered highest first
     */
    private static function mutualFriendCounts(array $friend_ids, int $viewer_id): array
    {
        if ($friend_ids === []) {
            return [];
        }

        $accepted_status = 'accepted';
        $not_banned = 0;
        $excluded_ids = array_merge($friend_ids, [$viewer_id]);

        // getSuggestedUsers() only ever takes the top 20 of these after
        // ranking - capped well above that (not at 20 itself) so eligibility
        // filtering (banned/blocked) still has plenty of ranked candidates
        // to fall back through. Without this, both the result set and the
        // bound-parameter count in eligibleSuggestions()'s IN(...) scale
        // with a well-connected user's entire two-hop neighborhood.
        $candidate_limit = 200;

        $friend_placeholders = implode(', ', array_fill(0, count($friend_ids), '?'));
        $excluded_placeholders = implode(', ', array_fill(0, count($excluded_ids), '?'));

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `candidateId`, COUNT(*) AS `mutualCount`
    FROM (
        SELECT `f`.`addresseeId` AS `candidateId`
            FROM `Friendships` `f`
            JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
            WHERE `f`.`status` = ? AND `u`.`banned` = ?
                AND `f`.`requesterId` IN (' . $friend_placeholders . ') AND `f`.`addresseeId` NOT IN (' . $excluded_placeholders . ')
        UNION ALL
        SELECT `f`.`requesterId` AS `candidateId`
            FROM `Friendships` `f`
            JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
            WHERE `f`.`status` = ? AND `u`.`banned` = ?
                AND `f`.`addresseeId` IN (' . $friend_placeholders . ') AND `f`.`requesterId` NOT IN (' . $excluded_placeholders . ')
    ) `candidates`
    GROUP BY `candidateId`
    ORDER BY `mutualCount` DESC
    LIMIT ?
');
        $params = array_merge(
            [$accepted_status, $not_banned],
            $friend_ids,
            $excluded_ids,
            [$accepted_status, $not_banned],
            $friend_ids,
            $excluded_ids,
            [$candidate_limit]
        );
        $types = 'si' . str_repeat('i', count($friend_ids)) . str_repeat('i', count($excluded_ids))
            . 'si' . str_repeat('i', count($friend_ids)) . str_repeat('i', count($excluded_ids)) . 'i';
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $mutual_counts = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $mutual_counts[(int) $row['candidateId']] = (int) $row['mutualCount'];
        }

        return $mutual_counts;
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

    /**
     * Permanently deletes an account and everything tied to it. Likes,
     * friendships, blocks, messages, notifications, timeline entries, and
     * remember-me tokens all cascade via their own FK (ON DELETE CASCADE).
     * Posts cascade too (Posts.userId, same as every other Users-referencing
     * FK) - which, via Posts' own parentId cascade, takes every reply nested
     * under them with it, regardless of who wrote the reply, same as a
     * single Post::delete() already does. The row cascade can't touch the
     * filesystem, so this collects every doomed post's media items (and the
     * account's own avatar) before deleting the row, and removes the actual
     * files only once the rows are confirmed gone.
     *
     * EmailVerifications/PasswordResets/EmailChangeReverts carry no FK
     * (they're short-lived tokens, not content) so they're pruned
     * explicitly rather than left to expire into irrelevance.
     *
     * Caller is responsible for the authorization check (see
     * api/delete-account.php) - this performs no checks of its own, same as
     * Post::delete().
     */
    public static function delete(int $user_id): void
    {
        $mysqli = Database::connection();

        // Every post this user authored, plus (via the parentId cascade)
        // every reply nested under them - the same graph-walk Post::delete()
        // uses, just seeded from every post this user owns instead of one.
        $own_posts_stmt = mysqli_prepare($mysqli, '
SELECT `postId`
    FROM `Posts`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($own_posts_stmt, 'i', $user_id);
        mysqli_stmt_execute($own_posts_stmt);
        $own_posts_result = mysqli_stmt_get_result($own_posts_stmt);

        $all_post_ids = [];
        $frontier = [];

        while ($row = mysqli_fetch_assoc($own_posts_result)) {
            $all_post_ids[] = (int) $row['postId'];
            $frontier[] = (int) $row['postId'];
        }

        while ($frontier !== []) {
            $placeholders = implode(', ', array_fill(0, count($frontier), '?'));

            $children_stmt = mysqli_prepare($mysqli, '
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
');
            mysqli_stmt_bind_param($children_stmt, str_repeat('i', count($frontier)), ...$frontier);
            mysqli_stmt_execute($children_stmt);
            $children_result = mysqli_stmt_get_result($children_stmt);

            $frontier = [];

            while ($row = mysqli_fetch_assoc($children_result)) {
                $all_post_ids[] = (int) $row['postId'];
                $frontier[] = (int) $row['postId'];
            }
        }

        $doomed_items = [];

        foreach (FeedItem::itemsForPosts($all_post_ids) as $post_items) {
            foreach ($post_items as $item) {
                $doomed_items[] = $item;
            }
        }

        foreach (['EmailVerifications', 'PasswordResets', 'EmailChangeReverts'] as $table) {
            $prune_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `' . $table . '`
    WHERE `userId` = ?
');
            mysqli_stmt_bind_param($prune_stmt, 'i', $user_id);
            mysqli_stmt_execute($prune_stmt);
        }

        // Notifications.postId carries no FK (same reasoning as
        // Post::delete()'s own cleanup) - and this user's userId/actorId
        // cascade only clears notifications addressed to or generated by
        // them, not a third party's notification about a reply nested under
        // one of this user's now-deleted posts. Without this it would be
        // left pointing at a 404'ing permalink forever.
        if ($all_post_ids !== []) {
            $post_id_placeholders = implode(', ', array_fill(0, count($all_post_ids), '?'));

            $prune_notifications_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Notifications`
    WHERE `postId` IN (' . $post_id_placeholders . ')
');
            mysqli_stmt_bind_param($prune_notifications_stmt, str_repeat('i', count($all_post_ids)), ...$all_post_ids);
            mysqli_stmt_execute($prune_notifications_stmt);
        }

        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Users`
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 'i', $user_id);
        mysqli_stmt_execute($delete_stmt);

        // Only remove files once the rows are actually gone.
        foreach ($doomed_items as $item) {
            UploadProcessor::deleteForItem((int) $item -> itemId, (string) $item -> itemType);
        }

        $avatar_dir = __DIR__ . '/../../uploads/avatars';

        foreach ([$user_id . '.jpg', $user_id . '-thumb.jpg'] as $filename) {
            $path = $avatar_dir . '/' . $filename;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
