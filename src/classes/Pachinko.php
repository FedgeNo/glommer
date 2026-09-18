<?php

declare(strict_types=1);

class Pachinko
{
    public const GAME = 'pachinko';
    public const STAKES = [10, 20, 50, 100, 250, 500];
    public const ROWS = 12;
    public const SYMBOLS = [];
    // Returns in tenths of the total stake, from leftmost pocket to rightmost.
    public const RETURNS = [500, 150, 50, 20, 10, 5, 2, 5, 10, 20, 50, 150, 500];

    public static function validate(mixed $stake): int
    {
        if (!is_int($stake) || !in_array($stake, self::STAKES, true)) throw new \InvalidArgumentException('Choose a listed whole-chip stake.');
        return $stake;
    }

    public static function settle(int $stake, array $path): array
    {
        self::validate($stake);
        if (!array_is_list($path) || count($path) !== self::ROWS) throw new \InvalidArgumentException('Expected twelve bounces.');
        foreach ($path as $direction) if ($direction !== 0 && $direction !== 1) throw new \InvalidArgumentException('Invalid bounce.');
        $pocket = array_sum($path);
        $payout = intdiv($stake, 10) * self::RETURNS[$pocket];
        return ['path' => $path, 'pocket' => $pocket, 'multiplier' => self::RETURNS[$pocket] / 10,
            'wager' => $stake, 'payout' => $payout, 'net' => $payout - $stake, 'wins' => []];
    }

    public static function pocketWays(int $pocket): int
    {
        if ($pocket < 0 || $pocket > self::ROWS) throw new \InvalidArgumentException('Invalid pocket.');
        $ways = 1;
        for ($i = 1; $i <= $pocket; $i++) $ways = intdiv($ways * (self::ROWS - $i + 1), $i);
        return $ways;
    }

    public static function returnRate(): float
    {
        $total = 0;
        foreach (self::RETURNS as $pocket => $units) $total += self::pocketWays($pocket) * $units;
        return $total / (10 * 2 ** self::ROWS);
    }

    public static function spin(int $wallet_id, string $request_key, mixed $stake): array
    {
        $stake = self::validate($stake);
        return GameWallet::play($wallet_id, $request_key, self::GAME, ['stake' => $stake], $stake, static function () use ($stake): array {
            $path = [];
            for ($row = 0; $row < self::ROWS; $row++) $path[] = random_int(0, 1);
            return self::settle($stake, $path);
        });
    }
}
