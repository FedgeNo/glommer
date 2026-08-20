<?php

declare(strict_types=1);

class CSPReportTest extends DatabaseTestCase
{
    public function testRecordKeepsTheReportVerbatimAndLiftsTheColumns(): void
    {
        $body = '{"csp-report":{"violated-directive":"media-src","blocked-uri":"https://elsewhere.example/clip.mp4"}}';

        CSPReport::record('media-src', 'https://elsewhere.example/clip.mp4', 'TestBrowser/1.0', $body);

        $reports = CSPReport::recent(1);
        $this -> assertCount(1, $reports);
        $this -> assertSame('media-src', $reports[0] -> violatedDirective);
        $this -> assertSame('https://elsewhere.example/clip.mp4', $reports[0] -> blockedURI);
        $this -> assertSame('TestBrowser/1.0', $reports[0] -> userAgent);
        $this -> assertSame($body, $reports[0] -> report);
    }

    public function testRecordBoundsOversizedFieldsAndTakesNulls(): void
    {
        CSPReport::record(str_repeat('d', 100), str_repeat('u', 300), null, '{}');

        $reports = CSPReport::recent(1);
        $this -> assertSame(64, mb_strlen((string) $reports[0] -> violatedDirective));
        $this -> assertSame(255, mb_strlen((string) $reports[0] -> blockedURI));
        $this -> assertSame('', $reports[0] -> userAgent);
    }

    public function testPruneDropsOnlyExpiredReports(): void
    {
        CSPReport::record('img-src', 'https://old.example/a.png', 'TestBrowser/1.0', '{}');
        $old_id = DB::rows('
SELECT `reportId`
    FROM `CSPReports`
    ORDER BY `reportId` DESC
    LIMIT 1
', 'stdClass')[0] -> reportId;

        DB::run('
UPDATE `CSPReports`
    SET `createdAt` = NOW() - INTERVAL ? DAY
    WHERE `reportId` = ?
', 'ii', CSPReport::KEEP_DAYS + 1, (int) $old_id);

        CSPReport::record('img-src', 'https://new.example/b.png', 'TestBrowser/1.0', '{}');
        CSPReport::prune();

        $remaining = array_map(
            static fn (CSPReport $report): ?string => $report -> blockedURI,
            CSPReport::recent(10)
        );
        $this -> assertTrue(in_array('https://new.example/b.png', $remaining, true), 'The fresh report should survive pruning');
        $this -> assertFalse(in_array('https://old.example/a.png', $remaining, true), 'The expired report should be pruned');
    }
}
