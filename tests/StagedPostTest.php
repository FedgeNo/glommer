<?php

declare(strict_types=1);

/**
 * A staged post is a Posts row that doesn't exist yet. What these hold to
 * account: it becomes exactly one real post when published (by hand or by
 * clock), it can never publish twice, and discarding it leaves no trace.
 */
class StagedPostTest extends DatabaseTestCase
{
    private function staged(?string $publish_at): int
    {
        return StagedPost::stage(
            self::createUser(),
            'A staged title',
            'staged body text',
            json_encode(['ops' => [['insert' => "staged body text\n"]]]),
            null,
            null,
            null,
            0,
            $publish_at
        );
    }

    public function testPublishingMakesARealPostAndConsumesTheRow(): void
    {
        $staged_id = $this -> staged(null);
        $staged = StagedPost::load($staged_id);

        $post_id = $staged -> publish();

        $this -> assertNotNull($post_id);
        $this -> assertNull(StagedPost::load($staged_id), 'the staged row should be consumed');

        $post = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        $this -> assertSame('A staged title', $post -> title);
        $this -> assertSame('staged body text', $post -> description);
    }

    public function testTheSameRowCannotPublishTwice(): void
    {
        // Two loads of one row racing (the worker's clock against the
        // publish-now button): whoever deletes the row publishes; the loser
        // must do nothing at all.
        $staged_id = $this -> staged(null);
        $first = StagedPost::load($staged_id);
        $second = StagedPost::load($staged_id);

        $this -> assertNotNull($first -> publish());
        $this -> assertNull($second -> publish());
    }

    public function testTheClockPublishesWhatIsDueAndOnlyWhatIsDue(): void
    {
        $due_id = $this -> staged(date('Y-m-d H:i:s', time() - 60));
        $future_id = $this -> staged(date('Y-m-d H:i:s', time() + 86400));
        $draft_id = $this -> staged(null);

        StagedPost::publishDue();

        $this -> assertNull(StagedPost::load($due_id), 'the due row should have published');
        $this -> assertNotNull(StagedPost::load($future_id), 'the future row must wait for its clock');
        $this -> assertNotNull(StagedPost::load($draft_id), 'a draft has no clock and never self-publishes');
    }

    public function testDiscardIsScopedToTheOwner(): void
    {
        $staged_id = $this -> staged(null);
        $owner_id = (int) StagedPost::load($staged_id) -> userId;

        StagedPost::discard($staged_id, $owner_id + 1);
        $this -> assertNotNull(StagedPost::load($staged_id), 'someone else\'s discard must not match');

        StagedPost::discard($staged_id, $owner_id);
        $this -> assertNull(StagedPost::load($staged_id));
    }

    public function testAStaleWorkerRespectsUnschedulingAndLatestDraftFields(): void
    {
        $id = $this -> staged(date('Y-m-d H:i:s', time() - 60));
        $stale = StagedPost::load($id);
        StagedPost::update($id, (int) $stale -> userId, 'Revised title', 'Revised body', null, null, null, null, null, 1, 'Spoilers');
        $this -> assertNull($stale -> publish(true), 'unscheduling must stop a worker that already loaded the draft');
        $this -> assertNotNull(StagedPost::load($id));

        $post_id = $stale -> publish();
        $post = DB::row('SELECT * FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post_id);
        $this -> assertSame('Revised title', $post -> title);
        $this -> assertSame('Revised body', $post -> description);
        $this -> assertSame(1, $post -> sensitive);
        $this -> assertSame('Spoilers', $post -> contentWarning);
    }

    public function testReschedulingIntoTheFutureStopsAnAlreadyLoadedWorker(): void
    {
        $id = $this -> staged(date('Y-m-d H:i:s', time() - 60));
        $stale = StagedPost::load($id);
        DB::run('UPDATE `StagedPosts` SET `publishAt` = NOW() + INTERVAL 1 DAY WHERE `stagedPostId` = ?', 'i', $id);
        $this -> assertNull($stale -> publish(true));
        $this -> assertNotNull(StagedPost::load($id));
    }

    public function testALatePublicationFailurePreservesTheDraftAndRollsBackThePost(): void
    {
        $id = $this -> staged(null);
        DB::run('UPDATE `StagedPosts` SET `latitude` = 10, `longitude` = 20 WHERE `stagedPostId` = ?', 'i', $id);
        $draft = StagedPost::load($id);
        mysqli_query(DB::connection(), '
CREATE TRIGGER `StagedPostFailLocation` BEFORE INSERT ON `PostLocations` FOR EACH ROW
SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'forced location failure\'');
        try {
            try {
                $draft -> publish();
                $this -> assertTrue(false, 'the failure must escape');
            } catch (\mysqli_sql_exception $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'forced location failure'));
            }
            $this -> assertNotNull(StagedPost::load($id));
            $this -> assertNull(DB::row('SELECT `postId` FROM `Posts` WHERE `userId` = ?', 'Post', 'i', $draft -> userId));
        } finally {
            mysqli_query(DB::connection(), 'DROP TRIGGER `StagedPostFailLocation`');
        }
        $this -> assertNotNull($draft -> publish(), 'the original draft can be retried');
    }

    public function testStagingPreservesWarningSettings(): void
    {
        $id = StagedPost::stage(self::createUser(), 'Title', 'Body', null, null, null, null, 1, null, 'Warning');
        $draft = StagedPost::load($id);
        $this -> assertSame(1, $draft -> sensitive);
        $this -> assertSame('Warning', $draft -> contentWarning);
        $post_id = $draft -> publish();
        $post = DB::row('SELECT * FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post_id);
        $this -> assertSame('Warning', $post -> contentWarning);
    }

    public function testABannedAuthorsScheduledPostIsDroppedNotPublished(): void
    {
        $staged_id = $this -> staged(date('Y-m-d H:i:s', time() - 60));
        $staged = StagedPost::load($staged_id);

        DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', (int) $staged -> userId);

        $this -> assertNull($staged -> publish());
        $this -> assertNull(StagedPost::load($staged_id), 'the row should be dropped either way');
    }
}
