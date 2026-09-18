<?php

declare(strict_types=1);

/** Shared play-only chip accounting for casino bets and fixed-price arcade plays. */
class GameWallet
{
    public const GRANT = 1000;
    public const INTERVAL = 3600;
    private const COOKIE = 'gameGuest';
    private const MAX_BALANCE = 9000000000000000;

    public static function current(): int
    {
        if (Auth::check()) {
            self::claimGuest((int) Auth::id());
            return self::forUser((int) Auth::id());
        }
        $hash = self::guestHash();
        if ($hash !== null) {
            $row = DB::row('SELECT `walletId` FROM `GameWallets` WHERE `guestHash` = ? AND `mergedAt` IS NULL', 'stdClass', 's', $hash);
            if ($row !== null) return (int) $row -> walletId;
        }
        $rate_key = 'game-guest:' . (ServerURL::clientIP() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($rate_key, 20, 3600)) {
            throw new \DomainException('Too many guest wallets. Sign in to continue with your saved chips.');
        }
        RateLimiter::recordAttempt($rate_key);
        $token = bin2hex(random_bytes(32));
        $wallet_id = self::createGuest(hash('sha256', $token));
        setcookie(Cookie::name(self::COOKIE), $token, self::cookieOptions(time() + 31536000));
        $_COOKIE[Cookie::name(self::COOKIE)] = $token;
        return $wallet_id;
    }

    private static function cookieOptions(int $expires): array
    {
        return ['expires' => $expires, 'path' => '/', 'secure' => ServerURL::isHTTPS(), 'httponly' => true, 'samesite' => 'Lax'];
    }

    private static function guestHash(): ?string
    {
        $token = Cookie::get(self::COOKIE);
        return $token !== null && preg_match('/^[a-f0-9]{64}$/D', $token) === 1 ? hash('sha256', $token) : null;
    }

    public static function createGuest(string $hash): int
    {
        DB::run('INSERT INTO `GameWallets` (`guestHash`) VALUES (?)', 's', $hash);
        return (int) mysqli_insert_id(DB::connection());
    }

    public static function forUser(int $user_id): int
    {
        DB::run('INSERT INTO `GameWallets` (`userId`) VALUES (?) ON DUPLICATE KEY UPDATE `userId` = VALUES(`userId`)', 'i', $user_id);
        return (int) DB::row('SELECT `walletId` FROM `GameWallets` WHERE `userId` = ?', 'stdClass', 'i', $user_id) -> walletId;
    }

    /** Called on completed sign-in, including signup and Google/2FA sign-in. */
    public static function claimGuest(int $user_id): void
    {
        $hash = self::guestHash();
        if ($hash === null) return;
        $guest = DB::row('SELECT `walletId` FROM `GameWallets` WHERE `guestHash` = ?', 'stdClass', 's', $hash);
        if ($guest !== null) self::merge((int) $guest -> walletId, self::forUser($user_id));
        setcookie(Cookie::name(self::COOKIE), '', self::cookieOptions(time() - 3600));
        unset($_COOKIE[Cookie::name(self::COOKIE)]);
    }

    public static function merge(int $guest_id, int $account_id): void
    {
        if ($guest_id === $account_id) throw new \InvalidArgumentException('Cannot merge a wallet into itself.');
        DB::transaction(static function () use ($guest_id, $account_id): void {
            $rows = DB::rows('SELECT * FROM `GameWallets` WHERE `walletId` IN (?, ?) ORDER BY `walletId` FOR UPDATE', 'stdClass', 'ii', $guest_id, $account_id);
            $by_id = [];
            foreach ($rows as $row) $by_id[(int) $row -> walletId] = $row;
            $guest = $by_id[$guest_id] ?? null;
            $account = $by_id[$account_id] ?? null;
            if ($guest === null || $account === null || $guest -> userId !== null || $account -> userId === null) {
                throw new \DomainException('These wallets cannot be combined.');
            }
            if ($guest -> mergedAt !== null) return;
            $balance = (int) $account -> balance + (int) $guest -> balance;
            self::checkBalance($balance);
            $last = max((int) $guest -> lastGrantAt, (int) $account -> lastGrantAt) ?: null;
            DB::run('UPDATE `GameWallets` SET `balance` = ?, `lastGrantAt` = ? WHERE `walletId` = ?', 'iii', $balance, $last, $account_id);
            DB::run('UPDATE `GamePlays` SET `walletId` = ? WHERE `walletId` = ?', 'ii', $account_id, $guest_id);
            DB::run('UPDATE `GameWallets` SET `balance` = 0, `mergedAt` = NOW() WHERE `walletId` = ?', 'i', $guest_id);
        });
    }

