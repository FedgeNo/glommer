<?php

declare(strict_types=1);

class PokerTest extends TestCase
{
    private function players(int $count = 3): array
    {
        return array_map(static fn (int $i): array => ['userId' => $i + 1, 'name' => '@player' . $i, 'image' => null, 'stack' => 1000], range(0, $count - 1));
    }

    private function cards(string $notation): array
    {
        return array_map(static fn (string $card): int => strpos('shdc', $card[1]) * 13 + strpos('23456789TJQKA', $card[0]), explode(' ', $notation));
    }

    public function testHandCategoriesAndWheelAndKickers(): void
    {
        $hands = ['As Jd 9c 7h 3s', 'As Ad 9c 7h 3s', 'As Ad 9c 9h 3s', 'As Ad Ac 9h 3s',
            'As 2d 3c 4h 5s', 'As Js 9s 7s 3s', 'As Ad Ac 9h 9s', 'As Ad Ac Ah 3s', 'As Ks Qs Js Ts'];
        $previous = [];
        foreach ($hands as $category => $hand) {
            $rank = PokerHand::rank($this -> cards($hand));
            $this -> assertSame($category, $rank[0]);
            $this -> assertTrue($rank > $previous); $previous = $rank;
        }
        $this -> assertSame(5, PokerHand::rank($this -> cards('As 2d 3c 4h 5s'))[1]);
        $this -> assertTrue(PokerHand::rank($this -> cards('As Ad Kc Qh Js')) > PokerHand::rank($this -> cards('Ah Ac Ks Qd Ts')));
        $this -> assertSame([6, 14, 13, 0, 0, 0], PokerHand::rank($this -> cards('As Ad Ac Ks Kd Kc 2s')));
        $this -> assertSame([8, 14, 0, 0, 0, 0], PokerHand::rank($this -> cards('As Ks Qs Js Ts 3d 4c')));
    }

    public function testBestCardsIdentifyTheWinningFiveAndStayPrivateUntilShowdown(): void
    {
        $cards = $this -> cards('As Ks Qs Js Ts 3d 4c');
        $this -> assertSame(array_slice($cards, 0, 5), PokerHand::best($cards)['cards']);
        $round = PokerRound::deal($this -> players(), 0, 100);
        foreach ($round -> view(1)['seats'] as $seat) $this -> assertFalse(isset($seat['bestCards']));
        $round -> act(0, 'raise', 1000, 101); $round -> act(1, 'call', 0, 102); $round -> act(2, 'call', 0, 103);
        $this -> assertTrue($round -> finished());
        $view = $round -> view(1);
        $this -> assertTrue($view['showdown']);
        foreach ($view['seats'] as $seat) {
            $this -> assertSame(5, count($seat['bestCards']));
            $this -> assertSame(PokerHand::rank([...$seat['cards'], ...$view['board']]), PokerHand::rank($seat['bestCards']));
        }
    }

    public function testBotEquityRecognizesUnbeatableHandsAndSharedBoards(): void
    {
        $seed = 19;
        $random = static function () use (&$seed): float { $seed = ($seed * 16807) % 2147483647; return $seed / 2147483647; };
        $this -> assertSame(1.0, PokerBot::equity($this -> cards('As Ks'), $this -> cards('Qs Js Ts 2d 3c'), 8, $random));
        $split = PokerBot::equity($this -> cards('2h 3d'), $this -> cards('As Ks Qs Js Ts'), 2, $random);
        $this -> assertTrue(abs($split - 1 / 3) < .000001);
        $draw = PokerBot::equity($this -> cards('As Ks'), $this -> cards('Qs Js 2d'), 1, $random);
        $weak = PokerBot::equity($this -> cards('3h 4c'), $this -> cards('Qs Js 2d'), 1, $random);
        $this -> assertTrue($draw > $weak);
    }

    public function testBotsConsiderPotOddsForTheSameCardsAndCallPrice(): void
    {
        $legal = ['call' => 100, 'maxRaiseTo' => 100, 'minRaiseTo' => 200, 'canRaise' => false, 'canCheck' => false];
        $context = ['opponents' => 2, 'position' => .5, 'pot' => 100, 'raises' => 0];
        $random = static fn (): float => .5;
        $this -> assertSame('fold', PokerBot::choose($this -> cards('2h 3d'), $this -> cards('As Ks Qs Js Ts'), $legal, PokerBot::NAMES[4], $random, $context)[0]);
        $context['pot'] = 2000;
        $this -> assertSame('call', PokerBot::choose($this -> cards('2h 3d'), $this -> cards('As Ks Qs Js Ts'), $legal, PokerBot::NAMES[4], $random, $context)[0]);
    }

