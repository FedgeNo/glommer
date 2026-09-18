<?php

declare(strict_types=1);

/** Fixed reel strips and paytable; the browser only chooses the total stake. */
class VLT
{
    public const GAME = 'vlt';
    public const STAKES = [10, 20, 50, 100, 250, 500];
    public const SYMBOLS = [
        ['name' => 'Ion', 'glyph' => 'ϟ', 'weight' => 10, 'pays' => [3 => 5, 4 => 14, 5 => 45]],
        ['name' => 'Orbit', 'glyph' => '◎', 'weight' => 8, 'pays' => [3 => 8, 4 => 24, 5 => 80]],
        ['name' => 'Prism', 'glyph' => '◆', 'weight' => 6, 'pays' => [3 => 12, 4 => 40, 5 => 150]],
        ['name' => 'Star', 'glyph' => '★', 'weight' => 4, 'pays' => [3 => 20, 4 => 80, 5 => 300]],
        ['name' => 'Crown', 'glyph' => '♛', 'weight' => 3, 'pays' => [3 => 40, 4 => 160, 5 => 600]],
        ['name' => 'Nova Seven', 'glyph' => '7', 'weight' => 1, 'pays' => [3 => 200, 4 => 1000, 5 => 5000]],
    ];
    public const LINES = [[1,1,1,1,1], [0,0,0,0,0], [2,2,2,2,2], [0,1,2,1,0], [2,1,0,1,2],
        [0,0,1,2,2], [2,2,1,0,0], [1,0,0,0,1], [1,2,2,2,1], [0,1,1,1,0]];

    public static function validate(mixed $stake): int
    {
        if (!is_int($stake) || !in_array($stake, static::STAKES, true)) throw new \InvalidArgumentException('Choose a listed whole-chip stake.');
        return $stake;
    }

    public static function strips(): array
    {
        $base = [];
        for ($pass = 0; $pass < 10; $pass++) foreach (static::SYMBOLS as $symbol => $rule) if ($rule['weight'] > $pass) $base[] = $symbol;
        $strips = [];
        for ($reel = 0; $reel < 5; $reel++) {
            $strip = [];
            for ($stop = 0; $stop < 32; $stop++) $strip[] = $base[($stop * ($reel * 2 + 1) + $reel * 7) % 32];
            $strips[] = $strip;
        }
        return $strips;
    }

    public static function grid(array $stops): array
    {
        if (!array_is_list($stops) || count($stops) !== 5) throw new \InvalidArgumentException('Expected five reel stops.');
        $grid = [];
        foreach (static::strips() as $reel => $strip) {
            if (!is_int($stops[$reel]) || $stops[$reel] < 0 || $stops[$reel] >= 32) throw new \InvalidArgumentException('Invalid reel stop.');
            $grid[] = [$strip[$stops[$reel]], $strip[($stops[$reel] + 1) % 32], $strip[($stops[$reel] + 2) % 32]];
        }
        return $grid;
    }

    public static function settle(int $stake, array $stops, int $multiplier): array
    {
        static::validate($stake);
        if (!in_array($multiplier, [1, 2, 5], true)) throw new \InvalidArgumentException('Invalid multiplier.');
        $grid = static::grid($stops); $wins = []; $payout = 0;
        foreach (static::LINES as $line => $rows) {
            $symbol = $grid[0][$rows[0]]; $count = 1;
            while ($count < 5 && $grid[$count][$rows[$count]] === $symbol) $count++;
            if ($count < 3) continue;
            $returned = intdiv($stake, count(static::LINES)) * static::SYMBOLS[$symbol]['pays'][$count] * $multiplier;
            $wins[] = ['line' => $line, 'symbol' => $symbol, 'count' => $count, 'payout' => $returned];
            $payout += $returned;
        }
        return ['stops' => $stops, 'grid' => $grid, 'multiplier' => $multiplier, 'wins' => $wins,
            'wager' => $stake, 'payout' => $payout, 'net' => $payout - $stake];
    }

    public static function returnRate(): float
    {
        $return = 0.0;
        foreach (static::SYMBOLS as $rule) {
            $p = $rule['weight'] / 32;
            $return += $p ** 3 * (1 - $p) * $rule['pays'][3] + $p ** 4 * (1 - $p) * $rule['pays'][4] + $p ** 5 * $rule['pays'][5];
        }
        return $return * (90 + 8 * 2 + 2 * 5) / 100;
    }

    public static function spin(int $wallet_id, string $request_key, mixed $stake): array
    {
        $stake = static::validate($stake);
        return GameWallet::play($wallet_id, $request_key, static::GAME, ['stake' => $stake], $stake, static function () use ($stake): array {
            $stops = []; for ($reel = 0; $reel < 5; $reel++) $stops[] = random_int(0, 31);
            $feature = random_int(1, 100);
            return static::settle($stake, $stops, $feature <= 90 ? 1 : ($feature <= 98 ? 2 : 5));
        });
    }
}
