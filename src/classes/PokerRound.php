<?php

declare(strict_types=1);

/** Server-only deck and betting state. Only view() may leave the server. */
class PokerRound
{
    public const SMALL_BLIND = 10;
    public const BIG_BLIND = 20;
    public const TURN_SECONDS = 25;
    private array $state;

    public function __construct(array $state) { $this -> state = $state; }
    public function snapshot(): array { return $this -> state; }
    public function finished(): bool { return $this -> state['street'] === 'finished'; }
    public function deadline(): int { return $this -> state['deadline']; }
    public function actor(): ?array { return $this -> state['turn'] === null ? null : $this -> state['seats'][$this -> state['turn']]; }

    public static function deal(array $players, int $dealer, int $now, ?array $deck = null): self
    {
        if (count($players) < 2 || count($players) > 9 || $dealer < 0 || $dealer >= count($players)) throw new \InvalidArgumentException('Invalid poker table.');
        if ($deck === null) {
            $deck = range(0, 51);
            for ($i = 51; $i > 0; $i--) { $j = random_int(0, $i); [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]]; }
        }
        if (count($deck) !== 52 || count(array_unique($deck)) !== 52 || array_diff($deck, range(0, 51))) throw new \InvalidArgumentException('Invalid deck.');
        $seats = [];
        foreach ($players as $player) {
            if (!is_int($player['stack']) || $player['stack'] < 1) throw new \InvalidArgumentException('Invalid stack.');
            $seats[] = [...$player, 'cards' => [], 'bet' => 0, 'total' => 0, 'folded' => false, 'actedAt' => null, 'action' => '', 'won' => 0];
        }
        for ($pass = 0; $pass < 2; $pass++) for ($offset = 1; $offset <= count($seats); $offset++) $seats[($dealer + $offset) % count($seats)]['cards'][] = array_pop($deck);
        $round = new self(['seats' => $seats, 'dealer' => $dealer, 'deck' => $deck, 'board' => [], 'street' => 'preflop',
            'currentBet' => self::BIG_BLIND, 'minRaise' => self::BIG_BLIND, 'turn' => null, 'deadline' => $now,
            'version' => 1, 'pots' => [], 'log' => [], 'showdown' => false]);
        $small = count($seats) === 2 ? $dealer : ($dealer + 1) % count($seats);
        $big = ($small + 1) % count($seats);
        $round -> commit($small, min(self::SMALL_BLIND, $seats[$small]['stack']));
        $round -> commit($big, min(self::BIG_BLIND, $seats[$big]['stack']));
        $round -> state['seats'][$small]['action'] = 'Small Blind';
        $round -> state['seats'][$big]['action'] = 'Big Blind';
        $round -> advance($big, $now);
        return $round;
    }

    private function commit(int $seat, int $amount): void
    {
        $this -> state['seats'][$seat]['stack'] -= $amount;
        $this -> state['seats'][$seat]['bet'] += $amount;
        $this -> state['seats'][$seat]['total'] += $amount;
    }

    public function legal(int $seat): array
    {
        if ($this -> finished() || $seat !== $this -> state['turn']) return [];
        $player = $this -> state['seats'][$seat];
        $call = min($player['stack'], max(0, $this -> state['currentBet'] - $player['bet']));
        $others = array_filter($this -> state['seats'], static fn (array $p): bool => !$p['folded'] && $p['stack'] > 0);
        $can_raise = count($others) > 1 && ($player['actedAt'] === null || $this -> state['currentBet'] - $player['actedAt'] >= $this -> state['minRaise']);
        return ['call' => $call, 'canCheck' => $call === 0, 'allInCall' => $call === $player['stack'],
            'canRaise' => $can_raise && $player['stack'] > $call,
            'minRaiseTo' => $this -> state['currentBet'] + $this -> state['minRaise'],
            'maxRaiseTo' => $player['bet'] + $player['stack']];
    }

    public function act(int $seat, string $action, int $amount, int $now): void
    {
        $legal = $this -> legal($seat);
        if (!$legal) throw new \DomainException('It is not your turn.');
        $player = $this -> state['seats'][$seat];
        if ($action === 'fold') $this -> state['seats'][$seat]['folded'] = true;
        elseif ($action === 'check') {
            if (!$legal['canCheck']) throw new \DomainException('You must call or fold.');
        } elseif ($action === 'call') $this -> commit($seat, $legal['call']);
        elseif ($action === 'raise') {
            if (!$legal['canRaise'] || $amount <= $this -> state['currentBet'] || $amount > $legal['maxRaiseTo']
                || ($amount < $legal['minRaiseTo'] && $amount !== $legal['maxRaiseTo'])) throw new \DomainException('Choose a legal raise or all-in.');
            $increase = $amount - $this -> state['currentBet'];
            $this -> commit($seat, $amount - $player['bet']);
            if ($increase >= $this -> state['minRaise']) $this -> state['minRaise'] = $increase;
            $this -> state['currentBet'] = $amount;
        } else throw new \InvalidArgumentException('Unknown poker action.');
        $label = ucfirst($action);
        if ($action === 'call') $label .= ' ' . $legal['call'];
        if ($action === 'raise') $label = 'Raise To ' . $amount;
        if (!$this -> state['seats'][$seat]['stack'] && $action !== 'fold') $label .= ' · All In';
        $this -> state['seats'][$seat]['action'] = $label;
        $this -> state['seats'][$seat]['actedAt'] = $this -> state['currentBet'];
        $this -> state['log'][] = $player['name'] . ': ' . $label;
        $this -> state['log'] = array_slice($this -> state['log'], -12);
        $this -> state['version']++;
        $this -> advance($seat, $now);
    }

    private function advance(int $after, int $now): void
    {
        $active = array_filter($this -> state['seats'], static fn (array $p): bool => !$p['folded']);
        if (count($active) === 1) { $this -> settle(false); return; }
        $able = array_filter($active, static fn (array $p): bool => $p['stack'] > 0);
        for ($offset = 1; $offset <= count($this -> state['seats']); $offset++) {
            $index = ($after + $offset) % count($this -> state['seats']);
            $player = $this -> state['seats'][$index];
            if ($player['folded'] || !$player['stack']) continue;
            if ($player['bet'] < $this -> state['currentBet'] || (count($able) > 1 && $player['actedAt'] === null)) {
                $this -> state['turn'] = $index;
                $this -> state['deadline'] = $now + ($player['userId'] === null ? 2 : self::TURN_SECONDS);
                return;
            }
        }
        if ($this -> state['street'] === 'river') { $this -> settle(true); return; }
        array_pop($this -> state['deck']);
        $count = $this -> state['street'] === 'preflop' ? 3 : 1;
        for ($i = 0; $i < $count; $i++) $this -> state['board'][] = array_pop($this -> state['deck']);
        $this -> state['street'] = match ($this -> state['street']) { 'preflop' => 'flop', 'flop' => 'turn', default => 'river' };
        $this -> state['currentBet'] = 0;
        $this -> state['minRaise'] = self::BIG_BLIND;
        foreach ($this -> state['seats'] as &$player) { $player['bet'] = 0; $player['actedAt'] = null; $player['action'] = $player['folded'] ? 'Fold' : ($player['stack'] === 0 ? 'All In' : ''); }
        unset($player);
        $this -> advance($this -> state['dealer'], $now);
    }

    private function settle(bool $showdown): void
    {
        $active = array_keys(array_filter($this -> state['seats'], static fn (array $p): bool => !$p['folded']));
        $ranks = [];
        if ($showdown) foreach ($active as $seat) $ranks[$seat] = PokerHand::rank([...$this -> state['seats'][$seat]['cards'], ...$this -> state['board']]);
        $levels = array_unique(array_column($this -> state['seats'], 'total'));
        sort($levels);
        $previous = 0;
        foreach ($levels as $level) {
            if ($level === 0) continue;
            $contributors = array_keys(array_filter($this -> state['seats'], static fn (array $p): bool => $p['total'] >= $level));
            $amount = ($level - $previous) * count($contributors);
            $previous = $level;
            $eligible = array_values(array_intersect($contributors, $active));
            if (count($contributors) === 1) $winners = $contributors;
            elseif (!$showdown) $winners = $active;
            else {
                $best = max(array_intersect_key($ranks, array_flip($eligible)));
                $winners = array_values(array_filter($eligible, static fn (int $seat): bool => $ranks[$seat] === $best));
            }
            $n = count($this -> state['seats']); $dealer = $this -> state['dealer'];
            usort($winners, static fn (int $a, int $b): int => (($a - $dealer - 1 + $n) % $n) <=> (($b - $dealer - 1 + $n) % $n));
            foreach ($winners as $i => $seat) {
                $chips = intdiv($amount, count($winners)) + ($i < $amount % count($winners) ? 1 : 0);
                $this -> state['seats'][$seat]['stack'] += $chips;
                $this -> state['seats'][$seat]['won'] += $chips;
            }
            $this -> state['pots'][] = ['amount' => $amount, 'winners' => $winners, 'refund' => count($contributors) === 1];
        }
        foreach ($ranks as $seat => $rank) $this -> state['seats'][$seat]['hand'] = PokerHand::NAMES[$rank[0]];
        $this -> state['street'] = 'finished';
        $this -> state['turn'] = null;
        $this -> state['showdown'] = $showdown;
    }

    public function botAction(): array
    {
        $seat = $this -> state['turn'];
        $active = array_keys(array_filter($this -> state['seats'], static fn (array $player): bool => !$player['folded']));
        $first = ($this -> state['dealer'] + ($this -> state['street'] === 'preflop' ? (count($this -> state['seats']) === 2 ? 0 : 3) : 1)) % count($this -> state['seats']);
        $order = array_map(fn (int $index): int => ($index - $first + count($this -> state['seats'])) % count($this -> state['seats']), $active);
        $position = count(array_filter($order, fn (int $offset): bool => $offset < ($seat - $first + count($this -> state['seats'])) % count($this -> state['seats']))) / max(1, count($active) - 1);
        $context = ['opponents' => count($active) - 1, 'position' => $position,
            'pot' => array_sum(array_map(fn (array $player): int => min($player['total'], $this -> state['seats'][$seat]['total'] + $this -> legal($seat)['call']), $this -> state['seats'])),
            'raises' => count(array_filter($this -> state['log'], static fn (string $entry): bool => str_contains($entry, ': Raise To ')))];
        return PokerBot::choose($this -> state['seats'][$seat]['cards'], $this -> state['board'], $this -> legal($seat), $this -> state['seats'][$seat]['name'], context: $context);
    }

    public function view(int $viewer): array
    {
        $result = array_intersect_key($this -> state, array_flip(['board', 'street', 'dealer', 'turn', 'deadline', 'version', 'pots', 'log', 'showdown']));
        $result['pot'] = array_sum(array_column($this -> state['seats'], 'total'));
        $result['seats'] = [];
        $result['legal'] = [];
        foreach ($this -> state['seats'] as $index => $seat) {
            $public = array_intersect_key($seat, array_flip(['userId', 'name', 'image', 'stack', 'bet', 'total', 'folded', 'action', 'won', 'hand']));
            $public['cards'] = $seat['userId'] === $viewer || ($this -> state['showdown'] && !$seat['folded']) ? $seat['cards'] : [null, null];
            if ($this -> state['showdown'] && !$seat['folded']) $public['bestCards'] = PokerHand::best([...$seat['cards'], ...$this -> state['board']])['cards'];
            $result['seats'][] = $public;
            if ($seat['userId'] === $viewer) $result['legal'] = $this -> legal($index);
        }
        return $result;
    }
}
