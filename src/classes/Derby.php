<?php

declare(strict_types=1);

/** Glommer accounting for the independently maintained browser game. */
class Derby
{
    public const LIFE = 500;
    private const VEHICLES = ['car', 'truck', 'van', 'buggy', 'muscle'];
    private const UPGRADES = ['engine', 'armor', 'ram', 'grip'];

    public static function career(int $wallet_id): array
    {
        $upgrades = array_fill_keys(self::VEHICLES, array_fill_keys(self::UPGRADES, 0));
        $rows = DB::rows('SELECT `result` FROM `GamePlays` WHERE `walletId` = ? AND `game` = ?', 'stdClass', 'is', $wallet_id, 'derby:upgrade');
        foreach ($rows as $row) {
            $result = json_decode($row -> result, true, 512, JSON_THROW_ON_ERROR);
            $vehicle = $result['vehicle']; $key = $result['key'];
            $upgrades[$vehicle][$key] = max($upgrades[$vehicle][$key], $result['level']);
        }
        return $upgrades;
    }

    public static function enter(int $wallet_id, string $key, string $mode): array
    {
        if (!in_array($mode, ['standard', 'elimination', 'trial'], true)) throw new \InvalidArgumentException('Unknown race format.');
        return GameWallet::play($wallet_id, $key, 'derby:race', ['mode' => $mode], self::LIFE,
            static fn (): array => ['startedAt' => time(), 'mode' => $mode, 'cost' => self::LIFE, 'payout' => 0]);
    }

    public static function purchase(int $wallet_id, string $key, string $vehicle, string $upgrade, int $level): array
    {
        if (!in_array($vehicle, self::VEHICLES, true) || !in_array($upgrade, self::UPGRADES, true) || $level < 0 || $level >= 5) {
            throw new \InvalidArgumentException('Invalid upgrade.');
        }
        return GameWallet::play($wallet_id, $key, 'derby:upgrade', ['vehicle' => $vehicle, 'key' => $upgrade, 'level' => $level], 200 + $level * 250,
            static function () use ($wallet_id, $vehicle, $upgrade, $level): array {
                if (self::career($wallet_id)[$vehicle][$upgrade] !== $level) throw new \DomainException('Your upgrades changed. Refresh the garage.');
                return ['vehicle' => $vehicle, 'key' => $upgrade, 'level' => $level + 1, 'payout' => 0];
            });
    }

    /** Same modest contribution rates as Economy.reward in the source game. */
    public static function reward(array $report): int
    {
        foreach (['place', 'bonus', 'hits', 'drift', 'wrecks', 'fatalities'] as $field) {
            if (!isset($report[$field]) || !is_int($report[$field]) || $report[$field] < 0 || $report[$field] > 1000000000) throw new \InvalidArgumentException('Invalid race result.');
        }
        foreach (['won', 'raceLoss'] as $field) {
            if (!isset($report[$field]) || !is_bool($report[$field])) throw new \InvalidArgumentException('Invalid race result.');
        }
        $place = $report['place'];
        if ($place < 1 || $report['won'] !== ($place === 1) || $report['raceLoss'] !== ($place > 3)
            || $report['fatalities'] > $report['wrecks']) throw new \InvalidArgumentException('Inconsistent race result.');
        if ($place > 3) return 0;
        $bonus = $place === 1 ? [3000, 6000] : ($place === 2 ? [3000] : [1500]);
        if (!in_array($report['bonus'], $bonus, true)) throw new \InvalidArgumentException('Invalid finish bonus.');
        $multiplier = [1 => 1, 2 => .5, 3 => .25][$place];
        return (int) round(($report['hits'] + $report['wrecks'] * 20 + $report['fatalities'] * 10) * $multiplier)
            + (int) round($report['bonus'] * .025) + ($report['won'] ? 100 + self::LIFE : 0)
            + (int) floor(5 * log(1 + $report['drift'] / 1000, 2));
    }

    public static function finish(int $wallet_id, string $key, array $report): array
    {
        $payout = self::reward($report);
        return GameWallet::finish($wallet_id, $key, 'derby:race', $report, static function (array $race) use ($report, $payout): int {
            $elapsed = time() - $race['startedAt'];
            // These are plausibility checks, not authoritative physics verification.
            // The simulation and its finish position are still client-reported.
            if ($race['mode'] === 'trial' || $elapsed < 10 || $report['hits'] > $elapsed * 4
                || $report['wrecks'] > 30 || $report['drift'] > $elapsed * 10000) throw new \DomainException('This result does not match the admitted race.');
            return $payout;
        });
    }
}