    public function testHeadsUpDealerPostsSmallBlindAndActsFirstThenLast(): void
    {
        $round = PokerRound::deal($this -> players(2), 0, 100, range(0, 51));
        $this -> assertSame(0, $round -> snapshot()['turn']);
        $this -> assertSame([10, 20], array_column($round -> snapshot()['seats'], 'bet'));
        $round -> act(0, 'call', 0, 101);
        $this -> assertTrue($round -> legal(1)['canCheck']);
        $round -> act(1, 'check', 0, 102);
        $this -> assertSame('flop', $round -> snapshot()['street']);
        $this -> assertSame(1, $round -> snapshot()['turn']);
    }

    public function testShortAllInDoesNotReopenAnEarlierCallButStillRequiresMatching(): void
    {
        $players = $this -> players(); $players[1]['stack'] = 25;
        $round = PokerRound::deal($players, 0, 100);
        $round -> act(0, 'call', 0, 101);
        $round -> act(1, 'raise', 25, 102);
        $round -> act(2, 'call', 0, 103);
        $legal = $round -> legal(0);
        $this -> assertSame(5, $legal['call']); $this -> assertFalse($legal['canRaise']);
        $before = $round -> snapshot();
        try { $round -> act(0, 'raise', 45, 104); $this -> assertTrue(false); } catch (\DomainException) {}
        $this -> assertSame($before, $round -> snapshot());
        $round -> act(0, 'call', 0, 105);
        $this -> assertSame('flop', $round -> snapshot()['street']);
    }

    public function testMultipleShortAllInsCanReopenBetting(): void
    {
        $players = $this -> players(4); $players[0]['stack'] = 30; $players[1]['stack'] = 40;
        $round = PokerRound::deal($players, 0, 100);
        $round -> act(3, 'call', 0, 101);
        $round -> act(0, 'raise', 30, 102);
        $round -> act(1, 'raise', 40, 103);
        $round -> act(2, 'call', 0, 104);
        $this -> assertTrue($round -> legal(3)['canRaise']);
        $this -> assertSame(60, $round -> legal(3)['minRaiseTo']);
    }

    public function testSidePotsAndUncalledExcessConserveChips(): void
    {
        $players = $this -> players();
        $players[0]['stack'] = 50; $players[1]['stack'] = 100; $players[2]['stack'] = 200;
        $round = PokerRound::deal($players, 0, 100);
        $state = $round -> snapshot();
        // Put known cards into a valid betting state to exercise all-in settlement.
        $state['seats'][0]['cards'] = $this -> cards('As Ad');
        $state['seats'][1]['cards'] = $this -> cards('Ks Kd');
        $state['seats'][2]['cards'] = $this -> cards('Qs Qd');
        $used = $this -> cards('As Ad Ks Kd Qs Qd 2s 3h 7d 8c 9s');
        $draw = $this -> cards('4s 2s 3h 7d 5s 8c 6s 9s');
        $state['deck'] = [...array_values(array_diff(range(0, 51), $used, $draw)), ...array_reverse($draw)];
        $round = new PokerRound($state);
        $round -> act(0, 'raise', 50, 101);
        $round -> act(1, 'raise', 100, 102);
        // No opponent can call another raise. The remaining player can only call.
        $this -> assertFalse($round -> legal(2)['canRaise']);
        $round -> act(2, 'call', 0, 103);
        $this -> assertTrue($round -> finished());
        $this -> assertSame([150, 100, 100], array_column($round -> snapshot()['seats'], 'stack'));
        $this -> assertSame(350, array_sum(array_column($round -> snapshot()['seats'], 'stack')));
    }

