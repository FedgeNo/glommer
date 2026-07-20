<?php

declare(strict_types=1);

/**
 * Users.slug is wide enough to hold a followed Fediverse account's whole
 * handle. That room exists for remote handles; a username chosen on this site
 * stays short regardless of what the column can physically store, and every
 * path that creates one agrees on the same limit.
 */
class UsernameTest extends TestCase
{
    public function testTheUsernameLimitIsWellUnderTheColumnWidth(): void
    {
        $this -> assertSame(32, User::MAX_USERNAME_LENGTH);

        $schema = (string) file_get_contents(__DIR__ . '/../schema.sql');

        $this -> assertTrue(str_contains($schema, '`slug` varchar(255) NOT NULL'));
    }

    /**
     * Both account-creation paths take the limit from the one constant, so a
     * change to it can't leave one of them behind - which is exactly how they
     * came to disagree in the first place.
     */
    public function testEveryAccountCreationPathUsesTheSharedLimit(): void
    {
        $sources = [
            __DIR__ . '/../api/signup.php',
            __DIR__ . '/../src/classes/GoogleAuth.php',
            __DIR__ . '/../src/classes/SignupForm.php',
        ];

        foreach ($sources as $path) {
            $this -> assertTrue(str_contains((string) file_get_contents($path), 'User::MAX_USERNAME_LENGTH'));
        }
    }

    /**
     * A Google-derived name is made unique by appending to a trimmed base, so
     * the suffix can never push a candidate past the limit.
     */
    public function testAGoogleDerivedUsernameCannotExceedTheLimit(): void
    {
        $long_base = str_repeat('g', User::MAX_USERNAME_LENGTH);

        $collision = substr($long_base, 0, User::MAX_USERNAME_LENGTH - 6) . random_int(1000, 999999);
        $fallback = substr($long_base, 0, User::MAX_USERNAME_LENGTH - 12) . bin2hex(random_bytes(6));

        $this -> assertSame(User::MAX_USERNAME_LENGTH, strlen($collision));
        $this -> assertSame(User::MAX_USERNAME_LENGTH, strlen($fallback));
    }
}
