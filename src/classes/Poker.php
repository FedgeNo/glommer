<?php

declare(strict_types=1);

/** Matchmaking and table persistence. Every transition shares the lobby lock. */
class Poker
{
    public const BUY_IN = 1000;
    public const MIN_BUY_IN = 200;
    public const SEATS = 9;
    public const WAIT_SECONDS = 20;
    public const PRESENCE_SECONDS = 45;

    public static function request(int $user_id, string $action, array $input = []): array
    {
        if (!in_array($action, ['state', 'join', 'leave', 'act', 'chat'], true)) throw new \InvalidArgumentException('Unknown poker request.');
        $wallet = GameWallet::forUser($user_id);
        GameWallet::visit($wallet);
        $response = DB::transaction(static function () use ($user_id, $action, $input, $wallet): array {
            DB::run('INSERT IGNORE INTO `PokerLobby` (`lobbyId`) VALUES (1)');
            DB::row('SELECT `lobbyId` FROM `PokerLobby` WHERE `lobbyId` = 1 FOR UPDATE', 'stdClass');
            $now = time();
            // Expired presence removes only the next-hand reservation, never chips in play.
            DB::run('UPDATE `PokerPlayers` SET `queued` = 0 WHERE `lastSeen` < ?', 'i', $now - self::PRESENCE_SECONDS);
            DB::run('INSERT INTO `PokerPlayers` (`userId`, `lastSeen`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `lastSeen` = VALUES(`lastSeen`)', 'ii', $user_id, $now);
            foreach (DB::rows('SELECT * FROM `PokerTables` WHERE `finishedAt` IS NULL AND `deadline` <= ? ORDER BY `deadline` LIMIT 24', 'stdClass', 'i', $now) as $table) self::tick($table, $now);
            $player = self::player($user_id);
            $table = $player -> tableId === null ? null : DB::row('SELECT * FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', (int) $player -> tableId);
            if ($action === 'join') {
                if ($table === null || $table -> finishedAt !== null) {
                    $funds = DB::row('SELECT `balance` FROM `GameWallets` WHERE `walletId` = ?', 'stdClass', 'i', $wallet);
                    if ((int) $funds -> balance < self::MIN_BUY_IN) throw new \DomainException('You need at least 200 chips to take a poker seat.');
                }
                if (!(int) $player -> queued) DB::run('UPDATE `PokerPlayers` SET `queued` = 1, `readyAt` = ? WHERE `userId` = ?', 'ii', $now, $user_id);
            } elseif ($action === 'leave') {
                DB::run('UPDATE `PokerPlayers` SET `queued` = 0 WHERE `userId` = ?', 'i', $user_id);
                // Leaving takes effect after the hand; no early refund or extra cards.
            } elseif ($action === 'act') {
                if ($table === null || $table -> finishedAt !== null || (int) ($input['tableId'] ?? 0) !== (int) $table -> tableId) throw new \DomainException('This hand has ended.');
                $round = new PokerRound(json_decode($table -> state, true, 512, JSON_THROW_ON_ERROR));
                if (($input['version'] ?? -1) !== $round -> snapshot()['version']) throw new \DomainException('The table has moved on. Check your current turn.');
                if (($round -> actor()['userId'] ?? null) !== $user_id) throw new \DomainException('It is not your turn.');
                $round -> act($round -> snapshot()['turn'], $input['move'] ?? '', $input['amount'] ?? 0, $now);
                self::save((int) $table -> tableId, $round, $now);
            } elseif ($action === 'chat') {
                if ($table === null || (int) ($input['tableId'] ?? 0) !== (int) $table -> tableId) throw new \DomainException('Join a table before chatting.');
                PokerChat::send((int) $table -> tableId, $user_id, $input['requestKey'] ?? '', $input['body'] ?? '', $now);
            }
            self::match($now);
            DB::run('DELETE FROM `PokerTables` WHERE `finishedAt` < ?', 'i', $now - 86400);
            $player = self::player($user_id);
            $table = $player -> tableId === null ? null : DB::row('SELECT * FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', (int) $player -> tableId);
            $view = null;
            if ($table !== null) {
                $view = (new PokerRound(json_decode($table -> state, true, 512, JSON_THROW_ON_ERROR))) -> view($user_id);
                $view['tableId'] = (int) $table -> tableId;
                $view['chat'] = PokerChat::messages((int) $table -> tableId, $user_id);
            }
            $waiting = (int) DB::row('SELECT COUNT(*) AS `count` FROM `PokerPlayers` p LEFT JOIN `PokerTables` t ON t.`tableId` = p.`tableId` WHERE p.`queued` = 1 AND (t.`tableId` IS NULL OR t.`finishedAt` IS NOT NULL)', 'stdClass') -> count;
            return ['table' => $view, 'queued' => (bool) $player -> queued, 'waiting' => $waiting,
                'matchAt' => (int) $player -> readyAt + self::WAIT_SECONDS, 'serverTime' => $now, 'userId' => $user_id];
        });
        $response['wallet'] = GameWallet::visit($wallet);
        return $response;
    }

    private static function player(int $user_id): object
    {
        return DB::row('SELECT * FROM `PokerPlayers` WHERE `userId` = ?', 'stdClass', 'i', $user_id);
    }

