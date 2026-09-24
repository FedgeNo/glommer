<?php

declare(strict_types=1);

class ReportDeletionTest extends DatabaseTestCase
{
    private function withPost(callable $test): void
    {
        $root = sys_get_temp_dir() . '/glommer-delete-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $saved = [];
        foreach (['uploadDirectory' => $root . '/public', 'originalsDirectory' => $root . '/originals'] as $name => $path) {
            $property = new \ReflectionProperty(UploadProcessor::class, $name);
            $saved[] = [$property, $property -> getValue()];
            $property -> setValue(null, $path);
        }
        $session = $_SESSION ?? [];
        $user = self::createUser();
        $_SESSION = ['userId' => $user];
        $report = $item = null;
        try {
            DB::run('INSERT INTO `Posts` (`userId`, `replyCount`) VALUES (?, 1)', 'i', $user);
            $parent = (int) mysqli_insert_id(DB::connection());
            DB::run('INSERT INTO `Posts` (`userId`, `parentId`, `description`) VALUES (?, ?, ?)', 'iis', $user, $parent, 'Reported reply');
            $post = (int) mysqli_insert_id(DB::connection());
            DB::run('INSERT INTO `FeedItems` (`postId`, `type`) VALUES (?, ?)', 'is', $post, 'ImageItem');
            $item = (int) mysqli_insert_id(DB::connection());
            $paths = (new \ReflectionMethod(UploadProcessor::class, 'outputPaths')) -> invoke(null, $item, 'ImageItem', 'jpg');
            foreach ($paths as $path) {
                if ($path !== null) {
                    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
                    file_put_contents($path, 'retained bytes');
                }
            }
            ReportManager::create($user, 'post', $post, 'Delete this');
            $report = (int) mysqli_insert_id(DB::connection());
            FediverseFollower::add($user, 'https://delete.invalid/actor', 'https://delete.invalid/inbox', null, 'https://delete.invalid/follow');
            $test($user, $parent, $post, $item, $report, $paths);
        } finally {
            $_SESSION = $session;
            if ($report !== null) {
                ReportManager::delete($report);
                DB::run('DELETE FROM `ModerationActions` WHERE `reportId` = ?', 'i', $report);
            }
            if ($item !== null) DB::run('DELETE FROM `MediaDeletions` WHERE `itemId` = ?', 'i', $item);
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user);
            foreach ($saved as [$property, $value]) $property -> setValue(null, $value);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file -> isDir() ? rmdir($file -> getPathname()) : unlink($file -> getPathname());
            rmdir($root);
        }
    }

    public function testAuditFailureRollsBackDeletionCountsFederationAndCleanup(): void
    {
        $this -> withPost(function ($user, $parent, $post, $item, $report, $paths): void {
            mysqli_query(DB::connection(), "CREATE TRIGGER ReportDeletionFailAudit BEFORE INSERT ON ModerationActions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced deletion audit failure'");
            try {
                try {
                    ReportManager::deleteContent($report);
                    $this -> assertTrue(false, 'Expected injected audit failure');
                } catch (\mysqli_sql_exception $exception) {
                    $this -> assertTrue(str_contains($exception -> getMessage(), 'forced deletion audit failure'));
                }
                $this -> assertNotNull(ReportManager::find($report));
                $this -> assertNotNull(DB::row('SELECT `postId` FROM `Posts` WHERE `postId` = ?', Post::class, 'i', $post));
                $this -> assertSame(1, DB::row('SELECT `replyCount` FROM `Posts` WHERE `postId` = ?', Post::class, 'i', $parent) -> replyCount);
                $this -> assertNull(DB::row('SELECT `itemId` FROM `MediaDeletions` WHERE `itemId` = ?', \stdClass::class, 'i', $item));
                $this -> assertCount(0, DB::rows('SELECT `deliveryId` FROM `FediverseDeliveries` WHERE `actorUserId` = ?', \stdClass::class, 'i', $user));
                foreach ($paths as $path) if ($path !== null) $this -> assertTrue(is_file($path));
            } finally {
                mysqli_query(DB::connection(), 'DROP TRIGGER ReportDeletionFailAudit');
            }
        });
    }

    public function testCommittedDeletionQueuesOnceAndPreservesTheForensicOriginal(): void
    {
        $this -> withPost(function ($user, $parent, $post, $item, $report, $paths): void {
            $this -> assertTrue(ReportManager::deleteContent($report));
            $this -> assertFalse(ReportManager::deleteContent($report));
            $this -> assertNull(ReportManager::find($report));
            $this -> assertNull(DB::row('SELECT `postId` FROM `Posts` WHERE `postId` = ?', Post::class, 'i', $post));
            $this -> assertSame(0, DB::row('SELECT `replyCount` FROM `Posts` WHERE `postId` = ?', Post::class, 'i', $parent) -> replyCount);
            $this -> assertCount(1, DB::rows('SELECT `actionId` FROM `ModerationActions` WHERE `reportId` = ?', \stdClass::class, 'i', $report));
            $this -> assertCount(1, DB::rows('SELECT `deliveryId` FROM `FediverseDeliveries` WHERE `actorUserId` = ?', \stdClass::class, 'i', $user));
            $this -> assertFalse(file_exists($paths['display']));
            $this -> assertTrue(is_file($paths['original']));
        });
    }

    public function testFailedMediaRemovalRemainsQueuedAndTheWorkerCanRetry(): void
    {
        $this -> withPost(function ($user, $parent, $post, $item, $report, $paths): void {
            unlink($paths['display']);
            mkdir($paths['display']); // unlink fails even when the test runner is root.
            $this -> assertTrue(ReportManager::deleteContent($report));
            $this -> assertNotNull(DB::row('SELECT `itemId` FROM `MediaDeletions` WHERE `itemId` = ?', \stdClass::class, 'i', $item));
            rmdir($paths['display']);
            file_put_contents($paths['display'], 'retry me');
            DB::run('UPDATE `MediaDeletions` SET `nextAttemptAt` = NOW() WHERE `itemId` = ?', 'i', $item);
            MediaDeletion::process($item);
            $this -> assertFalse(file_exists($paths['display']));
            $this -> assertNull(DB::row('SELECT `itemId` FROM `MediaDeletions` WHERE `itemId` = ?', \stdClass::class, 'i', $item));
        });
    }

    public function testDeletingReportedPostIncludesReplyMediaAndTombstone(): void
    {
        $this -> withPost(function ($user, $parent, $post, $item, $report): void {
            $uri = 'https://delete.invalid/new-reply-' . bin2hex(random_bytes(8));
            $reply_item = DB::transaction(static function () use ($user, $post, $uri): int {
                DB::row('SELECT `userId` FROM `Users` WHERE `userId` = ? FOR UPDATE', User::class, 'i', $user);
                Post::lockForUpdate($post);
                Post::adjustCounts($post, replies: 1);
                DB::run('INSERT INTO `Posts` (`userId`, `parentId`, `remoteObjectURI`) VALUES (?, ?, ?)', 'iis', $user, $post, $uri);
                $reply = (int) mysqli_insert_id(DB::connection());
                $type = 'ImageItem';
                DB::run('INSERT INTO `FeedItems` (`postId`, `type`) VALUES (?, ?)', 'is', $reply, $type);

                return (int) mysqli_insert_id(DB::connection());
            });

            $paths = (new \ReflectionMethod(UploadProcessor::class, 'outputPaths')) -> invoke(null, $reply_item, 'ImageItem', 'jpg');
            foreach ($paths as $path) {
                if ($path !== null) {
                    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
                    file_put_contents($path, 'reply media');
                }
            }

            try {
                $this -> assertTrue(ReportManager::deleteContent($report));
                $this -> assertFalse(file_exists($paths['display']));
                $this -> assertTrue(is_file($paths['original']));
                $this -> assertTrue(RemoteObjectTombstone::isTombstoned($uri));
            } finally {
                DB::run('DELETE FROM `RemoteObjectTombstones` WHERE `remoteObjectURI` = ?', 's', $uri);
            }
        });
    }

    public function testDeletingMessageKeepsLatestConversationAfterAnotherSend(): void
    {
        $first = self::createUser();
        $second = self::createUser();
        try {
            Message::create($first, $second, 'old');
            $reported = Message::create($second, $first, 'reported');
            $new = Message::create($first, $second, 'new');
            Message::delete($reported);

            $rows = DB::rows('SELECT `lastMessageId` FROM `Conversations` WHERE `userId` IN (?, ?)', \stdClass::class, 'ii', $first, $second);
            $this -> assertCount(2, $rows);
            foreach ($rows as $row) $this -> assertSame($new, (int) $row -> lastMessageId);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $first, $second);
        }
    }

    public function testMessageAuditFailureRestoresBothConversationPointers(): void
    {
        $first = self::createUser();
        $second = self::createUser();
        $session = $_SESSION ?? [];
        $_SESSION = ['userId' => $first];
        $old = Message::create($first, $second, 'old');
        $message = Message::create($second, $first, 'reported');
        ReportManager::create($first, 'message', $message, 'Review');
        $report = (int) mysqli_insert_id(DB::connection());
        mysqli_query(DB::connection(), "CREATE TRIGGER ReportDeletionFailMessage BEFORE INSERT ON ModerationActions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced message audit failure'");
        try {
            try {
                ReportManager::deleteContent($report);
                $this -> assertTrue(false);
            } catch (\mysqli_sql_exception $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'forced message audit failure'));
            }
            foreach (DB::rows('SELECT `lastMessageId` FROM `Conversations` WHERE `userId` IN (?, ?)', \stdClass::class, 'ii', $first, $second) as $row) {
                $this -> assertSame($message, (int) $row -> lastMessageId);
            }
            mysqli_query(DB::connection(), 'DROP TRIGGER ReportDeletionFailMessage');
            $this -> assertTrue(ReportManager::deleteContent($report));
            foreach (DB::rows('SELECT `lastMessageId` FROM `Conversations` WHERE `userId` IN (?, ?)', \stdClass::class, 'ii', $first, $second) as $row) {
                $this -> assertSame($old, (int) $row -> lastMessageId);
            }
        } finally {
            mysqli_query(DB::connection(), 'DROP TRIGGER IF EXISTS ReportDeletionFailMessage');
            $_SESSION = $session;
            ReportManager::delete($report);
            DB::run('DELETE FROM `ModerationActions` WHERE `reportId` = ?', 'i', $report);
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $first, $second);
        }
    }
}
