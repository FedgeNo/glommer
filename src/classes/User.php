<?php

declare(strict_types=1);

class User extends Div implements \JsonSerializable
{
    /** Most friends we ever load/show for one person (the friends-list cap). */
    public const MAX_FRIENDS = 5000;

    /**
     * Longest username a person on this site can have, however their account
     * was created. Well under Users.slug's width, which is sized to hold a
     * followed Fediverse account's whole handle - that room is for remote
     * handles, not for longer local names.
     */
    public const MAX_USERNAME_LENGTH = 32;

    /**
     * Reduces whatever was typed to the username that would actually be
     * stored: lowercase, only letters/digits/underscore, capped. Disallowed
     * characters are dropped, so a name never contains a character the person
     * didn't type.
     *
     * The one place this is decided, so sign-up and the availability check
     * can't disagree about what a given input means.
     */
    public static function normaliseUsername(string $raw): string
    {
        return substr((string) preg_replace('/[^a-z0-9_]/', '', strtolower(trim($raw))), 0, self::MAX_USERNAME_LENGTH);
    }

    public ?string $class = 'User';

    public ?int $userId = null;
    public ?string $slug = null;
    public ?string $email = null;
    /**
     * Private so a hash can only ever be checked or replaced, never read out
     * of a User and copied somewhere it could be cracked offline. mysqli's
     * object hydration fills it regardless of visibility, so loading a user
     * still works.
     */
    private ?string $passwordHash = null;
    public ?string $title = null;
    public ?string $description = null;
    public int $hasAvatar = 0;
    public ?string $createdAt = null;
    public int $banned = 0;
    public ?string $banReason = null;
    public int $isMod = 0;
    public int $verified = 0;
    public int $twoFactorEnabled = 0;
    public string $theme = 'system';
    public ?string $skinTone = null;
    public int $showSensitiveMedia = 0;
    public int $welcomeDismissed = 0;
    public ?string $lastSeen = null;
    public int $emailDigests = 1;
    public ?string $emailDigestSent = null;
    public int $lastNotificationId = 0;
    public int $lastMessageId = 0;
    public int $friendCount = 0;
    public int $sessionVersion = 0;
    public ?string $remoteActorURI = null;
    public ?string $remoteActorPublicKeyPem = null;

    /**
     * Who this user currently has a message thread open with, and when they
     * were last known to still be looking at it. A person can only read one
     * thread at a time, so this lives on the user rather than in a table of its
     * own. Presence is only ever "recent enough" - see ChatPresence.
     */
    public ?int $chatOtherUserId = null;
    public ?string $chatLastSeen = null;

    public function verifyPassword(string $password): bool
    {
        return $this -> passwordHash !== null && password_verify($password, $this -> passwordHash);
    }

    public function passwordNeedsRehash(): bool
    {
        return $this -> passwordHash !== null && password_needs_rehash($this -> passwordHash, PASSWORD_DEFAULT);
    }

    /**
     * Keeps a loaded User in step with a hash the caller has already written
     * to the row.
     */
    public function setPasswordHash(string $hash): void
    {
        $this -> passwordHash = $hash;
    }

    /**
     * What a User is when it's encoded as JSON. Named explicitly rather than
     * left to json_encode's default, which would publish every public property
     * - including the password hash, the email, the session version and the
     * signing key columns - to anything that ever encodes one.
     */
    public function jsonSerialize(): array
    {
        return [
            'userId' => (int) $this -> userId,
            'slug' => $this -> slug,
            'title' => $this -> title,
            'description' => $this -> description,
            'image' => $this -> avatarURL(),
            'createdAt' => $this -> createdAt,
            'isMod' => (bool) $this -> isMod,
        ];
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> slug !== null) {
            $this -> attributes['data-username'] = $this -> slug;
        }

        // The identity block and the bio stack in a growing left column, so the
        // bio runs the full width beneath the avatar/name up to whatever sits on
        // the card's right (the action buttons).
        $main = new UserMain();
        $main -> addContent($this -> identityElement());

        $bio = $this -> bioElement();

        if ($bio !== null) {
            $main -> addContent($bio);
        }

        $this -> contents[] = $main;

