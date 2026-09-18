<?php

declare(strict_types=1);

class PokerChat
{
    public static function send(int $table_id, int $user_id, string $key, string $body, int $now): void
    {
        DB::requireTransaction();
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 500 || preg_match('/^[a-f0-9]{32}$/D', $key) !== 1) throw new \InvalidArgumentException('Write a message of 1–500 characters.');
        $existing = DB::row('SELECT `tableId`, `body` FROM `PokerChat` WHERE `userId` = ? AND `requestKey` = ?', 'stdClass', 'is', $user_id, $key);
        if ($existing !== null) {
            if ((int) $existing -> tableId !== $table_id || $existing -> body !== $body) throw new \DomainException('This message identifier has already been used.');
            return;
        }
        $recent = DB::row('SELECT `createdAt` FROM `PokerChat` WHERE `userId` = ? ORDER BY `messageId` DESC LIMIT 1', 'stdClass', 'i', $user_id);
        if ($recent !== null && (int) $recent -> createdAt > $now - 2) throw new \DomainException('Please wait a moment before sending another message.');
        DB::run('INSERT INTO `PokerChat` (`tableId`, `userId`, `requestKey`, `body`, `createdAt`) VALUES (?, ?, ?, ?, ?)', 'iissi', $table_id, $user_id, $key, $body, $now);
    }

    public static function messages(int $table_id, int $viewer): array
    {
        $rows = DB::rows('SELECT c.`messageId`, c.`userId`, c.`body`, u.`slug`, u.`hasAvatar`
            FROM `PokerChat` c JOIN `Users` u ON u.`userId` = c.`userId`
            WHERE c.`tableId` = ? AND u.`banned` = 0 AND NOT EXISTS (
                SELECT 1 FROM `Blocks` b WHERE (b.`blockerId` = ? AND b.`blockedId` = c.`userId`)
                    OR (b.`blockedId` = ? AND b.`blockerId` = c.`userId`))
            ORDER BY c.`messageId` DESC LIMIT 50', 'stdClass', 'iii', $table_id, $viewer, $viewer);
        return array_map(static function (object $row): array {
            $user = new User(); $user -> userId = (int) $row -> userId; $user -> hasAvatar = (int) $row -> hasAvatar;
            return ['messageId' => (int) $row -> messageId, 'userId' => (int) $row -> userId, 'name' => '@' . $row -> slug, 'image' => $user -> avatarURL(), 'body' => $row -> body];
        }, array_reverse($rows));
    }
}
