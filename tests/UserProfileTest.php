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

    /** The rendered actions on a card, as the admin sees them. */
    private static function actionsFor(User $user): string
    {
        $was = $_SESSION['userId'] ?? null;
        // The admin, who is the only one offered the moderator button at all.
        $_SESSION['userId'] = 1;

        try {
            (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

            // Hydrated straight off the row as the card's own class, the way
            // every listing builds one.
            $card = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'OtherUser', 'i', (int) $user -> userId);

            $element = $card -> toDOM();
            HTMLObject::currentDocument() -> appendChild($element);

            return (string) HTMLObject::currentDocument() -> saveHTML($element);
        } finally {
            if ($was === null) {
                unset($_SESSION['userId']);
            } else {
                $_SESSION['userId'] = $was;
            }
        }
    }

    /**
     * Neither of these means anything for an account that lives elsewhere.
     * Friendship is held on this server, and moderating is done by signing in
     * here - which nobody on another server can do.
     */
    public function testAFediverseAccountIsOfferedNeitherFriendsNorModeration(): void
    {
        $markup = self::actionsFor(self::shadowUser());

        $this -> assertFalse(str_contains($markup, '/friends'), 'a Fediverse account has no friends here to view');
        $this -> assertFalse(str_contains($markup, 'UserModButton'), 'a Fediverse account cannot moderate this server');
    }

    /** A member is offered both, which is what makes their absence above mean something. */
    public function testAMemberIsOfferedBoth(): void
    {
        $markup = self::actionsFor(self::localUser());

        $this -> assertTrue(str_contains($markup, '/friends'));
        $this -> assertTrue(str_contains($markup, 'UserModButton'));
    }
}
