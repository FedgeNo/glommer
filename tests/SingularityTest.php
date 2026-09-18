<?php

declare(strict_types=1);

class SingularityTest extends TestCase
{
    public function testGroupsConnectOrthogonallyAndAllSeparateGroupsPay(): void
    {
        $grid = [[0,0,0,0,0], [1,2,3,4,5], [0,0,0,0,0], [2,3,4,1,2], [5,5,5,5,5]];
        $clusters = Singularity::clusters($grid);
        $this -> assertSame([0,0,5], array_column($clusters, 'symbol'));
        $this -> assertSame([5,5,5], array_column($clusters, 'count'));
        $diagonal = [];
        for ($x = 0; $x < 5; $x++) for ($y = 0; $y < 5; $y++) $diagonal[$x][$y] = ($x + $y) % 6;
        $this -> assertSame([], Singularity::clusters($diagonal));
        $values = array_merge(...$grid); $position = 0;
        $round = Singularity::round(10, static function () use ($values, &$position): int { return $position < 25 ? $values[$position++] : ($position++ % 6); });
        $this -> assertSame(88, $round['cascades'][0]['payout']);
        $this -> assertSame(3, count($round['cascades'][0]['clusters']));
        $this -> assertSame(array_sum(array_column($round['cascades'], 'payout')), $round['payout']);
    }

    public function testGravityPreservesSurvivorsAndRefillsOnlyRemovedCells(): void
    {
        $grid = array_fill(0, 5, [0,1,2,3,4]); $draws = 0;
        $next = Singularity::refill($grid, [['cells' => [[0,1],[0,3],[4,4]]]], static function () use (&$draws): int { $draws++; return 5; });
        $this -> assertSame([5,5,0,2,4], $next[0]);
        $this -> assertSame([5,0,1,2,3], $next[4]);
        $this -> assertSame($grid[1], $next[1]);
        $this -> assertSame(3, $draws);
    }

    public function testCascadeMultiplierResetsAndFinalCascadeIsPaidWithoutRefill(): void
    {
        $draws = 0;
        $round = Singularity::round(20, static function () use (&$draws): int { $draws++; return 0; });
        $this -> assertSame(range(1,12), array_column($round['cascades'], 'multiplier'));
        $this -> assertSame(300, $draws);
        $this -> assertSame(440 * 78, $round['payout']);
        $this -> assertSame(440 * 12, $round['cascades'][11]['payout']);
        $this -> assertSame($round['grid'], $round['cascades'][11]['grid']);
        $this -> assertSame(440, Singularity::round(20, static fn(): int => 0)['cascades'][0]['payout']);
        $index = 0;
        $lost = Singularity::round(10, static function () use (&$index): int { return $index++ % 6; });
        $this -> assertSame([], $lost['cascades']);
        $this -> assertSame(-10, $lost['net']);
    }

    public function testReactorRendersItsOwnLabelsAndNormalWalletAndAdControls(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        $root = (new SingularityGame()) -> toDOM(); $xpath = new \DOMXPath($root -> ownerDocument);
        $this -> assertSame('singularity', $root -> getAttribute('data-vlt-game'));
        $this -> assertTrue(str_contains($root -> getAttribute('class'), 'SingularityGame'));
        $this -> assertSame(Singularity::SYMBOLS, json_decode($root -> getAttribute('data-symbols'), true));
        $this -> assertSame('IGNITE THE REACTOR', $xpath -> query('.//button[@class="VLTSpin"]', $root) -> item(0) -> textContent);
        $this -> assertSame('OVERDRIVE · 1×', $xpath -> query('.//strong[@class="VLTPower"]', $root) -> item(0) -> textContent);
        $this -> assertSame(1, $xpath -> query('.//form[@method="POST"]//input[@name="CSRFToken"]', $root) -> length);
        $this -> assertSame(1, $xpath -> query('.//iframe[@data-desktop-ad]', $root) -> length);
        $this -> assertFalse(str_contains($root -> textContent, 'Nova Vault'));
        $this -> assertSame(25, $xpath -> query('.//span[@class="VLTSymbol"]', $root) -> length);
        $this -> assertTrue(str_contains($root -> textContent, 'Diagonals do not connect'));
        $this -> assertFalse(str_contains($root -> textContent, '95.7022'));
    }
}
