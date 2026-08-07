<?php

declare(strict_types=1);

class Notification extends Article
{
    public ?string $class = 'Notification';

    public ?int $notificationId = null;
    public ?int $userId = null;
    public ?int $actorId = null;
    public ?string $type = null;
    public ?int $postId = null;
    public ?string $createdAt = null;
    public ?string $actorUsername = null;
    public ?string $actorDisplayName = null;
    public ?int $actorHasAvatar = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> notificationId !== null) {
            $this -> attributes['data-notification-id'] = (string) $this -> notificationId;
        }

        $target = $this -> targetURL();

        // A notification links to its subject when it has one; a targetless one
        // (a system error, say) is a plain block, never a link to nowhere.
        $container = $target !== null ? new Anchor($target) : new Div();
        $container -> mixins = ['d-flex', 'align-items-center', 'gap-3'];

        $avatar_url = $this -> actorHasAvatar
            ? ServerURL::absolute(User::avatarPath((int) $this -> actorId))
            : null;

        $container -> addContent(Avatar::create((bool) $this -> actorHasAvatar, $avatar_url, $this -> actorName(), (int) $this -> actorId));

        $info = new Div();

        $text = new Div();
        $text -> contents[] = $this -> text();
        $info -> addContent($text);

        $meta = new RelativeTime($this -> createdAt);
        $meta -> mixins = ['muted', 'text-sm'];
        $info -> addContent($meta);

        $container -> addContent($info);

        $this -> contents[] = $container;

        return parent::toDOM();
    }

    protected function actorName(): string
    {
        return $this -> actorDisplayName ?? $this -> actorUsername;
    }

    protected function text(): string
    {
        return self::textFor($this -> type, $this -> actorName());
    }

    /**
     * The one wording for a notification, shared by the page, the dropdown,
     * and the Web Push a phone shows with no tab open - static because the
     * push is composed at creation time, before any hydrated instance exists.
     */
    public static function textFor(string $type, string $actor_name): string
    {
        return match ($type) {
            'postReady' => 'Your media has finished processing and is now live',
            'scheduledPostLive' => 'Your scheduled post is now live',
            'uploadPartlyFailed' => 'Your post is live, but one or more of its files couldn\'t be processed',
            'uploadFailed' => 'One of your uploads failed to process and was not posted',
            'mailerFailed' => 'Email delivery failed - the mailer may be down. Please check your mail configuration.',
            'mailFromNotConfigured' => 'No mail "from" address is configured, so emails can\'t be sent. Set one in Site Settings (Mail section) or via bin/install.php.',
            'systemError' => 'A server error occurred. Check the error log for details.',
            'passwordRemovedGoogle' => 'Your password was removed when you signed in with Google. Use "Forgot password" if you want to set a new one.',
            'like' => $actor_name . ' liked your post',
            'repost' => $actor_name . ' reposted your post',
            'reply' => $actor_name . ' replied to your post',
            'friendRequest' => $actor_name . ' sent you a friend request',
            'friendAccepted' => $actor_name . ' accepted your friend request',
            'message' => $actor_name . ' sent you a message',
            'mention' => $actor_name . ' mentioned you in a post',
            default => $actor_name . ' did something',
        };
    }

    protected function targetURL(): ?string
    {
        return match ($this -> type) {
            'like', 'repost', 'reply', 'postReady', 'scheduledPostLive', 'uploadPartlyFailed' => ServerURL::absolute('/users/' . Auth::user() ?-> slug . '/' . $this -> postId),
            'friendRequest' => ServerURL::absolute('/users/' . Auth::user() ?-> slug . '/friends'),
            'friendAccepted' => ServerURL::absolute('/users/' . $this -> actorUsername . '/'),
            'message' => ServerURL::absolute('/messages/' . $this -> actorUsername),
            // Unlike 'like'/'reply' (the recipient's OWN post), a mentioned
            // post belongs to the ACTOR (whoever wrote the post that mentions
            // you) - same reasoning as 'friendAccepted'/'message' below using
            // the actor's identity, not the recipient's.
            'mention' => ServerURL::absolute('/users/' . $this -> actorUsername . '/' . $this -> postId),
            'passwordRemovedGoogle' => ServerURL::absolute('/forgot-password'),
            default => null,
        };
    }

    /**
     * The JSON representation used by the notification-history endpoint that
     * feeds the client-side Notification class, which expects a ready-made
     * actorImage URL rather than the raw hasAvatar flag.
     *
     * @param self[] $notifications
     * @return array[]
     */
    public static function rowsToPayload(array $notifications): array
    {
        return array_map(static fn (self $notification): array => [
            'notificationId' => (int) $notification -> notificationId,
            'userId' => (int) $notification -> userId,
            'type' => $notification -> type,
            'postId' => $notification -> postId,
            'createdAt' => $notification -> createdAt,
            // A nested user object with row-named keys so Notification.js reads
            // the actor straight through (Avatar.forUser, actor.slug/title).
            'actor' => [
                'userId' => (int) $notification -> actorId,
                'slug' => $notification -> actorUsername,
                'title' => $notification -> actorDisplayName,
                'image' => $notification -> actorHasAvatar
                    ? ServerURL::absolute(User::avatarPath((int) $notification -> actorId))
                    : null,
            ],
        ], $notifications);
    }

    /**
     * The types that can be undone and done again: like/unlike, repost/
     * unrepost, and a friend request that can be cancelled and re-sent. Each
     * repeat would otherwise be a fresh notification and a fresh push - a way
     * to ring someone's bell as often as you like, needing nothing but the
     * button. Deliberately not every type: a second message or a second reply
     * is a second thing said, and has to arrive.
     */
    private const REPEATABLE_TYPES = ['like', 'repost', 'friendRequest'];

    /**
     * How long one of those counts as the same event. A day is long enough
     * that no flood survives it, and short enough that genuinely asking again
     * months later still says so.
     */
    private const REPEAT_WINDOW_SECONDS = 86400;

    public static function create(int $user_id, int $actor_id, string $type, ?int $post_id = null, bool $allow_self = false): void
    {
        if ($user_id === $actor_id && !$allow_self) {
            return;
        }

        if (self::alreadyNotified($user_id, $actor_id, $type, $post_id)) {
            return;
        }

        $mysqli = DB::connection();

        DB::run('
INSERT INTO `Notifications` (`userId`, `actorId`, `type`, `postId`)
    VALUES (?, ?, ?, ?)
', 'iisi', $user_id, $actor_id, $type, $post_id);

        $notification_id = (int) mysqli_insert_id($mysqli);
        $actor = User::load($actor_id);

        WebSocketPusher::push($user_id, [
            'event' => 'notification',
            'notification' => [
                'notificationId' => $notification_id,
                'userId' => $user_id,
                'type' => $type,
                'postId' => $post_id,
                'createdAt' => date('Y-m-d H:i:s'),
                'actor' => [
                    'userId' => $actor_id,
                    'slug' => $actor ?-> slug,
                    'title' => $actor ?-> title,
                    'image' => $actor !== null && $actor -> hasAvatar ? ServerURL::absolute(User::avatarPath($actor_id)) : null,
                ],
            ],
        ]);

        // The phone in a pocket: queued for the push worker, never sent from
        // this request. A message opens its thread; everything else opens the
        // notifications page, which carries the full context.
        WebPush::enqueueFor(
            $user_id,
            self::textFor($type, (string) ($actor ?-> title ?: $actor ?-> slug)),
            $type === 'message' && $actor !== null
                ? ServerURL::absolute('/messages/' . $actor -> slug)
                : ServerURL::absolute('/notifications')
        );
    }

    /**
     * Notifies the primary admin (userId 1) that email delivery is failing, so
     * they can fix the mailer. Throttled: skipped when any of the admin's last
     * five notifications is already a mailer-failure alert, so a burst of
     * failures can't flood them. $actor_id is the user whose mail failed - who
     * they are doesn't matter for this type, it just needs to be a real user so
     * the notification renders; allow_self covers that user being the admin.
     */
    public static function warnAdminMailerFailed(int $actor_id): void
    {
        $admin_id = 1;

        if (self::hasRecentOfType($admin_id, 'mailerFailed', 5)) {
            return;
        }

        self::create($admin_id, $actor_id, 'mailerFailed', null, true);
    }

    /**
     * Notifies the primary admin (userId 1) that an uncaught exception or
     * fatal error occurred, straight from init.php's error handlers. The
     * message/stack trace itself stays in error_log (this only flags that
     * something broke, not what) - the point is the admin finds out right
     * away instead of only by stumbling onto the log. Throttled the same way
     * warnAdminMailerFailed() is, so a fatal that repeats on every request
     * can't flood them with duplicates. $admin_id is both the notified user
     * and the actor (allow_self) since there's no real actor for a system
     * error - it just needs to be a valid user for the notification to render.
     */
    public static function warnAdminSystemError(): void
    {
        $admin_id = 1;

        if (self::hasRecentOfType($admin_id, 'systemError', 5)) {
            return;
        }

        self::create($admin_id, $admin_id, 'systemError', null, true);
    }

    /**
     * Notifies the primary admin (userId 1) that no mail "from" address is
     * configured, so Mailer::send() refused to attempt sending at all rather
     * than mail from a blank/broken address. $admin_id is both the notified
     * user and the actor (allow_self), same as warnAdminSystemError() -
     * Mailer::send() has no real actor of its own to attribute this to.
     * Throttled the same way.
     */
    public static function warnAdminMailFromNotConfigured(): void
    {
        $admin_id = 1;

        if (self::hasRecentOfType($admin_id, 'mailFromNotConfigured', 5)) {
            return;
        }

        self::create($admin_id, $admin_id, 'mailFromNotConfigured', null, true);
    }

    /**
     * Whether any of $user_id's most recent $within notifications is of $type.
     * Used to throttle repeat system alerts so duplicates don't pile up.
     */
    /**
     * Whether this exact event - same person, same kind, same post - was
     * already announced inside the repeat window. A null postId is its own
     * case rather than a wildcard (`<=>` is the null-safe comparison): a
     * friend request names no post, and two from the same person are the same
     * event, while two likes on different posts are not.
     */
    private static function alreadyNotified(int $user_id, int $actor_id, string $type, ?int $post_id): bool
    {
        if (!in_array($type, self::REPEATABLE_TYPES, true)) {
            return false;
        }

        $window = self::REPEAT_WINDOW_SECONDS;

        $existing = DB::row('
SELECT `notificationId`
    FROM `Notifications`
    WHERE `userId` = ? AND `actorId` = ? AND `type` = ?
        AND (`postId` <=> ?)
        AND `createdAt` > NOW() - INTERVAL ? SECOND
    LIMIT 1
', 'Notification', 'iisii', $user_id, $actor_id, $type, $post_id, $window);

        return $existing !== null;
    }

    public static function hasRecentOfType(int $user_id, string $type, int $within): bool
    {
        $recent = DB::rows('
SELECT `type`
    FROM `Notifications`
    WHERE `userId` = ?
    ORDER BY `notificationId` DESC
    LIMIT ?
', 'Notification', 'ii', $user_id, $within);

        foreach ($recent as $notification) {
            if ($notification -> type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marks every notification that exists for $user_id right now as seen -
     * not just whatever the caller happened to have loaded, since "seen" is
     * meant to track against the true most-recent notification.
     */
    /**
     * The newest notification this person has, or 0 if none - for the nav's
     * unseen mark, which needs the answer without building the dropdown that
     * usually provides it.
     */
    public static function newestIdFor(int $user_id): int
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COALESCE(MAX(`notificationId`), 0) AS `newestId`
    FROM `Notifications`
    WHERE `userId` = ?
', 'i', $user_id));

        return (int) mysqli_fetch_assoc($result)['newestId'];
    }

    public static function markSeen(int $user_id): void
    {
        $no_notifications_fallback = 0;

        DB::run('
UPDATE `Users`
    SET `lastNotificationId` = (
        SELECT COALESCE(MAX(`notificationId`), ?)
            FROM `Notifications`
            WHERE `userId` = ?
    )
    WHERE `userId` = ?
', 'iii', $no_notifications_fallback, $user_id, $user_id);
    }
}