    public function testSplitPotUsesBoardAndAwardsOddChipLeftOfDealer(): void
    {
        $round = PokerRound::deal($this -> players(), 0, 100);
        $state = $round -> snapshot(); $state['street'] = 'river'; $state['board'] = $this -> cards('As Ks Qs Js Ts');
        $state['currentBet'] = 0; $state['turn'] = 2;
        foreach ($state['seats'] as $i => &$seat) {
            $seat['cards'] = $this -> cards(['2d 3d', '4d 5d', '6d 7d'][$i]);
            $seat['total'] = 5; $seat['bet'] = 0; $seat['stack'] = 995; $seat['actedAt'] = 0;
        }
        unset($seat); $state['seats'][0]['folded'] = true;
        $round = new PokerRound($state); $round -> act(2, 'check', 0, 101);
        $this -> assertSame([995, 1003, 1002], array_column($round -> snapshot()['seats'], 'stack'));
        $this -> assertSame([1, 2], $round -> snapshot()['pots'][0]['winners']);
    }

    public function testPrivateViewNeverExposesDeckOrOpponentsCardsBeforeShowdown(): void
    {
        $round = PokerRound::deal($this -> players(), 0, 100);
        $view = $round -> view(1);
        $this -> assertFalse(isset($view['deck']));
        $this -> assertSame([null, null], $view['seats'][1]['cards']);
        $this -> assertSame($round -> snapshot()['seats'][0]['cards'], $view['seats'][0]['cards']);
        $round -> act(0, 'fold', 0, 101); $round -> act(1, 'fold', 0, 102);
        $this -> assertTrue($round -> finished());
        $this -> assertSame([null, null], $round -> view(1)['seats'][2]['cards']);
        $this -> assertSame(3000, array_sum(array_column($round -> snapshot()['seats'], 'stack')));
    }

    public function testBotGamesFinishWithUniqueCardsAndConservedChips(): void
    {
        for ($game = 0; $game < 20; $game++) {
            $players = $this -> players(9);
            foreach ($players as &$player) $player['userId'] = null;
            unset($player);
            $round = PokerRound::deal($players, $game % 9, 100);
            $cards = array_merge(...array_column($round -> snapshot()['seats'], 'cards'));
            $this -> assertSame(18, count(array_unique($cards)));
            for ($step = 0; $step < 1000 && !$round -> finished(); $step++) {
                [$move, $amount] = $round -> botAction();
                $round -> act($round -> snapshot()['turn'], $move, $amount, 100 + $step);
            }
            $this -> assertTrue($round -> finished());
            $this -> assertSame(9000, array_sum(array_column($round -> snapshot()['seats'], 'stack')));
        }
    }

    public function testBotPersonalitiesRespondDifferentlyToTheSameBet(): void
    {
        $legal = ['call' => 1000, 'maxRaiseTo' => 1000, 'minRaiseTo' => 2000, 'canRaise' => false, 'canCheck' => false];
        $random = static fn (): float => .25;
        $this -> assertSame('fold', PokerBot::choose($this -> cards('As Kd'), [], $legal, PokerBot::NAMES[0], $random)[0]);
        $this -> assertSame('call', PokerBot::choose($this -> cards('As Kd'), [], $legal, PokerBot::NAMES[2], $random)[0]);
        foreach (PokerBot::NAMES as $name) {
            $this -> assertSame('call', PokerBot::choose($this -> cards('2s 7d'), [], $legal, $name, static fn (): float => .01)[0]);
            $this -> assertSame('fold', PokerBot::choose($this -> cards('2s 7d'), [], $legal, $name, static fn (): float => .99)[0]);
        }
    }

    public function testBotsVaryRaiseSizesAndRespectRestrictedActions(): void
    {
        $legal = ['call' => 0, 'maxRaiseTo' => 1000, 'minRaiseTo' => 40, 'canRaise' => true, 'canCheck' => true];
        $choose = function (float $size) use ($legal): array {
            $rolls = [0.0, $size, .99];
            return PokerBot::choose($this -> cards('As Ad'), [], $legal, PokerBot::NAMES[1], static function () use (&$rolls): float { return array_shift($rolls); });
        };
        $small = $choose(.1); $large = $choose(.9);
        $this -> assertSame('raise', $small[0]);
        $this -> assertSame('raise', $large[0]);
        $this -> assertTrue($small[1] >= 40 && $large[1] > $small[1] && $large[1] <= 1000);
        $legal['canRaise'] = false;
        $this -> assertSame(['check', 0], PokerBot::choose($this -> cards('As Ad'), [], $legal, PokerBot::NAMES[1], static fn (): float => 0.0));
    }
}
