<?php

declare(strict_types=1);

class PrecomputedPlacesTest extends DatabaseTestCase
{
    private static function post(int $user_id): int
    {
        DB::run('INSERT INTO `Posts` (`userId`) VALUES (?)', 'i', $user_id);

        return (int) mysqli_insert_id(DB::connection());
    }

    public function testSavedPlacesAreReadWithoutRepeatingTheNearestSearch(): void
    {
        $author = self::createUser();
        $place_id = 903000001;

        try {
            DB::run('INSERT INTO `Places` (`placeId`, `title`, `latitude`, `longitude`) VALUES (?, ?, ?, ?)', 'isdd', $place_id, 'Stored Town', -61.2345, 88.7654);
            $post = self::post($author);
            PostLocation::save($post, -61.2345, 88.7654);

            // Moving the gazetteer entry without running its importer leaves
            // the stored identity intact; reads must not perform a new search.
            DB::run('UPDATE `Places` SET `latitude` = 0, `longitude` = 0 WHERE `placeId` = ?', 'i', $place_id);
            $before = DB::queryCount();
            $locations = PostLocation::forPosts([$post]);
            $this -> assertSame(1, DB::queryCount() - $before);
            $this -> assertSame('Stored Town', $locations[$post]['placeLabel']);
            $this -> assertSame('Stored Town', ActivityPubPlace::forPost($post)['name']);

            PostLocation::save($post, 30.1234, -160.5678);
            $this -> assertNull(PostLocation::forPosts([$post])[$post]['placeLabel']);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
            DB::run('DELETE FROM `Places` WHERE `placeId` = ?', 'i', $place_id);
        }
    }

    public function testInstallerResolvesLegacyLocationsAndRemembersMissingPlaces(): void
    {
        $author = self::createUser();

        try {
            $post = self::post($author);
            DB::run('INSERT INTO `PostLocations` (`postId`, `latitude`, `longitude`) VALUES (?, ?, ?)', 'idd', $post, -70.25, -155.75);
            $this -> assertTrue(PostLocation::resolvePending() > 0);
            $location = DB::row('SELECT * FROM `PostLocations` WHERE `postId` = ?', 'PostLocation', 'i', $post);
            $this -> assertSame(1, $location -> placeResolved);
            $this -> assertNull($location -> placeId);
            $this -> assertSame(0, PostLocation::resolvePending());
            $this -> assertNull(PostLocation::forPosts([$post])[$post]['placeLabel']);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
        }
    }

    public function testGazetteerImportRechecksPreviouslyUnnamedLocations(): void
    {
        $author = self::createUser();
        $post = self::post($author);
        $place_id = 903000002;
        $directory = sys_get_temp_dir() . '/glommer-location-cache-' . bin2hex(random_bytes(6));
        mkdir($directory);

        try {
            PostLocation::save($post, 83.12, -115.67);
            $this -> assertNull(PostLocation::forPosts([$post])[$post]['placeLabel']);
            file_put_contents($directory . '/cities', implode("\t", [$place_id, 'New Town', '', '', '83.12', '-115.67', 'P', 'PPL', 'XX', '', '', '', '', '', '100']) . "\n");
            file_put_contents($directory . '/regions', '');
            file_put_contents($directory . '/countries', '');

            Place::import($directory . '/cities', $directory . '/regions', $directory . '/countries');
            $this -> assertSame('New Town', PostLocation::forPosts([$post])[$post]['placeLabel']);
            $this -> assertSame(0, PostLocation::resolvePending());
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
            DB::run('DELETE FROM `Places` WHERE `placeId` = ?', 'i', $place_id);
            array_map('unlink', glob($directory . '/*'));
            rmdir($directory);
        }
    }
}
