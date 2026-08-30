<?php

declare(strict_types=1);

/**
 * Which preserved originals a moderator may read.
 *
 * The kept originals outlive the post they belong to - that is what makes a
 * report about deleted media answerable at all - and direct access is denied
 * by uploads/private/.htaccess, leaving api/report-attachment.php as the
 * application path to them. Being mod-gated is
 * not the same as being scoped: what a moderator may open is what a report
 * captured, not every upload on the server that has an id.
 */
class ReportAttachmentAccessTest extends DatabaseTestCase
{
    private function createPost(int $user_id): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'a post with something attached', '[{"insert":"a post with something attached\n"}]');

        return (int) mysqli_insert_id(DB::connection());
    }

    private function attach(int $post_id): int
    {
        DB::run('
INSERT INTO `FeedItems` (`postId`, `type`)
    VALUES (?, ?)
', 'is', $post_id, 'ImageItem');

        return (int) mysqli_insert_id(DB::connection());
    }

    public function testAnAttachmentAReportCapturedCanBeRead(): void
    {
        $author = self::createUser();
        $reporter = self::createUser();

        $post_id = $this -> createPost($author);
        $item_id = $this -> attach($post_id);

        $this -> assertTrue(ReportManager::create($reporter, 'post', $post_id, 'harassment', null));
        $this -> assertTrue(ReportManager::capturedAttachment($item_id));
    }

    /**
     * The whole point of the check. An upload nobody reported is somebody's
     * ordinary post, and its original is not a moderator's to open by guessing
     * a number.
     */
    public function testAnAttachmentNoReportCapturedCannotBeRead(): void
    {
        $author = self::createUser();

        $unreported_post = $this -> createPost($author);
        $unreported_item = $this -> attach($unreported_post);

        $this -> assertFalse(ReportManager::capturedAttachment($unreported_item));
    }

    /**
     * The case the endpoint exists for: the post is gone, its rows with it,
     * and the snapshot taken at report time is the only record of what was
     * attached.
     */
    public function testAnAttachmentStaysReadableAfterThePostIsDeleted(): void
    {
        $author = self::createUser();
        $reporter = self::createUser();

        $post_id = $this -> createPost($author);
        $item_id = $this -> attach($post_id);

        $this -> assertTrue(ReportManager::create($reporter, 'post', $post_id, 'harassment', null));

        DB::run('
DELETE FROM `FeedItems`
    WHERE `itemId` = ?
', 'i', $item_id);

        DB::run('
DELETE FROM `Posts`
    WHERE `postId` = ?
', 'i', $post_id);

        $this -> assertTrue(ReportManager::capturedAttachment($item_id), 'the snapshot still remembers it');
    }

    /** An id that shares digits with a captured one is a different id. */
    public function testAnIdIsMatchedWholeRatherThanAsDigits(): void
    {
        $author = self::createUser();
        $reporter = self::createUser();

        $post_id = $this -> createPost($author);
        $item_id = $this -> attach($post_id);

        $this -> assertTrue(ReportManager::create($reporter, 'post', $post_id, 'harassment', null));

        $shorter = (int) substr((string) $item_id, 0, -1);

        $this -> assertFalse(ReportManager::capturedAttachment((int) ((string) $item_id . '0')), 'a longer id');

        if ($shorter > 0) {
            $this -> assertFalse(ReportManager::capturedAttachment($shorter), 'a shorter one');
        }
    }
}
