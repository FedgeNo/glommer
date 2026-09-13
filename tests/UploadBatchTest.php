<?php

declare(strict_types=1);

class UploadBatchTest extends DatabaseTestCase
{
    /** All media and queue operations use a private temporary tree, never live uploads. */
    private function withBatch(callable $test): void
    {
        $root = sys_get_temp_dir() . '/glommer-upload-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $properties = [];
        foreach ([
            [UploadProcessor::class, 'uploadDirectory', $root . '/media'],
            [UploadProcessor::class, 'originalsDirectory', $root . '/originals'],
            [UploadBatch::class, 'stagingDirectory', $root . '/staging'],
            [UploadBatch::class, 'pendingDirectory', $root . '/pending'],
            [UploadBatch::class, 'processingDirectory', $root . '/processing'],
        ] as [$class, $name, $path]) {
            $property = new \ReflectionProperty($class, $name);
            $properties[] = [$property, $property -> getValue()];
            $property -> setValue(null, $path);
        }
        $id = self::createUser();
        $batch_id = null;
        try {
            file_put_contents($root . '/input', 'original upload');
            $batch_id = UploadBatch::stage($id, null, 'Crash recovery', null, null, null, [
                ['tmpPath' => $root . '/input', 'originalFilename' => 'one.jpg', 'altText' => 'First image'],
                ['tmpPath' => $root . '/input', 'originalFilename' => 'two.mp4'],
            ]);
            $this -> assertSame($batch_id, UploadBatch::claimNext());
            $directory = $root . '/processing/' . $batch_id;
            $progress = ['publicationVersion' => 1, 'finalizing' => true, 'started' => null, 'files' => []];
            foreach (['ImageItem', 'VideoItem'] as $index => $type) {
                $file = ['status' => 'done', 'deaths' => 0, 'seed' => bin2hex(random_bytes(8)) . '-' . $index,
                    'itemType' => $type, 'ext' => $index === 0 ? 'jpg' : 'mp4'];
                foreach (self::paths($file['seed'], $type, $file['ext']) as $kind => $path) {
                    if ($path !== null) {
                        if (!is_dir(dirname($path))) {
                            mkdir(dirname($path), 0755, true);
                        }
                        file_put_contents($path, $kind . '-' . $index);
                    }
                }
                $progress['files'][] = $file;
            }
            file_put_contents($directory . '/progress.json', json_encode($progress));
            DB::run('INSERT INTO `UploadPublications` (`batchId`) VALUES (?)', 's', $batch_id);
            $test($id, $batch_id, $directory, $progress, $root);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $id);
            if ($batch_id !== null) {
                DB::run('DELETE FROM `UploadPublications` WHERE `batchId` = ?', 's', $batch_id);
            }
            foreach ($properties as [$property, $value]) {
                $property -> setValue(null, $value);
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file -> isDir() ? rmdir($file -> getPathname()) : unlink($file -> getPathname());
            }
            rmdir($root);
        }
    }

    private static function paths(int|string $id, string $type, ?string $extension): array
    {
        return (new \ReflectionMethod(UploadProcessor::class, 'outputPaths')) -> invoke(null, $id, $type, $extension);
    }

    /** Stop at the real post-commit/pre-cleanup boundary, as a dying worker would. */
    private static function publish(string $directory): void
    {
        (new \ReflectionMethod(UploadBatch::class, 'finalize')) -> invoke(null, $directory,
            json_decode(file_get_contents($directory . '/metadata.json'), true),
            json_decode(file_get_contents($directory . '/progress.json'), true));
    }

    private static function posts(int $user_id): array
    {
        return DB::rows('SELECT `postId` FROM `Posts` WHERE `userId` = ?', 'stdClass', 'i', $user_id);
    }

    public function testPublicationKeepsAllMediaAndCommitsExactlyOneNotification(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress): void {
            UploadBatch::process($batch_id);
            $posts = self::posts($user_id);
            $this -> assertCount(1, $posts);
            $items = DB::rows('SELECT * FROM `FeedItems` WHERE `postId` = ? ORDER BY `itemId`', 'stdClass', 'i', $posts[0] -> postId);
            $this -> assertCount(2, $items);
            foreach ($items as $index => $item) {
                foreach (self::paths((int) $item -> itemId, $item -> type, 'bin') as $kind => $path) {
                    if ($path !== null) {
                        $this -> assertSame($kind . '-' . $index, file_get_contents($path));
                    }
                }
                $this -> assertFalse(UploadProcessor::exists($progress['files'][$index]['seed'], $item -> type));
            }
            $this -> assertSame('First image', $items[0] -> altText);
            $this -> assertCount(1, DB::rows('SELECT `notificationId` FROM `Notifications` WHERE `userId` = ?', 'stdClass', 'i', $user_id));
            $this -> assertFalse(is_dir($directory));
            $this -> assertNull(DB::row('SELECT `batchId` FROM `UploadPublications` WHERE `batchId` = ?', 'stdClass', 's', $batch_id));
        });
    }

    public function testPartialFilePublicationRollsBackAndResumesWithoutLosingFinishedSources(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress): void {
            $missing = self::paths($progress['files'][1]['seed'], 'VideoItem', 'mp4')['original'];
            unlink($missing);
            try {
                UploadBatch::process($batch_id);
                $this -> assertTrue(false, 'Publication should fail on a missing original');
            } catch (\RuntimeException $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'source missing'));
            }
            $this -> assertCount(0, self::posts($user_id));
            $this -> assertCount(0, DB::rows('SELECT `notificationId` FROM `Notifications` WHERE `userId` = ?', 'stdClass', 'i', $user_id));
            $this -> assertCount(0, DB::rows('SELECT `deliveryId` FROM `FediverseDeliveries` WHERE `actorUserId` = ?', 'stdClass', 'i', $user_id));
            $manifest = json_decode(file_get_contents($directory . '/progress.json'), true)['publishingItems'];
            $this -> assertCount(2, $manifest);
            $this -> assertTrue(UploadProcessor::exists($manifest[0]['itemId'], $manifest[0]['type']));
            $this -> assertTrue(UploadProcessor::exists($progress['files'][0]['seed'], 'ImageItem'));
            file_put_contents($missing, 'original-1');
            UploadBatch::recoverDied($batch_id);
            $this -> assertSame($batch_id, UploadBatch::claimNext());
            UploadBatch::process($batch_id);
            $this -> assertCount(1, self::posts($user_id));
            foreach ($manifest as $item) {
                $this -> assertFalse(UploadProcessor::exists($item['itemId'], $item['type']));
            }
        });
    }

    public function testCrashAfterCommitOnlyFinishesCleanupAndNeverRepeatsFederation(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress): void {
            FediverseFollower::add($user_id, 'https://upload.invalid/actor', 'https://upload.invalid/inbox', null, 'https://upload.invalid/follow');
            self::publish($directory);
            $post_id = self::posts($user_id)[0] -> postId;
            // Simulate death partway through cleanup, after one seed disappeared.
            UploadProcessor::purgeStaged($progress['files'][0]['seed']);
            UploadBatch::recoverDied($batch_id);
            $this -> assertSame($batch_id, UploadBatch::claimNext());
            UploadBatch::process($batch_id);
            $this -> assertSame($post_id, self::posts($user_id)[0] -> postId);
            $this -> assertCount(1, self::posts($user_id));
            $this -> assertCount(1, DB::rows('SELECT `deliveryId` FROM `FediverseDeliveries` WHERE `actorUserId` = ?', 'stdClass', 'i', $user_id));
            $this -> assertCount(1, DB::rows('SELECT `notificationId` FROM `Notifications` WHERE `userId` = ?', 'stdClass', 'i', $user_id));
        });
    }

    public function testLateDatabaseFailureRollsBackPostCountersNotificationsAndFederationTogether(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory): void {
            DB::run('INSERT INTO `Posts` (`userId`) VALUES (?)', 'i', $user_id);
            $parent_id = (int) mysqli_insert_id(DB::connection());
            $metadata = json_decode(file_get_contents($directory . '/metadata.json'), true);
            $metadata['parentId'] = $parent_id;
            file_put_contents($directory . '/metadata.json', json_encode($metadata));
            FediverseFollower::add($user_id, 'https://rollback.invalid/actor', 'https://rollback.invalid/inbox', null, 'https://rollback.invalid/follow');
            // The final receipt update happens after all publication effects.
            // This trigger exists only in the shared throwaway test database.
            DB::run("CREATE TRIGGER fail_upload_receipt BEFORE UPDATE ON `UploadPublications` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated receipt failure'");
            try {
                try {
                    UploadBatch::process($batch_id);
                    $this -> assertTrue(false, 'The receipt update should fail');
                } catch (\mysqli_sql_exception $exception) {
                    $this -> assertTrue(str_contains($exception -> getMessage(), 'simulated receipt failure'));
                }
            } finally {
                DB::run('DROP TRIGGER fail_upload_receipt');
            }
            $this -> assertCount(1, self::posts($user_id));
            $this -> assertSame(0, (int) DB::row('SELECT `replyCount` FROM `Posts` WHERE `postId` = ?', 'stdClass', 'i', $parent_id) -> replyCount);
            foreach (['Notifications' => 'userId', 'FediverseDeliveries' => 'actorUserId'] as $table => $column) {
                $this -> assertCount(0, DB::rows('SELECT * FROM `' . $table . '` WHERE `' . $column . '` = ?', 'stdClass', 'i', $user_id));
            }
            UploadBatch::process($batch_id);
            $this -> assertCount(2, self::posts($user_id));
            $this -> assertSame(1, (int) DB::row('SELECT `replyCount` FROM `Posts` WHERE `postId` = ?', 'stdClass', 'i', $parent_id) -> replyCount);
        });
    }

    public function testDeletedPublishedPostIsNeverResurrectedByRecovery(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory): void {
            self::publish($directory);
            Post::delete((int) self::posts($user_id)[0] -> postId);
            $receipt = DB::row('SELECT * FROM `UploadPublications` WHERE `batchId` = ?', 'stdClass', 's', $batch_id);
            $this -> assertNull($receipt -> postId);
            $this -> assertSame(1, (int) $receipt -> finished);
            UploadBatch::recoverDied($batch_id);
            UploadBatch::claimNext();
            UploadBatch::process($batch_id);
            $this -> assertCount(0, self::posts($user_id));
            $this -> assertFalse(is_dir($directory));
        });
    }

    public function testGracefulShutdownResumesFinalizationWithoutCountingADeath(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory): void {
            UploadBatch::releaseClaim($batch_id);
            $this -> assertSame($batch_id, UploadBatch::claimNext());
            $progress = json_decode(file_get_contents($directory . '/progress.json'), true);
            $this -> assertSame(0, $progress['finalizationDeaths'] ?? 0);
            UploadBatch::process($batch_id);
            $this -> assertCount(1, self::posts($user_id));
        });
    }

    public function testSecondWorkerCannotEnterALockedBatch(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory): void {
            $lock = fopen($directory . '/worker.lock', 'c');
            flock($lock, LOCK_EX);
            try {
                UploadBatch::process($batch_id);
                $this -> assertCount(0, self::posts($user_id));
            } finally {
                fclose($lock);
            }
            UploadBatch::process($batch_id);
            $this -> assertCount(1, self::posts($user_id));
        });
    }

    public function testTemporaryDatabaseFailureDefersWithoutUsingTheCrashBudget(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress, string $root): void {
            UploadBatch::deferClaim($batch_id);
            $this -> assertNull(UploadBatch::claimNext());
            $path = $root . '/pending/' . $batch_id . '/progress.json';
            $saved = json_decode(file_get_contents($path), true);
            $this -> assertSame(0, $saved['finalizationDeaths'] ?? 0);
            $saved['retryAt'] = time() - 1;
            file_put_contents($path, json_encode($saved));
            $this -> assertSame($batch_id, UploadBatch::claimNext());
            UploadBatch::process($batch_id);
            $this -> assertCount(1, self::posts($user_id));
        });
    }

    public function testRepeatedFinalizationFailureIsReportedOnceAndCleansUp(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress): void {
            $progress['finalizationDeaths'] = 3;
            file_put_contents($directory . '/progress.json', json_encode($progress));
            UploadBatch::process($batch_id);
            UploadBatch::process($batch_id);
            $this -> assertCount(0, self::posts($user_id));
            $notifications = DB::rows('SELECT `type` FROM `Notifications` WHERE `userId` = ?', 'stdClass', 'i', $user_id);
            $this -> assertCount(1, $notifications);
            $this -> assertSame('uploadFailed', $notifications[0] -> type);
            $this -> assertFalse(is_dir($directory));
        });
    }

    public function testDeletionOfTheUploaderDoesNotLeaveAnUnfinishableBatch(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory): void {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user_id);
            UploadBatch::process($batch_id);
            $this -> assertCount(0, self::posts($user_id));
            $this -> assertFalse(is_dir($directory));
        });
    }

    public function testAgeSweepCannotRemoveClaimedOrPendingPublicationSources(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress, string $root): void {
            touch($directory, time() - 172800);
            UploadBatch::sweepOrphanedBatches();
            $this -> assertTrue(is_file($directory . '/metadata.json'));
            UploadBatch::releaseClaim($batch_id);
            $pending = $root . '/pending/' . $batch_id;
            touch($pending, time() - 172800);
            UploadBatch::sweepOrphanedBatches();
            $this -> assertTrue(is_file($pending . '/metadata.json'));
        });
    }

    public function testLegacyFinalizationWithoutAReceiptIsPreservedRatherThanGuessed(): void
    {
        $this -> withBatch(function (int $user_id, string $batch_id, string $directory, array $progress): void {
            unset($progress['publicationVersion']);
            file_put_contents($directory . '/progress.json', json_encode($progress));
            DB::run('DELETE FROM `UploadPublications` WHERE `batchId` = ?', 's', $batch_id);
            UploadBatch::process($batch_id);
            $this -> assertCount(0, self::posts($user_id));
            $this -> assertTrue(is_file($directory . '/metadata.json'));
            $this -> assertTrue(UploadProcessor::exists($progress['files'][0]['seed'], 'ImageItem'));
        });
    }
}