        return parent::toDOM();
    }

    /**
     * The identity block - avatar, name, @username, joined date - as one link to
     * the profile (same shape as header(), just the fuller card). CurrentUser
     * overrides this so the name can be edited in place rather than link out.
     */
    protected function identityElement(): HTMLObject
    {
        $link = new UserLink(ServerURL::absolute('/users/' . $this -> slug . '/'));

        $link -> addContent(Avatar::forUser($this));
        $link -> addContent($this -> identityInfo());

        return $link;
    }

    /**
     * The name/username/joined column beside the avatar. Shared with CurrentUser
     * (which only differs in the name element), so those three lines build once.
     */
    protected function identityInfo(): HTMLObject
    {
        $info = new UserIdentity();

        $info -> addContent($this -> nameElement());

        $username_line = new Div();
        $username_line -> mixins = ['muted', 'text-sm'];
        $username_line -> contents[] = '@' . $this -> slug;
        $info -> addContent($username_line);

        if ($this -> createdAt !== null) {
            $joined = new Div();
            $joined -> mixins = ['muted', 'text-sm'];
            $joined -> contents[] = 'Joined ' . date('F j, Y', strtotime($this -> createdAt));
            $info -> addContent($joined);
        }

        return $info;
    }

    /** The display-name heading. CurrentUser pairs it with the edit pencil. */
    protected function nameElement(): HTMLObject
    {
        $name_heading = new DisplayName();
        $name_heading -> contents[] = $this -> title ?: $this -> slug;

        return $name_heading;
    }

    /** The bio beneath the identity, or null when there's none to show. */
    protected function bioElement(): ?HTMLObject
    {
        if ($this -> description === null || trim($this -> description) === '') {
            return null;
        }

        return new UserBio($this);
    }

    /**
     * The avatar + display name + username block used wherever a message,
     * post, or similar item needs to show who it's from - one clickable
     * link to their profile.
     */
    public function header(): HTMLObject
    {
        $name = $this -> title ?: $this -> slug;

        $header = new Anchor(ServerURL::absolute('/users/' . $this -> slug . '/'));
        $header -> class = 'UserHeader';
        $header -> mixins = ['d-flex', 'align-items-center', 'gap-3'];

        $header -> addContent(Avatar::forUser($this));

        $info = new UserHeaderInfo();

        $name_line = new UserHeaderName();
        $name_line -> mixins = ['fw-semibold'];
        $name_line -> contents[] = $name;
        $info -> addContent($name_line);

        $username_line = new Div();
        $username_line -> mixins = ['muted', 'text-sm'];
        $username_line -> contents[] = '@' . $this -> slug;
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
        $path = '/uploads/avatars/' . UploadProcessor::shard($user_id) . '/' . $user_id . '-thumb.jpg';
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

        $rows = DB::rows('
SELECT *
    FROM `Users`
    WHERE `userId` IN (' . $placeholders . ')
', 'User', str_repeat('i', count($user_ids)), ...$user_ids);

        $users = [];

        foreach ($rows as $user) {
            $users[(int) $user -> userId] = $user;
        }

        return $users;
    }

    public static function loadByUsername(string $username): ?self
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `slug` = ?
', 'User', 's', $username);
    }

    /**
     * A user looked up by username for public profile display, as an
     * OtherUser - or null if there's no such user or they're banned (a banned
     * profile is a 404 to everyone). The single shared "load + banned gate"
     * behind the profile page, its friends page, and its RSS feed, so that
     * visibility rule lives in one place instead of being hand-copied.
     */
    /**
     * The local shadow row standing in for a remote account, by its actor URI
     * - or null when this server has never met them. The one lookup behind
     * every place federation turns an actor URI into the Users row it acts as,
     * which used to be the same query hand-copied across the inbox, the
     * follow machinery and the actor cache.
     */
    public static function byRemoteActorURI(string $actor_uri): ?User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `remoteActorURI` = ?
