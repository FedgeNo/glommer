<?php

declare(strict_types=1);

/**
 * The selection policy behind the topic-summary drip: at most one topic is
 * ever due at a time, the most-trending one whose paragraph is missing or has
 * gone stale. The model call itself is deliberately out of reach here - these
 * tests spend no tokens and need no key.
 */
class TopicSummaryTest extends DatabaseTestCase
{
    private function clearTopics(): void
    {
        DB::run('
DELETE
    FROM `TopicSummaries`
');
        DB::run('
DELETE
    FROM `TrendingEntities`
');
    }

    private function trending(string $type, string $slug, float $score): void
    {
        DB::run('
INSERT INTO `TrendingEntities` (`type`, `slug`, `title`, `score`, `postCount`, `userCount`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'sssdii', $type, $slug, $slug, $score, 3, 2);
    }

    private function summary(string $slug, int $age_seconds): void
    {
        DB::run('
INSERT INTO `TopicSummaries` (`type`, `slug`, `summary`, `createdAt`)
    VALUES (?, ?, ?, NOW() - INTERVAL ? SECOND)
', 'sssi', 'hashtag', $slug, 'existing words', $age_seconds);
    }

    public function testTheMostTrendingUnsummarizedTopicIsDue(): void
    {
        $this -> clearTopics();
        $this -> trending('hashtag', 'quiet', 1.0);
        $this -> trending('hashtag', 'loud', 9.0);

        $this -> assertSame('loud', TopicSummary::nextDue() ?-> slug);
    }

    public function testAFreshSummaryTakesItsTopicOutOfTheQueue(): void
    {
        $this -> clearTopics();
        $this -> trending('hashtag', 'loud', 9.0);
        $this -> trending('hashtag', 'quiet', 1.0);
        $this -> summary('loud', 60);

        $this -> assertSame('quiet', TopicSummary::nextDue() ?-> slug);
    }

    public function testAStaleSummaryComesDueAgain(): void
    {
        $this -> clearTopics();
        $this -> trending('hashtag', 'loud', 9.0);
        $this -> summary('loud', 86400 + 3600);

        $this -> assertSame('loud', TopicSummary::nextDue() ?-> slug);
    }

    public function testOnlyHashtagsAreEverDue(): void
    {
        // People and places trend too, but only a hashtag has a page of its
        // own to carry the paragraph.
        $this -> clearTopics();
        $this -> trending('person', 'ada-lovelace', 9.0);

        $this -> assertNull(TopicSummary::nextDue());
    }

    public function testNothingTrendingMeansNothingDue(): void
    {
        $this -> clearTopics();

        $this -> assertNull(TopicSummary::nextDue());
    }

    public function testTheStoredParagraphReadsBack(): void
    {
        $this -> clearTopics();
        $this -> summary('gardening', 60);

        $this -> assertSame('existing words', TopicSummary::for('hashtag', 'gardening'));
        $this -> assertNull(TopicSummary::for('hashtag', 'nothing-here'));
    }

    public function testWithoutAnAPIKeyTheRefreshTouchesNothing(): void
    {
        // The whole feature keys off OpenRouter being configured; tests run
        // without a key, so this must be a clean no-op rather than an error.
        $this -> clearTopics();
        $this -> trending('hashtag', 'loud', 9.0);

        TopicSummary::refreshDue();

        $this -> assertNull(TopicSummary::for('hashtag', 'loud'));
    }
}
