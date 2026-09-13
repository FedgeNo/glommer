<?php

declare(strict_types=1);

class PushSubscription
{
    public const MAX_SUBSCRIPTIONS = 10;
    public const MAX_REGISTRATIONS_PER_DAY = 10;

    /** Returns subscribed, full, limited, or unavailable. Refreshes cost no issuance. */
    public static function subscribe(int $user_id, string $endpoint, string $key, string $auth, ?string $user_agent): string
    {
        return DB::transaction(static function () use ($user_id, $endpoint, $key, $auth, $user_agent): string {
            $user = User::loadForUpdate($user_id);
            if ($user === null || $user -> banned) {
                return 'unavailable';
            }
            $existing = DB::row('SELECT `pushSubscriptionId`, `userId` FROM `PushSubscriptions` WHERE `endpoint` = ? FOR UPDATE',
                \stdClass::class, 's', $endpoint);
            $refresh = $existing !== null && (int) $existing -> userId === $user_id;
            if (!$refresh) {
                $count = DB::row('SELECT COUNT(*) AS `total` FROM `PushSubscriptions` WHERE `userId` = ?', \stdClass::class, 'i', $user_id);
                if ((int) $count -> total >= self::MAX_SUBSCRIPTIONS) {
                    return 'full';
                }
                DB::run('DELETE FROM `PushRegistrations` WHERE `userId` = ? AND `createdAt` <= NOW() - INTERVAL 1 DAY', 'i', $user_id);
                $count = DB::row('SELECT COUNT(*) AS `total` FROM `PushRegistrations` WHERE `userId` = ? AND `createdAt` > NOW() - INTERVAL 1 DAY',
                    \stdClass::class, 'i', $user_id);
                if ((int) $count -> total >= self::MAX_REGISTRATIONS_PER_DAY) {
                    return 'limited';
                }
            }
            $agent = $user_agent === null ? null : mb_substr($user_agent, 0, 255);
            if ($existing !== null) {
                $id = (int) $existing -> pushSubscriptionId;
                if (!$refresh) {
                    // A shared browser changing accounts must not receive queued
                    // notifications belonging to the previous account.
                    DB::run('DELETE FROM `PushDeliveries` WHERE `pushSubscriptionId` = ?', 'i', $id);
                }
                DB::run('UPDATE `PushSubscriptions` SET `userId` = ?, `p256dh` = ?, `auth` = ?, `userAgent` = ? WHERE `pushSubscriptionId` = ?',
                    'isssi', $user_id, $key, $auth, $agent, $id);
            } else {
                DB::run('INSERT INTO `PushSubscriptions` (`userId`, `endpoint`, `p256dh`, `auth`, `userAgent`) VALUES (?, ?, ?, ?, ?)',
                    'issss', $user_id, $endpoint, $key, $auth, $agent);
                $id = (int) mysqli_insert_id(DB::connection());
            }
            if (!$refresh) {
                DB::run('INSERT INTO `PushRegistrations` (`userId`, `subscriptionId`) VALUES (?, ?)', 'ii', $user_id, $id);
            }
            return 'subscribed';
        });
    }

    public static function remove(int $user_id, ?int $id, string $endpoint = ''): void
    {
        DB::transaction(static function () use ($user_id, $id, $endpoint): void {
            User::loadForUpdate($user_id);
            if ($id !== null) {
                DB::run('DELETE FROM `PushSubscriptions` WHERE `userId` = ? AND `pushSubscriptionId` = ?', 'ii', $user_id, $id);
            } else {
                DB::run('DELETE FROM `PushSubscriptions` WHERE `userId` = ? AND `endpoint` = ?', 'is', $user_id, $endpoint);
            }
        });
    }

    /** List safe display metadata, never endpoints or subscription keys. */
    public static function listing(int $user_id, string $current_endpoint = ''): array
    {
        $words = Strings::for(RememberedDevice::class);
        $rows = DB::rows('SELECT `pushSubscriptionId`, `userAgent`, `createdAt`, (`endpoint` = ?) AS `current`
            FROM `PushSubscriptions` WHERE `userId` = ? ORDER BY `pushSubscriptionId` LIMIT 100',
            \stdClass::class, 'si', $current_endpoint, $user_id);
        return array_map(static fn (object $row): array => [
            'id' => (int) $row -> pushSubscriptionId,
            'label' => RememberedDevice::describe($row -> userAgent, $words)
                . ((bool) $row -> current ? (string) ($words['thisDevice'] ?? '') : ''),
            'createdAt' => $row -> createdAt,
            'current' => (bool) $row -> current,
        ], $rows);
    }

    public static function status(int $user_id, string $endpoint): array
    {
        return [
            'subscriptions' => self::listing($user_id, $endpoint),
            'subscribed' => $endpoint !== '' && DB::row('SELECT `pushSubscriptionId` FROM `PushSubscriptions` WHERE `userId` = ? AND `endpoint` = ?',
                \stdClass::class, 'is', $user_id, $endpoint) !== null,
        ];
    }

    public static function prune(): void
    {
        DB::run('DELETE FROM `PushRegistrations` WHERE `createdAt` <= NOW() - INTERVAL 1 DAY ORDER BY `createdAt` LIMIT 1000');
    }
}