', self::class, 's', $actor_uri);
    }

    public static function byUsername(string $username): ?OtherUser
    {
        $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `slug` = ?
', 'OtherUser', 's', $username);

        if (!$user instanceof OtherUser || $user -> banned) {
            return null;
        }

        return $user;
    }

    /**
     * The user ids of this user's accepted friends - just the ids, for the
     * suggestion engine's mutual-friend counting. The friends themselves, for
     * display, are FriendList's concern; each friendship stores one direction,
     * so the id wanted is whichever end of the pair isn't this user.
     *
     * @return int[]
     */
    public function friendIds(): array
    {
        $accepted_status = 'accepted';

        $result = mysqli_stmt_get_result(DB::run('
SELECT IF(`requesterId` = ?, `addresseeId`, `requesterId`) AS `friendId`
    FROM `Friendships`
    WHERE `status` = ? AND (`requesterId` = ? OR `addresseeId` = ?)
', 'isii', $this -> userId, $accepted_status, $this -> userId, $this -> userId));

        $ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $ids[] = (int) $row['friendId'];
        }

        return $ids;
    }

    /**
     * The label on a profile's Friends button, carrying how many they have.
     *
     * Asked only of a member: friendship is a relationship held on this
     * server, so a Fediverse account is offered no Friends button at all
     * rather than one leading to an empty list (see OtherUser).
     *
     * Reads the maintained friendCount cache, the same figure the friend cap is
     * enforced against, rather than counting Friendships for a label.
     */
    public function friendsButtonLabel(): string
    {
        return 'View Friends (' . $this -> friendCount . ')';
    }

    /**
     * How stale the stored mark may be before it is worth writing again.
     *
     * Every request from somebody signed in could write one, and on a page
     * with any reading in it that is a write per click for a figure nobody
     * needs to the second. Five minutes is finer than any question asked of
     * it - who was here today, who this week - and turns a long read into a
     * single write.
     */
    private const SEEN_AGAIN_AFTER_SECONDS = 300;

    /**
     * Notes that this member is here now, if it has been long enough since
     * the last time.
     *
     * Called from init.php on every request with somebody behind it. Whether
     * the mark is stale is judged against the row already loaded, so a
     * request that writes nothing also reads nothing extra.
     */
    public static function seen(self $user): void
    {
        if ($user -> lastSeen !== null && strtotime($user -> lastSeen) > time() - self::SEEN_AGAIN_AFTER_SECONDS) {
            return;
        }

        DB::run('
UPDATE `Users`
    SET `lastSeen` = NOW()
    WHERE `userId` = ?
', 'i', (int) $user -> userId);

        $user -> lastSeen = date('Y-m-d H:i:s');
    }

    /** How many members have been here within the last $days. */
    public static function activeSince(int $days): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Users`
    WHERE `remoteActorURI` IS NULL AND `lastSeen` >= NOW() - INTERVAL ? DAY
', 'PostCountData', 'i', $days);

        return $row === null ? 0 : (int) $row -> total;
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
        $user = DB::row('
SELECT `friendCount`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);

        return ($user ?-> friendCount ?? 0) >= self::MAX_FRIENDS;
    }

    /**
     * Rewrites the friendCount cache from the actual accepted friendships (the
     * source of truth). Called on sign-in so any drift - e.g. a friend deleted
     * by a path that missed the decrement - is corrected.
     */
    public static function recomputeFriendCount(int $user_id): void
    {
        $accepted_status = 'accepted';

        DB::run('
UPDATE `Users`
    SET `friendCount` = (
        SELECT COUNT(*)
            FROM `Friendships`
            WHERE `status` = ? AND (`requesterId` = ? OR `addresseeId` = ?)
    )
    WHERE `userId` = ?
', 'siii', $accepted_status, $user_id, $user_id, $user_id);
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
        DB::run('
UPDATE `Users`
    SET `sessionVersion` = `sessionVersion` + 1
    WHERE `userId` = ?
', 'i', $user_id);

        $user = DB::row('
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);

        return $user ?-> sessionVersion ?? 0;
    }

    /**
     * Bumps both users' friendCount cache by one - call right after a
     * friendship becomes accepted.
     */
    public static function incrementFriendCounts(int $user_a, int $user_b): void
    {
        DB::run('
UPDATE `Users`
    SET `friendCount` = `friendCount` + 1
    WHERE `userId` = ? OR `userId` = ?
', 'ii', $user_a, $user_b);
    }

    /**
     * Drops both users' friendCount cache by one (never below zero) - call
     * right after an accepted friendship is removed.
     */
    public static function decrementFriendCounts(int $user_a, int $user_b): void
    {
        DB::run('
UPDATE `Users`
    SET `friendCount` = `friendCount` - 1
    WHERE (`userId` = ? OR `userId` = ?) AND `friendCount` > 0
', 'ii', $user_a, $user_b);
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
        // Retired before the row goes, since the name is read off it. A local
        // member only: a shadow row's slug is a remote handle this server does
        // not own and has no business reserving.
        $account = DB::row('
SELECT `slug`, `remoteActorURI`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);

        if ($account !== null && $account -> remoteActorURI === null) {
            RetiredUsername::retire((string) $account -> slug);
        }

        // Every post this user authored, plus (via the parentId cascade)
        // every reply nested under them - the same graph-walk Post::delete()
        // uses, just seeded from every post this user owns instead of one.
        $own_posts_stmt = DB::run('
SELECT `postId`
    FROM `Posts`
    WHERE `userId` = ?
', 'i', $user_id);
        $own_posts_result = mysqli_stmt_get_result($own_posts_stmt);

        $all_post_ids = [];
        $frontier = [];

        while ($row = mysqli_fetch_assoc($own_posts_result)) {
            $all_post_ids[] = (int) $row['postId'];
            $frontier[] = (int) $row['postId'];
        }

        while ($frontier !== []) {
            $placeholders = implode(', ', array_fill(0, count($frontier), '?'));

            $children_stmt = DB::run('
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
', str_repeat('i', count($frontier)), ...$frontier);
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
            DB::run('
DELETE
    FROM `' . $table . '`
    WHERE `userId` = ?
', 'i', $user_id);
        }

        // Notifications.postId carries no FK (same reasoning as
        // Post::delete()'s own cleanup) - and this user's userId/actorId
        // cascade only clears notifications addressed to or generated by
        // them, not a third party's notification about a reply nested under
        // one of this user's now-deleted posts. Without this it would be
        // left pointing at a 404'ing permalink forever.
        if ($all_post_ids !== []) {
            $post_id_placeholders = implode(', ', array_fill(0, count($all_post_ids), '?'));

            DB::run('
DELETE
    FROM `Notifications`
    WHERE `postId` IN (' . $post_id_placeholders . ')
', str_repeat('i', count($all_post_ids)), ...$all_post_ids);
        }

        DB::run('
DELETE
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id);

        // Only remove files once the rows are actually gone.
        foreach ($doomed_items as $item) {
            UploadProcessor::deleteForItem((int) $item -> itemId, (string) $item -> type);
        }

        $avatar_dir = __DIR__ . '/../../uploads/avatars/' . UploadProcessor::shard($user_id);

        foreach ([$user_id . '.jpg', $user_id . '-thumb.jpg'] as $filename) {
            $path = $avatar_dir . '/' . $filename;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
