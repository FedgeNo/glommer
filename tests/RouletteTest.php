<?php

declare(strict_types=1);

class RouletteTest extends TestCase
{
    public function testEveryPositionHasExactlyTheEuropeanExpectedReturn(): void
    {
        $pockets = Roulette::WHEEL;
        sort($pockets);
        $this -> assertSame(range(0, 36), $pockets);
        foreach (Roulette::positions() as $key => $rule) {
            $returns = 0;
            foreach (range(0, 36) as $number) {
                $round = Roulette::settle([$key => 10], $number);
                $returns += $round['payout'];
                $this -> assertSame(in_array($number, $rule['numbers'], true) ? 10 * ($rule['odds'] + 1) : 0, $round['payout'], $key);
            }
            // 370 chips wagered over all 37 equally likely outcomes return 360.
            $this -> assertSame(360, $returns, $key);
            $this -> assertSame(count($rule['numbers']), count(array_unique($rule['numbers'])));
        }
        $this -> assertSame(18, count(Roulette::RED));
    }

    public function testZeroLosesOutsideBetsAndReturnsWinningStakes(): void
    {
        $this -> assertSame(0, Roulette::settle(['red' => 10, 'black' => 10, 'even' => 10, 'low' => 10, 'column:1' => 10, 'dozen:1' => 10], 0)['payout']);
        $round = Roulette::settle(['straight:0' => 10, 'first-four' => 20, 'red' => 5], 0);
        $this -> assertSame(540, $round['payout']);
        $this -> assertSame(505, $round['net']);
    }

    public function testInvalidBetsAndInflatedCostsAreRejected(): void
    {
        foreach ([null, [], ['red' => '10'], ['red' => 1.5], ['red' => -1], ['red' => 0], ['red' => true], ['payout' => 100], ['straight:37' => 1], ['red' => 10000, 'black' => 1], ['split:3-4' => 1]] as $bets) {
            $rejected = false;
            try { Roulette::validate($bets); } catch (\InvalidArgumentException) { $rejected = true; }
            $this -> assertTrue($rejected);
        }
        $this -> assertSame(['black' => 5000, 'red' => 5000], Roulette::validate(['red' => 5000, 'black' => 5000]));
    }

    public function testGrantClockIncludesTheExactHourButNeverAccumulatesMissedHours(): void
    {
        $this -> assertTrue(GameWallet::grantDue(null, 10000));
        $this -> assertFalse(GameWallet::grantDue(10000, 13599));
        $this -> assertTrue(GameWallet::grantDue(10000, 13600));
        $this -> assertTrue(GameWallet::grantDue(10000, 30000));
    }

    public function testRenderedTableExposesAllPocketsAndAllThreeAdSizes(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        $root = (new RouletteGame()) -> toDOM();
        $xpath = new \DOMXPath($root -> ownerDocument);
        $this -> assertSame(37, $xpath -> query('.//button[starts-with(@data-bet,"straight:")]', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//form[@method="POST"]//input[@name="CSRFToken"]', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//iframe[@data-desktop-ad and @data-tablet-ad and @data-mobile-ad and not(@src)]', $root) -> length);
        $this -> assertSame(0, $xpath -> query('.//aside[@class="casino-ad"]/small', $root) -> length);
        $this -> assertSame(Roulette::WHEEL, json_decode($root -> getAttribute('data-wheel'), true));
    }
}
