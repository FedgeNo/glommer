<?php

declare(strict_types=1);

/**
 * How much of the uploads volume is held back for everything that is not an
 * upload - the database above all, which usually shares the disk.
 *
 * The reserve has to mean the same thing on any size of disk. A flat figure
 * does not: ten gigabytes held back is prudent on a large volume and an
 * outright refusal to accept uploads on a small one, where the disk can be
 * two thirds empty and still never clear the bar.
 */
class UploadDiskReserveTest extends TestCase
{
    private const GIB = 1024 * 1024 * 1024;

    /**
     * The case that took uploads down: a 15 GiB volume with 9.9 GiB free is
     * a third full and in no trouble, and a flat 10 GiB reserve refused
     * every upload on it.
     */
    public function testASmallVolumeIsNotAskedForMoreThanItHas(): void
    {
        $reserved = UploadProcessor::reservedFor(15 * self::GIB);

        $this -> assertSame((int) (1.5 * self::GIB), $reserved);
        $this -> assertTrue($reserved < 9.9 * self::GIB, 'a third-full 15 GiB volume must still take an upload');
    }

    /** Past the cap, a bigger disk is just a bigger disk. */
    public function testALargeVolumeReservesTheCapAndNoMore(): void
    {
        $this -> assertSame(10 * self::GIB, UploadProcessor::reservedFor(1024 * self::GIB));
        $this -> assertSame(10 * self::GIB, UploadProcessor::reservedFor(100 * self::GIB));
    }

    /** The two rules meet where a tenth of the volume is the cap. */
    public function testTheFractionGivesWayToTheCapAtAHundredGibibytes(): void
    {
        $this -> assertSame(9 * self::GIB, UploadProcessor::reservedFor(90 * self::GIB));
        $this -> assertSame(10 * self::GIB, UploadProcessor::reservedFor(110 * self::GIB));
    }
}
