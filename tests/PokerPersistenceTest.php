<?php

declare(strict_types=1);

class PokerPersistenceTest extends DatabaseTestCase
{
    private array $users = [];
    private array $tables = [];

    private function join(int $count): array
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $user = self::createUser(); $users[] = $user; $this -> users[] = $user;
            Poker::request($user, 'join');
        }
        return $users;
    }

    private function match(array $users): array
    {
        foreach ($users as $user) DB::run('UPDATE `PokerPlayers` SET `readyAt` = ? WHERE `userId` = ?', 'ii', time() - 21, $user);
        $view = Poker::request($users[0], 'state');
        foreach ($users as $user) {
            $table = DB::row('SELECT `tableId` FROM `PokerPlayers` WHERE `userId` = ?', 'stdClass', 'i', $user);
            if ($table -> tableId !== null) $this -> tables[] = (int) $table -> tableId;
        }
        return $view;
    }

    private function cleanup(): void
    {
        foreach ($this -> users as $user) DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
        foreach (array_unique($this -> tables) as $table) DB::run('DELETE FROM `PokerTables` WHERE `tableId` = ?', 'i', $table);
        $this -> users = []; $this -> tables = [];
    }

    public function testGroupsHumansNineAtATimeFillsBotsAndDebitsOnlyOnce(): void
    {
        try {
            $users = $this -> join(10); $first = $this -> match($users);
            $this -> assertSame(9, count($first['table']['seats']));
            $this -> assertSame(9, count(array_filter($first['table']['seats'], static fn (array $seat): bool => $seat['userId'] !== null)));
            $second = Poker::request($users[9], 'state');
            $this -> assertSame(8, count(array_filter($second['table']['seats'], static fn (array $seat): bool => $seat['userId'] === null)));
            $this -> assertTrue($first['table']['tableId'] !== $second['table']['tableId']);
            $retry = Poker::request($users[0], 'join');
            $this -> assertSame(0, $retry['wallet']['balance']);
            $this -> assertSame(1, (int) DB::row('SELECT COUNT(*) AS `count` FROM `GamePlays` WHERE `walletId` = ? AND `game` = ?', 'stdClass', 'is', GameWallet::forUser($users[0]), 'poker') -> count);
        } finally { $this -> cleanup(); }
    }

    public function testOutOfTurnAndStaleActionsCannotChangeThePot(): void
    {
        try {
            $users = $this -> join(2); $view = $this -> match($users); $table = $view['table'];
            $wrong = $table['seats'][$table['turn']]['userId'] === $users[0] ? $users[1] : $users[0];
            $before = DB::row('SELECT `state` FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', $table['tableId']) -> state;
            $rejected = false;
            try { Poker::request($wrong, 'act', ['tableId' => $table['tableId'], 'version' => $table['version'], 'move' => 'call', 'amount' => 0]); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
            $this -> assertSame($before, DB::row('SELECT `state` FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', $table['tableId']) -> state);
            $rejected = false;
            try { Poker::request($users[0], 'act', ['tableId' => $table['tableId'], 'version' => -1, 'move' => 'call']); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
        } finally { $this -> cleanup(); }
    }

    public function testAbandonedHandSettlesOnceAndLeavesWithoutAnotherBuyIn(): void
    {
        try {
            $users = $this -> join(1); $view = $this -> match($users); $id = $view['table']['tableId'];
            Poker::request($users[0], 'leave');
            $row = DB::row('SELECT `state` FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', $id);
            $state = json_decode($row -> state, true); $state['deadline'] = time() - 10000;
            DB::run('UPDATE `PokerTables` SET `state` = ?, `deadline` = 0 WHERE `tableId` = ?', 'si', json_encode($state), $id);
            for ($i = 0; $i < 20; $i++) { $view = Poker::request($users[0], 'state'); if ($view['table']['street'] === 'finished') break; }
            $this -> assertSame('finished', $view['table']['street']);
            $this -> assertFalse($view['queued']);
            $balance = $view['wallet']['balance'];
            $this -> assertSame($balance, Poker::request($users[0], 'state')['wallet']['balance']);
            $this -> assertSame(2, (int) DB::row('SELECT COUNT(*) AS `count` FROM `GamePlays` WHERE `walletId` = ? AND `game` = ?', 'stdClass', 'is', GameWallet::forUser($users[0]), 'poker') -> count);
        } finally { $this -> cleanup(); }
    }

    public function testChatIsTableScopedRetrySafeAndRespectsBlocks(): void
    {
        try {
            $users = $this -> join(2); $view = $this -> match($users); $id = $view['table']['tableId'];
            $message = ['tableId' => $id, 'requestKey' => bin2hex(random_bytes(16)), 'body' => '<img src=x> hello'];
            Poker::request($users[0], 'chat', $message); Poker::request($users[0], 'chat', $message);
            $chat = Poker::request($users[1], 'state')['table']['chat'];
            $this -> assertSame(1, count($chat)); $this -> assertSame($message['body'], $chat[0]['body']);
            $outsider = $this -> join(1)[0]; $rejected = false;
            try { Poker::request($outsider, 'chat', $message); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
            Block::create($users[1], $users[0]);
            $this -> assertSame([], Poker::request($users[1], 'state')['table']['chat']);
        } finally { $this -> cleanup(); }
    }

    public function testTableTransferRollsBackAndRejectsConflictingReceipts(): void
    {
        try {
            $user = $this -> join(1)[0]; $wallet = GameWallet::forUser($user); $key = bin2hex(random_bytes(16));
            try { DB::transaction(static function () use ($wallet, $key): void {
                GameWallet::tableTransfer($wallet, $key, 1000, 0, ['test' => true]);
                throw new \DomainException('abort');
            }); } catch (\DomainException) {}
            $this -> assertSame(1000, GameWallet::visit($wallet)['balance']);
            $this -> assertNull(DB::row('SELECT * FROM `GamePlays` WHERE `requestKey` = ?', 'stdClass', 's', $key));
        } finally { $this -> cleanup(); }
    }

    public function testExpiredReservationDoesNotReviveWhenThePlayerReturns(): void
    {
        try {
            $user = $this -> join(1)[0];
            DB::run('UPDATE `PokerPlayers` SET `lastSeen` = ?, `readyAt` = ? WHERE `userId` = ?', 'iii', time() - 60, time() - 60, $user);
            $view = Poker::request($user, 'state');
            $this -> assertFalse($view['queued']);
            $this -> assertNull($view['table']);
            $this -> assertSame(1000, $view['wallet']['balance']);
        } finally { $this -> cleanup(); }
    }

    public function testSmallerStacksCanPlayAndInsufficientStacksAreNotCharged(): void
    {
        try {
            $users = $this -> join(2);
            GameWallet::spend(GameWallet::forUser($users[0]), bin2hex(random_bytes(16)), 'fixture', 750);
            GameWallet::spend(GameWallet::forUser($users[1]), bin2hex(random_bytes(16)), 'fixture', 950);
            $view = $this -> match($users);
            $seat = array_values(array_filter($view['table']['seats'], static fn (array $seat): bool => $seat['userId'] === $users[0]))[0];
            $this -> assertSame(250, $seat['stack'] + $seat['bet']);
            $this -> assertSame(0, $view['wallet']['balance']);
            $waiting = Poker::request($users[1], 'state');
            $this -> assertFalse($waiting['queued']);
            $this -> assertNull($waiting['table']);
            $this -> assertSame(50, $waiting['wallet']['balance']);
        } finally { $this -> cleanup(); }
    }

    public function testConcurrentMatchRequestsCannotSeatOrChargeTheSameUserTwice(): void
    {
        $children = [];
        try {
            $user = $this -> join(1)[0];
            DB::run('UPDATE `PokerPlayers` SET `readyAt` = ? WHERE `userId` = ?', 'ii', time() - 21, $user);
            DB::transaction(function () use ($user, &$children): void {
                DB::row('SELECT * FROM `PokerLobby` WHERE `lobbyId` = 1 FOR UPDATE', 'stdClass');
                for ($i = 0; $i < 2; $i++) {
                    $code = 'spl_autoload_register(static function ($class) { require getcwd() . "/src/classes/" . $class . ".php"; });'
                        . 'require "src/functions.php"; echo "ready\\n"; flush();'
                        . 'echo json_encode(Poker::request(' . $user . ', "state"));';
                    $process = proc_open(['timeout', '10', PHP_BINARY, '-r', $code],
                        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    $children[] = [$process, $pipes];
                    $this -> assertSame("ready\n", fgets($pipes[1]));
                }
            });
            $results = [];
            foreach ($children as [$process, $pipes]) $results[] = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this -> tables[] = $results[0]['table']['tableId'];
            $this -> assertSame($results[0]['table']['tableId'], $results[1]['table']['tableId']);
            $this -> assertSame(0, $results[0]['wallet']['balance']);
            $this -> assertSame(0, $results[1]['wallet']['balance']);
            $this -> assertSame(1, (int) DB::row('SELECT COUNT(*) AS `count` FROM `GamePlays` WHERE `walletId` = ? AND `game` = ?', 'stdClass', 'is', GameWallet::forUser($user), 'poker') -> count);
        } finally {
            foreach ($children as [$process, $pipes]) { foreach ($pipes as $pipe) fclose($pipe); proc_close($process); }
            $this -> cleanup();
        }
    }

    public function testFinishedPlayersRegroupWithWaitingHumansInsteadOfTheirOldBots(): void
    {
        try {
            $user = $this -> join(1)[0]; $first = $this -> match([$user]);
            $id = $first['table']['tableId'];
            $state = json_decode(DB::row('SELECT `state` FROM `PokerTables` WHERE `tableId` = ?', 'stdClass', 'i', $id) -> state, true);
            $round = new PokerRound($state);
            while (!$round -> finished()) {
                $turn = $round -> snapshot()['turn'];
                $move = $round -> actor()['userId'] === $user ? ($round -> legal($turn)['canCheck'] ? 'check' : 'call') : 'fold';
                $round -> act($turn, $move, 0, time());
            }
            $other = $this -> join(1)[0];
            DB::run('UPDATE `PokerTables` SET `state` = ?, `deadline` = 0 WHERE `tableId` = ?', 'si', json_encode($round -> snapshot()), $id);
            $done = Poker::request($user, 'state');
            $this -> assertSame('finished', $done['table']['street']);
            $this -> assertTrue($done['wallet']['balance'] >= 1000);
            $this -> assertTrue($done['matchAt'] >= time() + Poker::WAIT_SECONDS);
            $next = $this -> match([$user, $other]);
            $this -> assertTrue($next['table']['tableId'] !== $id);
            $humans = array_filter($next['table']['seats'], static fn (array $seat): bool => $seat['userId'] !== null);
            $this -> assertSame(2, count($humans));
        } finally { $this -> cleanup(); }
    }
}