    private static function tick(object $table, int $now): void
    {
        $round = new PokerRound(json_decode($table -> state, true, 512, JSON_THROW_ON_ERROR));
        // Catch up a dormant table without one request monopolizing a PHP worker.
        for ($i = 0; $i < 64 && !$round -> finished() && $round -> deadline() <= $now; $i++) {
            $state = $round -> snapshot();
            $action = $round -> actor()['userId'] === null ? $round -> botAction() : [$round -> legal($state['turn'])['canCheck'] ? 'check' : 'fold', 0];
            $round -> act($state['turn'], $action[0], $action[1], $round -> deadline());
        }
        self::save((int) $table -> tableId, $round, $now);
    }

    private static function save(int $table_id, PokerRound $round, int $now): void
    {
        if ($round -> finished()) {
            $existing = DB::row('SELECT `finishedAt` FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', $table_id);
            if ($existing -> finishedAt === null) {
                foreach ($round -> snapshot()['seats'] as $seat) {
                    if ($seat['userId'] === null) continue;
                    $wallet = DB::row('SELECT `walletId` FROM `GameWallets` WHERE `userId` = ?', 'stdClass', 'i', $seat['userId']);
                    if ($wallet === null) continue;
                    GameWallet::tableTransfer((int) $wallet -> walletId, self::receipt($table_id, $seat['userId'], 'return'), 0, $seat['stack'], ['tableId' => $table_id, 'returned' => $seat['stack']]);
                    DB::run('UPDATE `PokerPlayers` SET `readyAt` = ? WHERE `userId` = ? AND `tableId` = ?', 'iii', $now + 10, $seat['userId'], $table_id);
                }
            }
        }
        DB::run('UPDATE `PokerTables` SET `state` = ?, `deadline` = ?, `finishedAt` = ? WHERE `tableId` = ?', 'siii',
            json_encode($round -> snapshot(), JSON_THROW_ON_ERROR), $round -> deadline(), $round -> finished() ? $now : null, $table_id);
    }

    private static function receipt(int $table_id, int $user_id, string $kind): string
    {
        return substr(hash('sha256', 'poker:' . $table_id . ':' . $user_id . ':' . $kind), 0, 32);
    }

    private static function match(int $now): void
    {
        $waiting = DB::rows('SELECT p.*, w.`walletId`, w.`balance`, u.`slug`, u.`hasAvatar`
            FROM `PokerPlayers` p JOIN `Users` u ON u.`userId` = p.`userId`
            JOIN `GameWallets` w ON w.`userId` = p.`userId`
            LEFT JOIN `PokerTables` t ON t.`tableId` = p.`tableId`
            WHERE p.`queued` = 1 AND p.`readyAt` <= ? AND u.`banned` = 0
              AND (t.`tableId` IS NULL OR t.`finishedAt` IS NOT NULL)
            ORDER BY p.`readyAt`, p.`userId` LIMIT 180', 'stdClass', 'i', $now);
        $eligible = [];
        foreach ($waiting as $player) {
            if ((int) $player -> balance < self::MIN_BUY_IN) DB::run('UPDATE `PokerPlayers` SET `queued` = 0 WHERE `userId` = ?', 'i', (int) $player -> userId);
            else $eligible[] = $player;
        }
        while ($eligible && (int) $eligible[0] -> readyAt + self::WAIT_SECONDS <= $now) {
            $players = array_splice($eligible, 0, self::SEATS);
            $seats = [];
            foreach ($players as $player) {
                $user = new User();
                $user -> userId = (int) $player -> userId; $user -> hasAvatar = (int) $player -> hasAvatar;
                $seats[] = ['userId' => (int) $player -> userId, 'name' => '@' . $player -> slug, 'image' => $user -> avatarURL(), 'stack' => min(self::BUY_IN, (int) $player -> balance)];
            }
            for ($i = count($seats); $i < self::SEATS; $i++) $seats[] = ['userId' => null, 'name' => PokerBot::NAMES[$i - count($players)], 'image' => null, 'stack' => self::BUY_IN];
            // Fresh random seats prevent always charging the oldest arrival the same blind.
            for ($i = count($seats) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$seats[$i], $seats[$j]] = [$seats[$j], $seats[$i]]; }
            $round = PokerRound::deal($seats, random_int(0, self::SEATS - 1), $now);
            DB::run('INSERT INTO `PokerTables` (`state`, `deadline`) VALUES (?, ?)', 'si', json_encode($round -> snapshot(), JSON_THROW_ON_ERROR), $round -> deadline());
            $table_id = (int) mysqli_insert_id(DB::connection());
            foreach ($players as $player) {
                $stake = min(self::BUY_IN, (int) $player -> balance);
                GameWallet::tableTransfer((int) $player -> walletId, self::receipt($table_id, (int) $player -> userId, 'buyin'), $stake, 0, ['tableId' => $table_id, 'buyIn' => $stake]);
                DB::run('UPDATE `PokerPlayers` SET `tableId` = ? WHERE `userId` = ?', 'ii', $table_id, (int) $player -> userId);
            }
        }
    }
}
