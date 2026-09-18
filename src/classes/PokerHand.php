<?php

declare(strict_types=1);

/** Best five-card Hold’em hand; lexicographic ranks include every kicker. */
class PokerHand
{
    public const NAMES = ['High Card', 'One Pair', 'Two Pair', 'Three of a Kind', 'Straight', 'Flush', 'Full House', 'Four of a Kind', 'Straight Flush'];

    public static function rank(array $cards): array
    {
        return self::best($cards)['rank'];
    }

    public static function best(array $cards): array
    {
        if (count($cards) < 5 || count($cards) > 7 || count(array_unique($cards)) !== count($cards)) throw new \InvalidArgumentException('Expected five to seven distinct cards.');
        foreach ($cards as $card) if (!is_int($card) || $card < 0 || $card > 51) throw new \InvalidArgumentException('Invalid card.');
        $best = [];
        $chosen = [];
        $n = count($cards);
        for ($a = 0; $a < $n - 4; $a++) for ($b = $a + 1; $b < $n - 3; $b++)
            for ($c = $b + 1; $c < $n - 2; $c++) for ($d = $c + 1; $d < $n - 1; $d++)
                for ($e = $d + 1; $e < $n; $e++) {
                    $five = [$cards[$a], $cards[$b], $cards[$c], $cards[$d], $cards[$e]];
                    $rank = array_pad(self::five($five), 6, 0);
                    if ($rank > $best) { $best = $rank; $chosen = $five; }
                }
        return ['rank' => $best, 'cards' => $chosen];
    }

    private static function five(array $cards): array
    {
        $ranks = array_map(static fn (int $card): int => $card % 13 + 2, $cards);
        rsort($ranks);
        $counts = array_count_values($ranks);
        $groups = array_keys($counts);
        usort($groups, static fn (int $a, int $b): int => ($counts[$b] <=> $counts[$a]) ?: ($b <=> $a));
        $flush = count(array_unique(array_map(static fn (int $card): int => intdiv($card, 13), $cards))) === 1;
        $straight = count($counts) === 5 && $ranks[0] - $ranks[4] === 4 ? $ranks[0] : 0;
        if ($ranks === [14, 5, 4, 3, 2]) $straight = 5;
        if ($flush && $straight) return [8, $straight];
        if ($counts[$groups[0]] === 4) return [7, ...$groups];
        if ($counts[$groups[0]] === 3 && $counts[$groups[1]] === 2) return [6, ...$groups];
        if ($flush) return [5, ...$ranks];
        if ($straight) return [4, $straight];
        if ($counts[$groups[0]] === 3) return [3, ...$groups];
        if ($counts[$groups[0]] === 2 && $counts[$groups[1]] === 2) return [2, ...$groups];
        if ($counts[$groups[0]] === 2) return [1, ...$groups];
        return [0, ...$ranks];
    }
}
