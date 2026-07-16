<?php

declare(strict_types=1);

/**
 * The flat columns Notification::rowsForUser()'s Notifications/Users join
 * selects - Notification::fromRow() turns one of these into a Notification
 * with its nested actor User attached.
 */
class NotificationData
{
    public ?int $notificationId = null;
    public ?int $userId = null;
    public ?int $actorId = null;
    public ?string $type = null;
    public ?int $postId = null;
    public ?string $createdAt = null;
    public ?string $actorUsername = null;
    public ?string $actorDisplayName = null;
    public int $actorHasAvatar = 0;
}
