<?php

declare(strict_types=1);

class GameWalletTest extends DatabaseTestCase
{
    private function guest(): int { return GameWallet::createGuest(hash('sha256', random_bytes(32))); }
    private function balance(int $wallet): int { return (int) DB::row('SELECT `balance` FROM `GameWallets` WHERE `walletId` = ?', 'stdClass', 'i', $wallet) -> balance; }
    private function remove(int $wallet): void { DB::run('DELETE FROM `GameWallets` WHERE `walletId` = ?', 'i', $wallet); }

    public function testVLTReceiptReplaysTheSameReelsAndCreditsOnlyOnce(): void
    {
        $wallet = $this -> guest(); $key = bin2hex(random_bytes(16));
        try {
            GameWallet::visit($wallet);
            $first = VLT::spin($wallet, $key, 20);
            $this -> assertSame(VLT::grid($first['round']['stops']), $first['round']['grid']);
            $this -> assertSame(980 + $first['round']['payout'], $this -> balance($wallet));
            $again = VLT::spin($wallet, $key, 20);
            $this -> assertSame($first['round'], $again['round']);
            $this -> assertSame($first['wallet']['balance'], $this -> balance($wallet));
            $failed = false;
            try { VLT::spin($wallet, $key, 50); } catch (\DomainException) { $failed = true; }
            $this -> assertTrue($failed);
        } finally { $this -> remove($wallet); }
    }

