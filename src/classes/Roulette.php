<?php

declare(strict_types=1);

/** European single-zero roulette: uniform pockets, standard full-loss zero. */
class Roulette
{
    public const WHEEL = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
    public const RED = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    public const MAX_WAGER = 10000;

    /** Every supported position is defined here; the browser cannot supply odds. */
    public static function positions(): array
    {
        static $positions = null;
        if ($positions !== null) return $positions;
        $positions = [];
        $add = static function (string $key, string $label, array $numbers, int $odds) use (&$positions): void {
            $positions[$key] = ['label' => $label, 'numbers' => $numbers, 'odds' => $odds];
        };
        for ($n = 0; $n <= 36; $n++) $add('straight:' . $n, (string) $n, [$n], 35);
        $add('red', 'Red', self::RED, 1);
        $add('black', 'Black', array_values(array_diff(range(1, 36), self::RED)), 1);
        $add('odd', 'Odd', range(1, 35, 2), 1);
        $add('even', 'Even', range(2, 36, 2), 1);
        $add('low', '1–18', range(1, 18), 1);
        $add('high', '19–36', range(19, 36), 1);
        for ($n = 1; $n <= 3; $n++) {
            $add('dozen:' . $n, 'Dozen ' . $n, range(($n - 1) * 12 + 1, $n * 12), 2);
            $add('column:' . $n, 'Column ' . $n, range($n, 36, 3), 2);
        }
        for ($n = 1; $n <= 36; $n++) {
            if ($n % 3 !== 0) $add('split:' . $n . '-' . ($n + 1), 'Split ' . $n . ' / ' . ($n + 1), [$n, $n + 1], 17);
            if ($n <= 33) $add('split:' . $n . '-' . ($n + 3), 'Split ' . $n . ' / ' . ($n + 3), [$n, $n + 3], 17);
            if ($n % 3 === 1) {
                $add('street:' . $n, 'Street ' . $n . '–' . ($n + 2), range($n, $n + 2), 11);
                if ($n <= 31) $add('sixline:' . $n, 'Six line ' . $n . '–' . ($n + 5), range($n, $n + 5), 5);
            }
            if ($n <= 32 && $n % 3 !== 0) $add('corner:' . $n, 'Corner ' . $n . ' / ' . ($n + 1) . ' / ' . ($n + 3) . ' / ' . ($n + 4), [$n, $n + 1, $n + 3, $n + 4], 8);
        }
        for ($n = 1; $n <= 3; $n++) $add('split:0-' . $n, 'Split 0 / ' . $n, [0, $n], 17);
        $add('trio:1', 'Trio 0 / 1 / 2', [0, 1, 2], 11);
        $add('trio:2', 'Trio 0 / 2 / 3', [0, 2, 3], 11);
        $add('first-four', 'First four: 0 / 1 / 2 / 3', [0, 1, 2, 3], 8);
        return $positions;
    }

    public static function validate(mixed $bets): array
    {
        if (!is_array($bets) || $bets === [] || count($bets) > count(self::positions())) {
            throw new \InvalidArgumentException('Place at least one bet.');
        }
        $total = 0;
        foreach ($bets as $position => $amount) {
            if (!is_string($position) || !isset(self::positions()[$position]) || !is_int($amount) || $amount < 1 || $amount > self::MAX_WAGER) {
                throw new \InvalidArgumentException('Choose a valid position and a whole-chip stake.');
            }
            $total += $amount;
        }
        if ($total > self::MAX_WAGER) throw new \InvalidArgumentException('The table limit is 10,000 chips per spin.');
        ksort($bets);
        return $bets;
    }

    public static function settle(array $bets, int $number): array
    {
        $bets = self::validate($bets);
        if ($number < 0 || $number > 36) throw new \InvalidArgumentException('Invalid roulette pocket.');
        $payout = 0;
        $wins = [];
        foreach ($bets as $position => $amount) {
            $rule = self::positions()[$position];
            if (in_array($number, $rule['numbers'], true)) {
                $returned = $amount * ($rule['odds'] + 1);
                $payout += $returned;
                $wins[] = ['position' => $position, 'returned' => $returned];
            }
        }
        $wager = array_sum($bets);
        return ['number' => $number, 'color' => self::color($number), 'wager' => $wager,
            'payout' => $payout, 'net' => $payout - $wager, 'wins' => $wins, 'bets' => $bets];
    }

    public static function color(int $number): string
    {
        return $number === 0 ? 'green' : (in_array($number, self::RED, true) ? 'red' : 'black');
    }

    public static function spin(int $wallet_id, string $request_key, mixed $bets): array
    {
        $bets = self::validate($bets);
        return GameWallet::play($wallet_id, $request_key, 'roulette', $bets, array_sum($bets),
            static fn (): array => self::settle($bets, random_int(0, 36)));
    }
}