    private static function locked(int $wallet_id): object
    {
        DB::requireTransaction();
        $row = DB::row('SELECT * FROM `GameWallets` WHERE `walletId` = ? FOR UPDATE', 'stdClass', 'i', $wallet_id);
        if ($row === null || $row -> mergedAt !== null) throw new \DomainException('Your wallet changed. Refresh the page to continue.');
        return $row;
    }

    public static function grantDue(?int $last_grant_at, int $now): bool
    {
        return $last_grant_at === null || $now >= $last_grant_at + self::INTERVAL;
    }

    private static function grant(object $wallet, int $now): int
    {
        if (!self::grantDue($wallet -> lastGrantAt === null ? null : (int) $wallet -> lastGrantAt, $now)) return 0;
        $wallet -> balance = (int) $wallet -> balance + self::GRANT;
        self::checkBalance($wallet -> balance);
        $wallet -> lastGrantAt = $now;
        DB::run('UPDATE `GameWallets` SET `balance` = ?, `lastGrantAt` = ? WHERE `walletId` = ?', 'iii', $wallet -> balance, $now, (int) $wallet -> walletId);
        return self::GRANT;
    }

    private static function payload(object $wallet, int $now, int $granted = 0): array
    {
        return ['balance' => (int) $wallet -> balance, 'nextGrantAt' => (int) $wallet -> lastGrantAt + self::INTERVAL,
            'serverTime' => $now, 'granted' => $granted, 'guest' => $wallet -> userId === null];
    }

    public static function visit(int $wallet_id): array
    {
        return DB::transaction(static function () use ($wallet_id): array {
            $wallet = self::locked($wallet_id);
            $now = time();
            $granted = self::grant($wallet, $now);
            $payload = self::payload($wallet, $now, $granted);
            $rows = DB::rows('SELECT `result` FROM `GamePlays` WHERE `walletId` = ? AND `game` = ? ORDER BY `createdAt` DESC, `requestKey` DESC LIMIT 12', 'stdClass', 'is', $wallet_id, 'roulette');
            $payload['history'] = array_map(static fn (object $row): array => json_decode($row -> result, true, 512, JSON_THROW_ON_ERROR), $rows);
            return $payload;
        });
    }

