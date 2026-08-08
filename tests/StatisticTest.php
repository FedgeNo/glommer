<?php

declare(strict_types=1);

/**
 * Counting things that have already happened.
 *
 * The queues cannot answer how a server is doing - they hold only what has
 * not been dealt with, and a delivery that arrives is deleted the moment it
 * does. So the outcome is counted as it happens, and what matters here is
 * that counting is cheap, additive, and never worth failing the work it was
 * counting for.
 */
class StatisticTest extends DatabaseTestCase
{
    private function name(): string
    {
        return 'test-' . bin2hex(random_bytes(6));
    }

    public function testCountsAddUpWithinTheDay(): void
    {
        $name = $this -> name();

        Statistic::count($name);
        Statistic::count($name);
        Statistic::count($name, 3);

        $this -> assertSame(5, Statistic::since($name, 7));
    }

    /** Nothing counted is nothing, not an absence to trip over. */
    public function testSomethingNeverCountedIsZero(): void
    {
        $this -> assertSame(0, Statistic::since($this -> name(), 7));
    }

    /** Two things counted on the same day stay two separate tallies. */
    public function testCountsAreKeptApartByName(): void
    {
        $first = $this -> name();
        $second = $this -> name();

        Statistic::count($first, 2);
        Statistic::count($second, 7);

        $this -> assertSame(2, Statistic::since($first, 7));
        $this -> assertSame(7, Statistic::since($second, 7));
    }

    /** A window asks for days, and days outside it are not in the answer. */
    public function testOnlyTheDaysAskedForAreCounted(): void
    {
        $name = $this -> name();

        Statistic::count($name, 4);

        DB::run('
INSERT INTO `Statistics` (`name`, `day`, `total`)
    VALUES (?, CURDATE() - INTERVAL 30 DAY, ?)
', 'si', $name, 100);

        $this -> assertSame(4, Statistic::since($name, 7), 'a month ago is not this week');
        $this -> assertSame(104, Statistic::since($name, 60));
    }

    /** Days too old to be looked at go, and recent ones stay. */
    public function testPruningDropsOnlyWhatIsTooOld(): void
    {
        $name = $this -> name();

        Statistic::count($name, 1);

        DB::run('
INSERT INTO `Statistics` (`name`, `day`, `total`)
    VALUES (?, CURDATE() - INTERVAL ? DAY, ?)
', 'sii', $name, Statistic::KEEP_DAYS + 10, 500);

        Statistic::prune();

        $this -> assertSame(1, Statistic::since($name, Statistic::KEEP_DAYS + 60));
    }
}
