<?php

declare(strict_types=1);

class Notification extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Notification';

    public ?int $notificationId = null;
    public ?int $userId = null;
    public ?int $actorId = null;
    public ?string $type = null;
    public ?int $postId = null;
    public ?string $createdAt = null;
    public ?User $actor = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> notificationId !== null) {
            $this -> attributes['data-notification-id'] = (string) $this -> notificationId;
        }

        $link = new Anchor($this -> targetURL());
        $link -> class = 'd-flex align-items-center gap-3';

        $link -> addContent(Avatar::forUser($this -> actor));

        $info = new Div();

        $text = new Div();
        $text -> contents[] = $this -> text();
        $info -> addContent($text);

        $meta = new RelativeTime($this -> createdAt);
        $meta -> class = 'Muted text-sm ' . $meta -> class;
        $info -> addContent($meta);

        $link -> addContent($info);

        $this -> contents[] = $link;

        return parent::toDOM();
    }

    protected function actorName(): string
    {
        return $this -> actor -> displayName ?? $this -> actor -> username;
    }

    protected function text(): string
    {
        return match ($this -> type) {
            'postReady' => 'Your media has finished processing and is now live',
            'uploadPartlyFailed' => 'Your post is live, but one or more of its files couldn\'t be processed',
            'uploadFailed' => 'One of your uploads failed to process and was not posted',
            'mailerFailed' => 'Email delivery failed - the mailer may be down. Please check your mail configuration.',
            'systemError' => 'A server error occurred. Check the error log for details.',
            'passwordRemovedGoogle' => 'Your password was removed when you signed in with Google. Use "Forgot password" if you want to set a new one.',
            default => $this -> actorText(),
        };
    }

    protected function actorText(): string
    {
        $name = $this -> actorName();

        return match ($this -> type) {
            'like' => $name . ' liked your post',
            'reply' => $name . ' replied to your post',
            'friendRequest' => $name . ' sent you a friend request',
            'friendAccepted' => $name . ' accepted your friend request',
            'message' => $name . ' sent you a message',
            'mention' => $name . ' mentioned you in a post',
            default => $name . ' did something',
        };
    }

    protected function targetURL(): string
    {
        return match ($this -> type) {
            'like', 'reply', 'postReady', 'uploadPartlyFailed' => ServerURL::absolute('/users/' . Auth::user() ?-> username . '/' . $this -> postId),
            'friendRequest' => ServerURL::absolute('/users/' . Auth::user() ?-> username . '/friends'),
            'friendAccepted' => ServerURL::absolute('/users/' . $this -> actor -> username . '/'),
            'message' => ServerURL::absolute('/messages/' . $this -> actor -> username),
            // Unlike 'like'/'reply' (the recipient's OWN post), a mentioned
            // post belongs to the ACTOR (whoever wrote the post that mentions
            // you) - same reasoning as 'friendAccepted'/'message' below using
            // the actor's identity, not the recipient's.
            'mention' => ServerURL::absolute('/users/' . $this -> actor -> username . '/' . $this -> postId),
            'passwordRemovedGoogle' => ServerURL::absolute('/forgot-password'),
            default => '#',
        };
    }

    public static function fromRow(array $row): self
    {
        $notification = new self();
        $notification -> notificationId = (int) $row['notificationId'];
        $notification -> userId = (int) $row['userId'];
        $notification -> actorId = (int) $row['actorId'];
        $notification -> type = $row['type'];
        $notification -> postId = $row['postId'] !== null ? (int) $row['postId'] : null;
        $notification -> createdAt = $row['createdAt'];

        $actor = new User();
        $actor -> userId = (int) $row['actorId'];
        $actor -> username = $row['actorUsername'];
        $actor -> displayName = $row['actorDisplayName'];
        $actor -> hasAvatar = (int) $row['actorHasAvatar'];
        $notification -> actor = $actor;

        return $notification;
    }

    /**
     * The JSON representation used by the notification-history endpoint that
     * feeds the client-side Notification class, which expects a ready-made
     * actorImage URL rather than the raw actorHasAvatar flag.
     *
     * @param array[] $rows
     * @return array[]
     */
    public static function rowsToPayload(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['actorImage'] = (int) $row['actorHasAvatar']
                ? ServerURL::absolute(User::avatarPath((int) $row['actorId']))
                : null;
        }

        unset($row);

        return $rows;
    }

    /**
     * Fetches $limit + 1 rows so an extra leftover row (if present) signals more
     * history without a separate count query.
     *
     * @return array{rows: array[], hasMore: bool}
     */
    public static function rowsForUser(int $user_id, int $limit, ?int $before_id = null): array
    {
        $mysqli = Database::connection();
        $fetch_limit = $limit + 1;

        if ($before_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `n`.*, `u`.`username` AS `actorUsername`, `u`.`displayName` AS `actorDisplayName`, `u`.`hasAvatar` AS `actorHasAvatar`
    FROM `Notifications` `n`
    JOIN `Users` `u` ON `u`.`userId` = `n`.`actorId`
    WHERE `n`.`userId` = ? AND `n`.`notificationId` < ?
    ORDER BY `n`.`notificationId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iii', $user_id, $before_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `n`.*, `u`.`username` AS `actorUsername`, `u`.`displayName` AS `actorDisplayName`, `u`.`hasAvatar` AS `actorHasAvatar`
    FROM `Notifications` `n`
    JOIN `Users` `u` ON `u`.`userId` = `n`.`actorId`
    WHERE `n`.`userId` = ?
    ORDER BY `n`.`notificationId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'ii', $user_id, $fetch_limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
        }

        return ['rows' => $rows, 'hasMore' => $has_more];
    }

    public static function create(int $user_id, int $actor_id, string $type, ?int $post_id = null, bool $allow_self = false): void
    {
        if ($user_id === $actor_id && !$allow_self) {
            return;
        }

        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
INSERT INTO `Notifications` (`userId`, `actorId`, `type`, `postId`)
    VALUES (?, ?, ?, ?)
');
        mysqli_stmt_bind_param($stmt, 'iisi', $user_id, $actor_id, $type, $post_id);
        mysqli_stmt_execute($stmt);

        $notification_id = (int) mysqli_insert_id($mysqli);
        $actor = User::load($actor_id);

        WebSocketPusher::push($user_id, [
            'event' => 'notification',
            'notification' => [
                'notificationId' => $notification_id,
                'userId' => $user_id,
                'actorId' => $actor_id,
                'type' => $type,
                'postId' => $post_id,
                'createdAt' => date('Y-m-d H:i:s'),
                'actorUsername' => $actor ?-> username,
                'actorDisplayName' => $actor ?-> displayName,
                'actorImage' => $actor !== null && $actor -> hasAvatar ? ServerURL::absolute(User::avatarPath($actor_id)) : null,
            ],
        ]);
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
     * Whether any of $user_id's most recent $within notifications is of $type.
     * Used to throttle repeat system alerts so duplicates don't pile up.
     */
    public static function hasRecentOfType(int $user_id, string $type, int $within): bool
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `type`
    FROM `Notifications`
    WHERE `userId` = ?
    ORDER BY `notificationId` DESC
    LIMIT ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $within);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['type'] === $type) {
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
    public static function markSeen(int $user_id): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
UPDATE `Users`
    SET `lastNotificationId` = (
        SELECT COALESCE(MAX(`notificationId`), 0)
            FROM `Notifications`
            WHERE `userId` = ?
    )
    WHERE `userId` = ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
}
