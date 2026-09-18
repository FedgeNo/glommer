<?php

declare(strict_types=1);

class Singularity
{
    public const GAME = 'singularity';
    public const STAKES = [10, 20, 50, 100, 250, 500];
    public const SIZE = 5;
    public const MAX_CASCADES = 12;
    public const SYMBOLS = [
        ['name' => 'Plasma', 'glyph' => 'ϟ', 'pays' => [5 => 22, 7 => 44, 9 => 88, 12 => 220]],
        ['name' => 'Turbine', 'glyph' => '⚙', 'pays' => [5 => 26, 7 => 52, 9 => 104, 12 => 260]],
        ['name' => 'Fuel Cell', 'glyph' => '⬡', 'pays' => [5 => 31, 7 => 62, 9 => 124, 12 => 310]],
        ['name' => 'Atom', 'glyph' => '⚛', 'pays' => [5 => 35, 7 => 70, 9 => 140, 12 => 350]],
        ['name' => 'Reactor', 'glyph' => '☢', 'pays' => [5 => 40, 7 => 80, 9 => 160, 12 => 400]],
        ['name' => 'Singularity', 'glyph' => '✹', 'pays' => [5 => 44, 7 => 88, 9 => 176, 12 => 440]],
    ];

    public static function validate(mixed $stake): int
    {
        if (!is_int($stake) || !in_array($stake, self::STAKES, true)) throw new \InvalidArgumentException('Choose a listed whole-chip stake.');
        return $stake;
    }

    public static function clusters(array $grid): array
    {
        if (!array_is_list($grid) || count($grid) !== self::SIZE) throw new \InvalidArgumentException('Expected a five by five board.');
        foreach ($grid as $column) {
            if (!is_array($column) || !array_is_list($column) || count($column) !== self::SIZE) throw new \InvalidArgumentException('Expected a five by five board.');
            foreach ($column as $symbol) if (!is_int($symbol) || !isset(self::SYMBOLS[$symbol])) throw new \InvalidArgumentException('Invalid symbol.');
        }
        $seen = []; $clusters = [];
        for ($x = 0; $x < self::SIZE; $x++) for ($y = 0; $y < self::SIZE; $y++) {
            if (isset($seen[$x][$y])) continue;
            $symbol = $grid[$x][$y]; $cells = []; $queue = [[$x, $y]]; $seen[$x][$y] = true;
            while ($queue) {
                [$cx, $cy] = array_pop($queue); $cells[] = [$cx, $cy];
                foreach ([[$cx - 1, $cy], [$cx + 1, $cy], [$cx, $cy - 1], [$cx, $cy + 1]] as [$nx, $ny]) {
                    if (isset($grid[$nx][$ny]) && !isset($seen[$nx][$ny]) && $grid[$nx][$ny] === $symbol) {
                        $seen[$nx][$ny] = true; $queue[] = [$nx, $ny];
                    }
                }
            }
            if (count($cells) >= 5) $clusters[] = ['symbol' => $symbol, 'count' => count($cells), 'cells' => $cells];
        }
        return $clusters;
    }

    public static function refill(array $grid, array $clusters, callable $draw): array
    {
        foreach ($clusters as $cluster) foreach ($cluster['cells'] as [$x, $y]) unset($grid[$x][$y]);
        foreach ($grid as &$column) {
            $column = array_values($column); $fresh = [];
            while (count($fresh) + count($column) < self::SIZE) $fresh[] = $draw();
            $column = array_merge($fresh, $column);
        }
        return $grid;
    }

    public static function round(int $stake, ?callable $draw = null): array
    {
        self::validate($stake);
        $draw ??= static fn(): int => random_int(0, count(self::SYMBOLS) - 1);
        $grid = self::refill(array_fill(0, self::SIZE, []), [], $draw);
        $steps = []; $wins = []; $payout = 0;
        for ($multiplier = 1; $multiplier <= self::MAX_CASCADES; $multiplier++) {
            $clusters = self::clusters($grid);
            if (!$clusters) break;
            $returned = 0;
            foreach ($clusters as &$cluster) {
                $units = 0;
                foreach (self::SYMBOLS[$cluster['symbol']]['pays'] as $size => $pay) if ($cluster['count'] >= $size) $units = $pay;
                $cluster['payout'] = intdiv($stake, 10) * $units * $multiplier;
                $returned += $cluster['payout'];
                $wins[] = $cluster + ['cascade' => $multiplier];
            }
            unset($cluster);
            $steps[] = ['grid' => $grid, 'clusters' => $clusters, 'multiplier' => $multiplier, 'payout' => $returned];
            $payout += $returned;
            // The twelfth paid cascade completes the reactor cycle without another draw.
            if ($multiplier < self::MAX_CASCADES) $grid = self::refill($grid, $clusters, $draw);
        }
        return ['version' => 2, 'grid' => $grid, 'cascades' => $steps, 'wins' => $wins,
            'multiplier' => max(1, count($steps)), 'wager' => $stake, 'payout' => $payout, 'net' => $payout - $stake];
    }

    public static function spin(int $wallet_id, string $request_key, mixed $stake): array
    {
        $stake = self::validate($stake);
        return GameWallet::play($wallet_id, $request_key, self::GAME, ['stake' => $stake], $stake,
            static fn(): array => self::round($stake));
    }
}