    /** The caller supplies server-owned rules, never a browser-supplied payout. */
    public static function play(int $wallet_id, string $request_key, string $game, array $request, int $wager, callable $outcome): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $request_key) !== 1) throw new \InvalidArgumentException('Invalid play identifier.');
        if ($wager < 1 || $wager > Roulette::MAX_WAGER) throw new \InvalidArgumentException('Invalid chip cost.');
        $hash = hash('sha256', json_encode([$game, $request], JSON_THROW_ON_ERROR));
        return DB::transaction(static function () use ($wallet_id, $request_key, $game, $hash, $wager, $outcome): array {
            $wallet = self::locked($wallet_id);
            $receipt = DB::row('SELECT * FROM `GamePlays` WHERE `requestKey` = ?', 'stdClass', 's', $request_key);
            $now = time();
            if ($receipt !== null) {
                if ((int) $receipt -> walletId !== $wallet_id || !hash_equals($receipt -> requestHash, $hash)) {
                    throw new \DomainException('That play identifier belongs to a different bet.');
                }
                return ['wallet' => self::payload($wallet, $now), 'round' => json_decode($receipt -> result, true, 512, JSON_THROW_ON_ERROR)];
            }
            $granted = self::grant($wallet, $now);
            if ((int) $wallet -> balance < $wager) throw new \DomainException('Not enough chips. Reduce your bets or wait for your next 1,000 chips.');
            $result = $outcome();
            $payout = $result['payout'] ?? 0;
            if (!is_int($payout) || $payout < 0) throw new \LogicException('Invalid game payout.');
            $wallet -> balance = (int) $wallet -> balance - $wager + $payout;
            self::checkBalance($wallet -> balance);
            $result['requestKey'] = $request_key;
            DB::run('INSERT INTO `GamePlays` (`requestKey`, `walletId`, `game`, `requestHash`, `wager`, `payout`, `result`) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'sissiis', $request_key, $wallet_id, $game, $hash, $wager, $payout, json_encode($result, JSON_THROW_ON_ERROR));
            DB::run('UPDATE `GameWallets` SET `balance` = ? WHERE `walletId` = ?', 'ii', $wallet -> balance, $wallet_id);
            return ['wallet' => self::payload($wallet, $now, $granted), 'round' => $result];
        });
    }

    /** Arcade games can charge a fixed server-chosen entry price without wagering. */
    public static function spend(int $wallet_id, string $request_key, string $game, int $cost): array
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,30}$/D', $game) !== 1) throw new \InvalidArgumentException('Invalid arcade game.');
        return self::play($wallet_id, $request_key, 'arcade:' . $game, ['cost' => $cost], $cost,
            static fn (): array => ['admitted' => true, 'cost' => $cost, 'payout' => 0]);
    }

    /** Settle a previously paid arcade admission once, under the same wallet lock. */
    public static function finish(int $wallet_id, string $request_key, string $game, array $report, callable $outcome): array
    {
        return DB::transaction(static function () use ($wallet_id, $request_key, $game, $report, $outcome): array {
            $wallet = self::locked($wallet_id);
            $receipt = DB::row('SELECT * FROM `GamePlays` WHERE `requestKey` = ? AND `walletId` = ? AND `game` = ?', 'stdClass', 'sis', $request_key, $wallet_id, $game);
            if ($receipt === null) throw new \DomainException('This race has no paid admission.');
            $result = json_decode($receipt -> result, true, 512, JSON_THROW_ON_ERROR);
            $hash = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR));
            if (isset($result['settlementHash'])) {
                if (!hash_equals($result['settlementHash'], $hash)) throw new \DomainException('This race already has a different result.');
            } else {
                $payout = $outcome($result);
                if (!is_int($payout) || $payout < 0) throw new \LogicException('Invalid game payout.');
                $wallet -> balance = (int) $wallet -> balance + $payout;
                self::checkBalance($wallet -> balance);
                $result['payout'] = $payout;
                $result['settlementHash'] = $hash;
                DB::run('UPDATE `GamePlays` SET `payout` = ?, `result` = ? WHERE `requestKey` = ?', 'iss', $payout, json_encode($result, JSON_THROW_ON_ERROR), $request_key);
                DB::run('UPDATE `GameWallets` SET `balance` = ? WHERE `walletId` = ?', 'ii', $wallet -> balance, $wallet_id);
            }
            return ['wallet' => self::payload($wallet, time()), 'round' => $result];
        });
    }

    /** Table games move stakes and returns inside their own atomic hand transaction. */
    public static function tableTransfer(int $wallet_id, string $key, int $cost, int $returned, array $result): void
    {
        DB::requireTransaction();
        if ($cost < 0 || $returned < 0 || preg_match('/^[a-f0-9]{32}$/D', $key) !== 1) throw new \LogicException('Invalid table transfer.');
        $wallet = self::locked($wallet_id);
        $hash = hash('sha256', json_encode([$cost, $returned, $result], JSON_THROW_ON_ERROR));
        $receipt = DB::row('SELECT `walletId`, `requestHash` FROM `GamePlays` WHERE `requestKey` = ?', 'stdClass', 's', $key);
        if ($receipt !== null) {
            if ((int) $receipt -> walletId !== $wallet_id || !hash_equals($hash, $receipt -> requestHash)) throw new \LogicException('Conflicting table transfer.');
            return;
        }
        if ((int) $wallet -> balance < $cost) throw new \DomainException('Not enough chips for this seat.');
        $balance = (int) $wallet -> balance - $cost + $returned;
        self::checkBalance($balance);
        DB::run('INSERT INTO `GamePlays` (`requestKey`, `walletId`, `game`, `requestHash`, `wager`, `payout`, `result`) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'sissiis', $key, $wallet_id, 'poker', $hash, $cost, $returned, json_encode($result, JSON_THROW_ON_ERROR));
        DB::run('UPDATE `GameWallets` SET `balance` = ? WHERE `walletId` = ?', 'ii', $balance, $wallet_id);
    }

    private static function checkBalance(int $balance): void
    {
        if ($balance < 0 || $balance > self::MAX_BALANCE) throw new \DomainException('The chip balance is outside the supported range.');
    }
}
