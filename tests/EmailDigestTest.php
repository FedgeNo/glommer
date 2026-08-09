<?php

declare(strict_types=1);

/**
 * The mail to somebody who has stopped visiting.
 *
 * Two things are worth holding to account here, and they pull against each
 * other. It has to reach a person who has drifted away - which is the whole
 * point of it - and it must never become mail somebody did not ask for and
 * cannot stop: not too often, not when nothing happened, not after they have
 * said no, and never without a way out that works with no password.
 */
class EmailDigestTest extends DatabaseTestCase
{
    private static function reload(int $user_id): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);
    }

    /** Somebody signed up, verified, and last here a month ago. */
    private static function absentee(): int
    {
        $user_id = self::createUser();

        DB::run('
UPDATE `Users`
    SET `verified` = 1, `lastSeen` = NOW() - INTERVAL 30 DAY, `createdAt` = NOW() - INTERVAL 60 DAY
    WHERE `userId` = ?
', 'i', $user_id);

        return $user_id;
    }

    /** Something for them to have missed, however long ago it happened. */
    private static function somethingHappened(int $user_id, int $days_ago = 0): void
    {
        Notification::create($user_id, self::createUser(), 'like');

        if ($days_ago === 0) {
            return;
        }

        // The newest of theirs rather than the last insert id - creating a
        // notification queues a push behind it, which moves that on.
        DB::run('
UPDATE `Notifications`
    SET `createdAt` = NOW() - INTERVAL ? DAY
    WHERE `userId` = ?
    ORDER BY `notificationId` DESC
    LIMIT 1
', 'ii', $days_ago, $user_id);
    }

    private static function isDue(int $user_id): bool
    {
        foreach (EmailDigest::due(1000) as $user) {
            if ((int) $user -> userId === $user_id) {
                return true;
            }
        }

        return false;
    }

    public function testSomebodyLongGoneWithNewsWaitingIsDue(): void
    {
        $user_id = self::absentee();

        $this -> assertFalse(self::isDue($user_id), 'nothing happened while they were away');

        self::somethingHappened($user_id);

        $this -> assertTrue(self::isDue($user_id));
    }

    /** Somebody who is still using the site does not get told what they missed. */
    public function testSomebodyWhoWasHereYesterdayIsNotDue(): void
    {
        $user_id = self::absentee();
        self::somethingHappened($user_id);

        DB::run('
UPDATE `Users`
    SET `lastSeen` = NOW() - INTERVAL 1 DAY
    WHERE `userId` = ?
', 'i', $user_id);

        $this -> assertFalse(self::isDue($user_id));
    }

    /**
     * The cap. However long somebody stays away and however much happens,
     * one of these a week is the most that can ever reach them.
     */
    public function testOneAWeekIsTheMost(): void
    {
        $user_id = self::absentee();
        self::somethingHappened($user_id);

        EmailDigest::markSent($user_id);

        $this -> assertFalse(self::isDue($user_id), 'they have just had one');

        DB::run('
UPDATE `Users`
    SET `emailDigestSent` = NOW() - INTERVAL 8 DAY
    WHERE `userId` = ?
', 'i', $user_id);

        // Something since that digest, or there would be nothing to write.
        self::somethingHappened($user_id);

        $this -> assertTrue(self::isDue($user_id));
    }

    /**
     * A second digest to somebody still away carries what has happened since
     * the first, not the same news over again.
     */
    public function testASecondDigestDoesNotRepeatTheFirst(): void
    {
        $user_id = self::absentee();

        // News from before that first digest, which it will already have
        // carried.
        self::somethingHappened($user_id, 20);

        DB::run('
UPDATE `Users`
    SET `emailDigestSent` = NOW() - INTERVAL 8 DAY
    WHERE `userId` = ?
', 'i', $user_id);

        $this -> assertFalse(self::isDue($user_id), 'everything it would say was said last time');

        self::somethingHappened($user_id);

        $this -> assertTrue(self::isDue($user_id));
    }

    public function testSayingNoStopsThem(): void
    {
        $user_id = self::absentee();
        self::somethingHappened($user_id);

        EmailDigest::setEnabled($user_id, false);

        $this -> assertFalse(self::isDue($user_id));

        EmailDigest::setEnabled($user_id, true);

        $this -> assertTrue(self::isDue($user_id));
    }

    /**
     * Nobody who cannot be mailed, or should not be: an address nobody ever
     * confirmed, an account that was banned, and a Fediverse account that is
     * a shadow of somebody on another server entirely.
     */
    public function testNobodyWhoShouldNotBeMailedIsDue(): void
    {
        $unverified = self::absentee();
        self::somethingHappened($unverified);
        DB::run('
UPDATE `Users`
    SET `verified` = 0
    WHERE `userId` = ?
', 'i', $unverified);

        $banned = self::absentee();
        self::somethingHappened($banned);
        DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', $banned);

        $remote = self::absentee();
        self::somethingHappened($remote);
        DB::run('
UPDATE `Users`
    SET `remoteActorURI` = ?
    WHERE `userId` = ?
', 'si', 'https://elsewhere.test/users/' . $remote, $remote);

        $this -> assertFalse(self::isDue($unverified), 'the address was never confirmed');
        $this -> assertFalse(self::isDue($banned));
        $this -> assertFalse(self::isDue($remote), 'there is nobody here to mail');
    }

    /**
     * This server talking about itself is not news somebody missed. A mail
     * that opens "a server error occurred" is the wrong thing entirely, and
     * anything the notification wording does not know becomes "did something",
     * which is fine beside a timestamp on a page and not fine in an email.
     */
    public function testTheServerTalkingToItselfIsNotNews(): void
    {
        $user_id = self::absentee();

        Notification::create($user_id, $user_id, 'systemError', null, true);
        Notification::create($user_id, $user_id, 'postReady', null, true);

        $this -> assertFalse(self::isDue($user_id), 'none of that is why somebody comes back');
        $this -> assertTrue(new EmailDigest(self::reload($user_id)) -> isEmpty());
    }

    /**
     * Somebody who nobody has interacted with still has a reason to come back:
     * their feed moved. Without this they would be picked every pass and
     * dropped every pass, and hold the queue against people with real news.
     */
    public function testAFeedThatMovedIsWorthWriting(): void
    {
        $user_id = self::absentee();
        $author_id = self::createUser();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $author_id, 'something worth reading', (string) json_encode([['insert' => "something worth reading\n"]]));

        DB::run('
INSERT INTO `Timelines` (`userId`, `postId`, `sortAt`)
    VALUES (?, ?, NOW())
', 'ii', $user_id, (int) mysqli_insert_id(DB::connection()));

        $this -> assertTrue(self::isDue($user_id));

        $digest = new EmailDigest(self::reload($user_id));

        $this -> assertFalse($digest -> isEmpty());
        $this -> assertTrue(str_contains($digest -> textBody(), 'One new post in your feed'));
    }

    /** Repeats collapse - one person liking six posts is one line, not six. */
    public function testRepeatsCollapseIntoOneLine(): void
    {
        $user_id = self::absentee();
        $actor_id = self::createUser();

        DB::run('
UPDATE `Users`
    SET `title` = ?
    WHERE `userId` = ?
', 'si', 'Ash', $actor_id);

        foreach ([1, 2, 3] as $post_id) {
            Notification::create($user_id, $actor_id, 'like', $post_id);
        }

        $text = new EmailDigest(self::reload($user_id)) -> textBody();

        $this -> assertTrue(str_contains($text, Notification::textFor('like', 'Ash') . ' (3 times)'));
    }

    /** What the mail actually says: the things they missed, in the site's wording. */
    public function testTheMailNamesWhatWasMissed(): void
    {
        $user_id = self::absentee();
        $actor_id = self::createUser();

        DB::run('
UPDATE `Users`
    SET `title` = ?
    WHERE `userId` = ?
', 'si', 'Robin', $actor_id);

        Notification::create($user_id, $actor_id, 'reply', null, true);

        $digest = new EmailDigest(self::reload($user_id));

        $this -> assertFalse($digest -> isEmpty());

        $text = $digest -> textBody();

        $this -> assertTrue(str_contains($text, Notification::textFor('reply', 'Robin')), 'the missed reply is named');
        $this -> assertTrue(str_contains($text, EmailDigest::paragraph()), 'the server\'s own closing line is in it');
        $this -> assertTrue(str_contains($digest -> htmlBody(), 'Robin'), 'the HTML half says the same');
    }

    /** Messages are counted as well as listed - repeats collapse into one notification. */
    public function testMessagesWaitingAreCounted(): void
    {
        $user_id = self::absentee();
        $sender_id = self::createUser();

        self::createMessage($sender_id, $user_id);
        self::createMessage($sender_id, $user_id);

        $digest = new EmailDigest(self::reload($user_id));

        $this -> assertFalse($digest -> isEmpty(), 'messages alone are worth writing about');
        $this -> assertTrue(str_contains($digest -> textBody(), '2 messages waiting'));
    }

    /** A digest with nothing in it is never sent. */
    public function testAnEmptyDigestIsNotSent(): void
    {
        $user_id = self::absentee();

        $digest = new EmailDigest(self::reload($user_id));

        $this -> assertTrue($digest -> isEmpty());
        $this -> assertFalse($digest -> send());
        $this -> assertNull(self::reload($user_id) -> emailDigestSent, 'a week was not used up on nothing');
    }

    /**
     * The way out. It has to work for somebody who is signed out - the person
     * it is aimed at has not been here in a month - so the link carries its
     * own authority and names no session.
     */
    public function testTheUnsubscribeLinkNamesItsMemberAndNobodyElse(): void
    {
        $user_id = self::absentee();

        $url = EmailDigestUnsubscribe::URL($user_id);

        $this -> assertNotNull($url, 'no link means no digest is sent at all');

        $query = (string) parse_url((string) $url, PHP_URL_QUERY);
        parse_str($query, $parameters);
        $token = (string) ($parameters['token'] ?? '');

        $this -> assertSame($user_id, EmailDigestUnsubscribe::userIdFor($token));
    }

    /** A token somebody made up, or edited, is nobody's. */
    public function testAForgedUnsubscribeTokenIsRefused(): void
    {
        $user_id = self::absentee();
        $other_id = self::createUser();

        $token = (string) EmailDigestUnsubscribe::URL($user_id);
        $token = substr($token, (int) strpos($token, 'token=') + 6);
        [$named, $tag] = explode('.', $token, 2);

        $this -> assertNull(EmailDigestUnsubscribe::userIdFor($other_id . '.' . $tag), 'the tag is bound to one member');
        $this -> assertNull(EmailDigestUnsubscribe::userIdFor($named . '.' . strrev($tag)));
        $this -> assertNull(EmailDigestUnsubscribe::userIdFor($named));
        $this -> assertNull(EmailDigestUnsubscribe::userIdFor(''));
    }

    /** Using it turns the digests off, and the way back turns them on again. */
    public function testUnsubscribingStopsThemAndCanBeUndone(): void
    {
        $user_id = self::absentee();
        self::somethingHappened($user_id);

        EmailDigest::setEnabled($user_id, false);

        $this -> assertSame(0, (int) self::reload($user_id) -> emailDigests);
        $this -> assertFalse(self::isDue($user_id));

        EmailDigest::setEnabled($user_id, true);

        $this -> assertSame(1, (int) self::reload($user_id) -> emailDigests);
        $this -> assertTrue(self::isDue($user_id));
    }

    /** Builds an object into a document of its own and hands back an XPath over it. */
    private function xpathOver(HTMLObject $object): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        HTMLObject::currentDocument() -> appendChild($object -> toDOM());

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    /**
     * The switch on the member's own settings page. Its class is what the JS
     * twin keys on to save the answer, and the box has to arrive ticked - the
     * mail is on unless somebody turns it off.
     */
    public function testTheSettingRendersATickedBoxTheClientCanFind(): void
    {
        $xpath = $this -> xpathOver(new EmailDigestSetting());

        $boxes = $xpath -> query('//div[contains(@class, "EmailDigestSetting")]//input[@name="emailDigests"]');

        $this -> assertSame(1, $boxes -> length);
        $this -> assertSame('checkbox', $boxes -> item(0) -> getAttribute('type'));
        $this -> assertSame('checked', $boxes -> item(0) -> getAttribute('checked'), 'on unless turned off');
    }

    /** The admin's box, prefilled with whatever the digest would currently say. */
    public function testTheAdminFormOffersTheParagraphItWouldSend(): void
    {
        $xpath = $this -> xpathOver(new EmailDigestSettingsForm());

        $areas = $xpath -> query('//form[contains(@class, "EmailDigestSettingsForm")]//textarea[@name="' . EmailDigest::PARAGRAPH_SETTING . '"]');

        $this -> assertSame(1, $areas -> length);
        $this -> assertSame(EmailDigest::paragraph(), trim((string) $areas -> item(0) -> textContent));
    }

    /** The admin's paragraph, when they have written one. */
    public function testTheServerCanSaySomethingOfItsOwn(): void
    {
        $was = (string) Settings::get(EmailDigest::PARAGRAPH_SETTING, '');

        try {
            Settings::set(EmailDigest::PARAGRAPH_SETTING, 'Come back, the kettle is on.');

            $this -> assertSame('Come back, the kettle is on.', EmailDigest::paragraph());

            // Blank puts the shipped wording back rather than removing it.
            Settings::set(EmailDigest::PARAGRAPH_SETTING, '');

            $this -> assertTrue(EmailDigest::paragraph() !== '');
        } finally {
            Settings::set(EmailDigest::PARAGRAPH_SETTING, $was);
        }
    }
}
