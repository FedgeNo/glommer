<?php

declare(strict_types=1);

class VLTTest extends TestCase
{
    private function stops(array $symbols): array
    {
        $stops = [];
        foreach (VLT::strips() as $reel => $strip) $stops[] = (array_search($symbols[$reel], $strip, true) + 31) % 32;
        return $stops;
    }

    public function testEveryReelHasTheDeclaredSymbolOddsAndWraps(): void
    {
        foreach (VLT::strips() as $reel => $strip) {
            $this -> assertCount(32, $strip);
            foreach (VLT::SYMBOLS as $symbol => $rule) $this -> assertSame($rule['weight'], array_count_values($strip)[$symbol]);
            $grid = VLT::grid([31, 31, 31, 31, 31]);
            $this -> assertSame([$strip[31], $strip[0], $strip[1]], $grid[$reel]);
        }
    }

    public function testOnlyTheLongestLeftmostMatchPaysAndMultipliersScaleAllReturns(): void
    {
        $three = VLT::settle(20, $this -> stops([5, 5, 5, 0, 5]), 1);
        $win = array_values(array_filter($three['wins'], static fn (array $win): bool => $win['line'] === 0));
        $this -> assertCount(1, $win); $this -> assertSame(3, $win[0]['count']); $this -> assertSame(400, $win[0]['payout']);
        $five = VLT::settle(20, $this -> stops([5, 5, 5, 5, 5]), 1);
        $win = array_values(array_filter($five['wins'], static fn (array $win): bool => $win['line'] === 0));
        $this -> assertCount(1, $win); $this -> assertSame(10000, $win[0]['payout']);
        $boost = VLT::settle(20, $this -> stops([5, 5, 5, 5, 5]), 5);
        $this -> assertSame($five['payout'] * 5, $boost['payout']);
        $this -> assertSame(array_sum(array_column($boost['wins'], 'payout')) - 20, $boost['net']);
        $right = VLT::settle(20, $this -> stops([0, 1, 5, 5, 5]), 1);
        $this -> assertCount(0, array_filter($right['wins'], static fn (array $win): bool => $win['line'] === 0));
    }

    public function testMultipleWinningLinesAllPayIncludingLinesAfterTheFirstMatch(): void
    {
        $round = VLT::settle(20, [27, 27, 27, 27, 27], 1);
        $this -> assertSame([
            ['line' => 1, 'symbol' => 1, 'count' => 3, 'payout' => 16],
            ['line' => 4, 'symbol' => 1, 'count' => 3, 'payout' => 16],
            ['line' => 8, 'symbol' => 0, 'count' => 4, 'payout' => 28],
        ], $round['wins']);
        $this -> assertSame(60, $round['payout']);
        $this -> assertSame(40, $round['net']);
    }

    public function testExactReturnMatchesExhaustiveIndependentLineOutcomes(): void
    {
        $expected = 0.0;
        for ($outcome = 0; $outcome < 6 ** 5; $outcome++) {
            $symbols = []; $value = $outcome; $probability = 1.0;
            for ($reel = 0; $reel < 5; $reel++) {
                $symbols[] = $value % 6; $probability *= VLT::SYMBOLS[$value % 6]['weight'] / 32; $value = intdiv($value, 6);
            }
            $count = 1;
            while ($count < 5 && $symbols[$count] === $symbols[0]) $count++;
            $expected += $probability * (VLT::SYMBOLS[$symbols[0]]['pays'][$count] ?? 0);
        }
        $this -> assertTrue(abs(VLT::returnRate() - $expected * 1.16) < .0000000001);
        $this -> assertTrue(abs(VLT::returnRate() - .9570221805572509) < .0000000001);
        $this -> assertSame(10, count(array_unique(array_map('json_encode', VLT::LINES))));
    }

    public function testInvalidStakesStopsAndMultipliersCannotSettle(): void
    {
        foreach ([0, -10, 15, 10000, '20', 20.0, null, [], true] as $stake) {
            $failed = false;
            try { VLT::validate($stake); } catch (\InvalidArgumentException) { $failed = true; }
            $this -> assertTrue($failed);
        }
        foreach ([[32,0,0,0,0], [-1,0,0,0,0], [0,0], ['1',0,0,0,0]] as $stops) {
            $failed = false;
            try { VLT::settle(10, $stops, 1); } catch (\InvalidArgumentException) { $failed = true; }
            $this -> assertTrue($failed);
        }
        $failed = false;
        try { VLT::settle(10, [0,0,0,0,0], 100); } catch (\InvalidArgumentException) { $failed = true; }
        $this -> assertTrue($failed);
    }

    public function testCabinetRendersRealControlsAndServerOwnedPaytable(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        $root = (new VLTGame()) -> toDOM(); $xpath = new \DOMXPath($root -> ownerDocument);
        $this -> assertSame(5, $xpath -> query('.//div[@class="VLTReel"]', $root) -> length);
        $this -> assertSame(15, $xpath -> query('.//span[@class="VLTSymbol"]', $root) -> length);
        $this -> assertSame(6, $xpath -> query('.//select[@name="stake"]/option', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//form[@method="POST"]//input[@name="CSRFToken"]', $root) -> length);
        $this -> assertSame(VLT::SYMBOLS, json_decode($root -> getAttribute('data-symbols'), true));
        $this -> assertTrue(str_contains($root -> textContent, '95.7022%'));
        $this -> assertFalse(str_contains($root -> textContent, 'ADVERTISEMENT'));
    }
}
