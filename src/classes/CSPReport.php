<?php

declare(strict_types=1);

/**
 * A browser's report that the Content-Security-Policy blocked something.
 *
 * The report body is kept verbatim - the point is to hear about pages
 * breaking in browsers nobody here uses, a video refused by media-src or a
 * font that never loaded, and the JSON says it best. The violated directive
 * and blocked URI are lifted into their own columns so the review query
 * ("what is tripping, how often") can group and filter without opening every
 * body, and the user agent is recorded because the report itself never names
 * the browser sending it.
 */
class CSPReport
{
    public ?int $reportId = null;
    public ?string $violatedDirective = null;
    public ?string $blockedURI = null;
    public ?string $userAgent = null;
    public ?string $report = null;
    public ?string $createdAt = null;

    /** Long enough to find a pattern, short enough that a diagnostic log is not a data store. */
    public const KEEP_DAYS = 30;

    /** Real reports run well under 2KB; anything bigger is not a browser being honest. */
    public const MAX_BODY_BYTES = 8192;

    public static function record(?string $violated_directive, ?string $blocked_uri, ?string $user_agent, string $report_json): void
    {
        DB::run('
INSERT INTO `CSPReports` (`violatedDirective`, `blockedURI`, `userAgent`, `report`)
    VALUES (?, ?, ?, ?)
', 'ssss', mb_substr($violated_directive ?? '', 0, 64), mb_substr($blocked_uri ?? '', 0, 255), mb_substr($user_agent ?? '', 0, 255), $report_json);

        // Occasionally sweep out expired rows (same lottery approach as
        // RateLimiter) - no worker ever visits this table on a schedule.
        if (random_int(1, 100) === 1) {
            self::prune();
        }
    }

    /** Drops what is too old to be telling anybody anything. */
    public static function prune(): void
    {
        DB::run('
DELETE FROM `CSPReports`
    WHERE `createdAt` < NOW() - INTERVAL ? DAY
', 'i', self::KEEP_DAYS);
    }

    /**
     * The most recent reports, newest first.
     *
     * @return self[]
     */
    public static function recent(int $limit = 50): array
    {
        return DB::rows('
SELECT *
    FROM `CSPReports`
    ORDER BY `reportId` DESC
    LIMIT ?
', self::class, 'i', $limit);
    }
}
