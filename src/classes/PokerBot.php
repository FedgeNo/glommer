<?php

declare(strict_types=1);

/** Decisions deliberately receive no deck or opponent cards. */
class PokerBot
{
    public const NAMES = ['♠ Copper Fox', '♥ Velvet Owl', '♦ Brass Badger', '♣ Midnight Lynx', '♠ Silver Heron', '♥ Ruby Otter', '♦ Golden Moth', '♣ Indigo Wolf', '♠ Emerald Raven'];

    private const STYLES = [
        ['looseness' => -.15, 'aggression' => .18, 'bluff' => .02],
        ['looseness' => .08, 'aggression' => .55, 'bluff' => .10],
        ['looseness' => .22, 'aggression' => .20, 'bluff' => .04],
        ['looseness' => -.08, 'aggression' => .65, 'bluff' => .08],
        ['looseness' => .00, 'aggression' => .32, 'bluff' => .05],
        ['looseness' => .18, 'aggression' => .60, 'bluff' => .14],
        ['looseness' => -.12, 'aggression' => .28, 'bluff' => .03],
        ['looseness' => .12, 'aggression' => .40, 'bluff' => .07],
        ['looseness' => -.03, 'aggression' => .48, 'bluff' => .09],
    ];

    public static function choose(array $cards, array $board, array $legal, string $name = '', ?callable $random = null, array $context = []): array
    {
        $random ??= static fn (): float => random_int(0, 9999) / 10000;
        $identity = array_search($name, self::NAMES, true);
        $style = self::STYLES[$identity === false ? 4 : $identity];
        $a = $cards[0] % 13 + 2; $b = $cards[1] % 13 + 2;
        $strength = ($a + $b) / 35 + ($a === $b ? .35 : 0) + (intdiv($cards[0], 13) === intdiv($cards[1], 13) ? .08 : 0);
        if (count($board) >= 3) {
            $rank = PokerHand::rank([...$cards, ...$board]);
            $strength = min(1.3, .2 + $rank[0] * .18 + ($rank[1] ?? 0) / 70);
        }
        $pressure = $legal['call'] / max(1, $legal['maxRaiseTo']);
        $equity = null;
        if ($context) {
            $equity = self::equity($cards, $board, max(1, (int) $context['opponents']), $random);
            $odds = $legal['call'] / max(1, $context['pot'] + $legal['call']);
            $edge = $equity - $odds - min(.12, $context['raises'] * .025) + ($context['position'] - .5) * .06;
            $strength = $equity * 1.5;
        }
        $raise_chance = min(.8, ($style['bluff'] + max(0, $strength - .4) * $style['aggression']) * (1 - .6 * $pressure));
        if ($legal['canRaise'] && $legal['maxRaiseTo'] >= $legal['minRaiseTo'] && $random() < $raise_chance) {
            $range = $legal['maxRaiseTo'] - $legal['minRaiseTo'];
            $fraction = $random() ** 2 * (.25 + $style['aggression']);
            $size = $context ? ($context['pot'] + $legal['call']) * (.35 + $fraction) : $range * $fraction;
            $amount = min($legal['maxRaiseTo'], $legal['minRaiseTo'] + (int) floor($size / PokerRound::BIG_BLIND) * PokerRound::BIG_BLIND);
            if ($random() < .02 + max(0, $strength - .8) * $style['aggression']) $amount = $legal['maxRaiseTo'];
            return ['raise', $amount];
        }
        if ($legal['canCheck']) return ['check', 0];
        $call_chance = max(.04, min(.98, .25 + .65 * $strength + $style['looseness'] - .55 * $pressure));
        if ($equity !== null) $call_chance = max(.03, min(.99, .45 + 2.3 * $edge + $style['looseness']));
        return [$random() < $call_chance ? 'call' : 'fold', 0];
    }

    /** Sample unseen cards, including future streets, without access to the real deal. */
    public static function equity(array $cards, array $board, int $opponents, ?callable $random = null): float
    {
        $random ??= static fn (): float => random_int(0, 9999) / 10000;
        $opponents = max(1, min(8, $opponents));
        $unseen = array_values(array_diff(range(0, 51), $cards, $board));
        $score = 0.0;
        for ($trial = 0; $trial < 28; $trial++) {
            $deck = $unseen;
            $draw = static function () use (&$deck, $random): int {
                $index = min(count($deck) - 1, (int) floor($random() * count($deck)));
                return array_splice($deck, $index, 1)[0];
            };
            $runout = $board;
            while (count($runout) < 5) $runout[] = $draw();
            $own = PokerHand::rank([...$cards, ...$runout]);
            $ties = 1; $lost = false;
            for ($opponent = 0; $opponent < $opponents; $opponent++) {
                $other = PokerHand::rank([$draw(), $draw(), ...$runout]);
                if ($other > $own) $lost = true;
                elseif ($other === $own) $ties++;
            }
            if (!$lost) $score += 1 / $ties;
        }
        return $score / 28;
    }
}
