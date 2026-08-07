<?php

declare(strict_types=1);

/**
 * What a profile says about itself. Currently the Friends button, which carries
 * a count for a member here and does not for an account that lives elsewhere.
 */
class UserProfileTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function shadowUser(): User
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'sssssi',
            'test-remote-' . bin2hex(random_bytes(6)),
            'test-' . bin2hex(random_bytes(6)) . '@example.test',
            password_hash('x', PASSWORD_DEFAULT),
            'Remote Person',
            $actor_uri,
            1
        );

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    public function testTheFriendsButtonCarriesTheCount(): void
    {
        $user = self::localUser();
        $friend = self::localUser();

        $this -> assertSame('View Friends (0)', $user -> friendsButtonLabel());

        $accepted = 'accepted';

        DB::run('
INSERT INTO `Friendships` (`requesterId`, `addresseeId`, `status`)
    VALUES (?, ?, ?)
', 'iis', (int) $user -> userId, (int) $friend -> userId, $accepted);

        // The label reads the maintained cache, so the row alone proves
        // nothing - this is the healer the app runs on sign-in.
        User::recomputeFriendCount((int) $user -> userId);

        $reloaded = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $user -> userId);

        $this -> assertSame('View Friends (1)', $reloaded -> friendsButtonLabel());
    }

    public function testARemoteProfileGetsNoCount(): void
    {
        // Friendship is a relationship held here; people follow a Fediverse
        // account rather than befriending it, so a number beside their name
        // would state something about them that is only true of this server.
        $this -> assertSame('View Friends', self::shadowUser() -> friendsButtonLabel());
    }
}