    public function testSingularityReceiptsCannotBeReplayedAsNovaVault(): void
    {
        $wallet = $this -> guest(); $key = bin2hex(random_bytes(16));
        try {
            GameWallet::visit($wallet);
            $first = Singularity::spin($wallet, $key, 20);
            $again = Singularity::spin($wallet, $key, 20);
            $this -> assertSame($first['round'], $again['round']);
            $this -> assertSame(980 + $first['round']['payout'], $this -> balance($wallet));
            $row = DB::row('SELECT `game` FROM `GamePlays` WHERE `requestKey` = ?', 'stdClass', 's', $key);
            $this -> assertSame('singularity', $row -> game);
            $rejected = false;
            try { VLT::spin($wallet, $key, 20); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
            $this -> assertSame($first['wallet']['balance'], $this -> balance($wallet));
        } finally { $this -> remove($wallet); }
    }

    public function testPachinkoRecoveryKeepsTheSamePathAndChargesOnlyOnce(): void
    {
        $wallet = $this -> guest(); $key = bin2hex(random_bytes(16));
        try {
            GameWallet::visit($wallet);
            $first = Pachinko::spin($wallet, $key, 20);
            $again = Pachinko::spin($wallet, $key, 20);
            $this -> assertSame($first['round'], $again['round']);
            $this -> assertSame(980 + $first['round']['payout'], $this -> balance($wallet));
            $this -> assertSame(array_sum($first['round']['path']), $first['round']['pocket']);
            foreach (['stake', 'game'] as $mismatch) {
                $rejected = false;
                try {
                    if ($mismatch === 'stake') Pachinko::spin($wallet, $key, 50);
                    else VLT::spin($wallet, $key, 20);
                } catch (\DomainException) { $rejected = true; }
                $this -> assertTrue($rejected);
            }
            $this -> assertSame($first['wallet']['balance'], $this -> balance($wallet));
        } finally { $this -> remove($wallet); }
    }

    public function testVisitGrantsOnceAndLateReturnStartsANewHour(): void
    {
        $wallet = $this -> guest();
        try {
            $first = GameWallet::visit($wallet);
            $this -> assertSame(1000, $first['balance']);
            $this -> assertSame(1000, $first['granted']);
            $this -> assertSame(0, GameWallet::visit($wallet)['granted']);
            DB::run('UPDATE `GameWallets` SET `lastGrantAt` = ? WHERE `walletId` = ?', 'ii', time() - 7200, $wallet);
            $late = GameWallet::visit($wallet);
            $this -> assertSame(2000, $late['balance']);
            $this -> assertSame($late['serverTime'] + 3600, $late['nextGrantAt']);
            $this -> assertSame(0, GameWallet::visit($wallet)['granted']);
        } finally { $this -> remove($wallet); }
    }

    public function testRetryUsesTheCommittedResultAndNeverChargesOrDrawsAgain(): void
    {
        $wallet = $this -> guest(); $key = bin2hex(random_bytes(16));
        try {
            GameWallet::visit($wallet);
            $draws = 0;
            $outcome = static function () use (&$draws): array { $draws++; return Roulette::settle(['straight:7' => 10], 7); };
            $first = GameWallet::play($wallet, $key, 'roulette', ['straight:7' => 10], 10, $outcome);
            $again = GameWallet::play($wallet, $key, 'roulette', ['straight:7' => 10], 10, $outcome);
            $this -> assertSame($first['round'], $again['round']);
            $this -> assertSame(1, $draws);
            $this -> assertSame(1350, $this -> balance($wallet));
            $rejected = false;
            try { Roulette::spin($wallet, $key, ['red' => 10]); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
            $this -> assertSame(1350, $this -> balance($wallet));
            $this -> assertSame(1, count(GameWallet::visit($wallet)['history']));
        } finally { $this -> remove($wallet); }
    }

    public function testInsufficientFundsAndFailedSettlementLeaveNoChargeOrReceipt(): void
    {
        $wallet = $this -> guest();
        try {
            GameWallet::visit($wallet);
            foreach ([1001, 10] as $cost) {
                $key = bin2hex(random_bytes(16)); $rejected = false;
                try {
                    GameWallet::play($wallet, $key, 'test', [], $cost, static function (): array { throw new \DomainException('settlement failed'); });
                } catch (\DomainException) { $rejected = true; }
                $this -> assertTrue($rejected);
                $this -> assertSame(1000, $this -> balance($wallet));
                $this -> assertNull(DB::row('SELECT * FROM `GamePlays` WHERE `requestKey` = ?', 'stdClass', 's', $key));
            }
        } finally { $this -> remove($wallet); }
    }

    public function testAccountClaimsBalanceAndReceiptsOnceWithoutAnotherGrant(): void
    {
        $user = self::createUser(); $guest = $this -> guest(); $account = GameWallet::forUser($user);
        $key = bin2hex(random_bytes(16));
        try {
            GameWallet::visit($account); GameWallet::visit($guest);
            $first = GameWallet::spend($guest, $key, 'pinball', 25);
            GameWallet::merge($guest, $account); GameWallet::merge($guest, $account);
            $this -> assertSame(1975, $this -> balance($account));
            $this -> assertSame(0, $this -> balance($guest));
            $this -> assertSame(0, GameWallet::visit($account)['granted']);
            $retry = GameWallet::spend($account, $key, 'pinball', 25);
            $this -> assertSame($first['round'], $retry['round']);
            $this -> assertSame(1975, $retry['wallet']['balance']);
            $rejected = false;
            try { GameWallet::visit($guest); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
            $this -> assertNull(DB::row('SELECT * FROM `GamePlays` WHERE `requestKey` = ?', 'stdClass', 's', $key));
        } finally {
            $this -> remove($guest); $this -> remove($account);
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
        }
    }

    public function testOtherWalletCannotReplayAReceipt(): void
    {
        $first = $this -> guest(); $second = $this -> guest(); $key = bin2hex(random_bytes(16));
        try {
            GameWallet::spend($first, $key, 'pinball', 25);
            $rejected = false;
            try { GameWallet::spend($second, $key, 'pinball', 25); } catch (\DomainException) { $rejected = true; }
            $this -> assertTrue($rejected);
            $this -> assertSame(0, $this -> balance($second));
            $this -> assertSame(975, $this -> balance($first));
        } finally { $this -> remove($first); $this -> remove($second); }
    }

    public function testConcurrentSpinsWithTheSameKeyChargeAndDrawOnlyOnce(): void
    {
        $wallet = $this -> guest(); $key = bin2hex(random_bytes(16)); $children = [];
        try {
            DB::transaction(function () use ($wallet, $key, &$children): void {
                DB::row('SELECT * FROM `GameWallets` WHERE `walletId` = ? FOR UPDATE', 'stdClass', 'i', $wallet);
                for ($i = 0; $i < 2; $i++) {
                    $code = 'spl_autoload_register(static function ($class) { require getcwd() . "/src/classes/" . $class . ".php"; });'
                        . 'require "src/functions.php"; echo "ready\\n"; flush();'
                        . 'echo json_encode(Roulette::spin(' . $wallet . ', ' . var_export($key, true) . ', ["red" => 25]));';
                    $process = proc_open(['timeout', '10', PHP_BINARY, '-r', $code],
                        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    $children[] = [$process, $pipes];
                    $this -> assertSame("ready\n", fgets($pipes[1]));
                }
            });
            $results = [];
            foreach ($children as [$process, $pipes]) $results[] = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this -> assertSame($results[0]['round'], $results[1]['round']);
            $this -> assertSame(975 + $results[0]['round']['payout'], $this -> balance($wallet));
            $this -> assertSame(1, count(GameWallet::visit($wallet)['history']));
        } finally {
            foreach ($children as [$process, $pipes]) { foreach ($pipes as $pipe) fclose($pipe); proc_close($process); }
            $this -> remove($wallet);
        }
    }
}
