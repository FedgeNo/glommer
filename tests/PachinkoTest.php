<?php

declare(strict_types=1);

class PachinkoTest extends TestCase
{
    public function testEveryPathMatchesTheDeclaredPocketOddsAndExactReturn(): void
    {
        $counts = array_fill(0, 13, 0); $payout = 0;
        for ($bits = 0; $bits < 4096; $bits++) {
            $path = [];
            for ($row = 0; $row < 12; $row++) $path[] = ($bits >> $row) & 1;
            $round = Pachinko::settle(10, $path);
            $this -> assertSame(array_sum($path), $round['pocket']);
            $this -> assertSame(Pachinko::RETURNS[$round['pocket']], $round['payout']);
            $this -> assertSame($round['payout'] - 10, $round['net']);
            $counts[$round['pocket']]++; $payout += $round['payout'];
        }
        $this -> assertSame([1,12,66,220,495,792,924,792,495,220,66,12,1], $counts);
        foreach ($counts as $pocket => $count) $this -> assertSame($count, Pachinko::pocketWays($pocket));
        $this -> assertSame(39668, $payout);
        $this -> assertTrue(abs(Pachinko::returnRate() - $payout / 40960) < 1e-12);
    }

    public function testStakesScaleReturnsAndInvalidInputsAreRejected(): void
    {
        foreach (Pachinko::STAKES as $stake) {
            $this -> assertSame($stake * 50, Pachinko::settle($stake, array_fill(0, 12, 0))['payout']);
            $this -> assertSame(intdiv($stake, 5), Pachinko::settle($stake, array_merge(array_fill(0, 6, 0), array_fill(0, 6, 1)))['payout']);
        }
        foreach ([null, '20', 20.0, 0, -10, 15, 10000] as $stake) {
            $rejected = false;
            try { Pachinko::validate($stake); } catch (\InvalidArgumentException) { $rejected = true; }
            $this -> assertTrue($rejected);
        }
        foreach ([[], array_fill(0, 11, 0), array_fill(0, 12, 2), array_fill(0, 12, '0'), array_fill(1, 12, 0)] as $path) {
            $rejected = false;
            try { Pachinko::settle(20, $path); } catch (\InvalidArgumentException) { $rejected = true; }
            $this -> assertTrue($rejected);
        }
    }

    public function testPageRendersAllPocketsControlsRulesAndAdvertising(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        $root = (new PachinkoGame()) -> toDOM(); $xpath = new \DOMXPath($root -> ownerDocument);
        $this -> assertSame('pachinko', $root -> getAttribute('data-vlt-game'));
        $this -> assertSame(13, $xpath -> query('.//*[@data-pocket]', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//button[@type="submit"]', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//input[@name="CSRFToken"]', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//iframe[@data-desktop-ad]', $root) -> length);
        $this -> assertTrue(str_contains($root -> textContent, '96.8457%'));
        $this -> assertFalse(str_contains($root -> textContent, 'payline'));
    }
}
