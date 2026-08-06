<?php

declare(strict_types=1);

/**
 * The unread mark, which stands where the site's name used to carry a
 * decorative one. It compares the newest message somebody has been sent
 * against the newest they had when they last opened their conversations -
 * the same shape the notification mark uses, and for the same reason: a read
 * flag per message would be a row per message per person to answer yes or no.
 */
class MessageDotTest extends DatabaseTestCase
{
    private function userWith(int $last_seen): int
    {
        $user_id = self::createUser();

        DB::run('
UPDATE `Users`
    SET `lastMessageId` = ?
    WHERE `userId` = ?
', 'ii', $last_seen, $user_id);

        return $user_id;
    }

    public function testNothingReceivedMeansNoMark(): void
    {
        $this -> assertFalse(MessageDot::unreadFor(User::load($this -> userWith(0))));
    }

    public function testAMessageArrivingRaisesTheMark(): void
    {
        $recipient = $this -> userWith(0);
        self::createMessage(self::createUser(), $recipient);

        $this -> assertTrue(MessageDot::unreadFor(User::load($recipient)));
    }

    /** Opening the conversations list is what clears it. */
    public function testSeeingTheConversationsClearsTheMark(): void
    {
        $recipient = $this -> userWith(0);
        self::createMessage(self::createUser(), $recipient);

        Message::markSeen($recipient);

        $this -> assertFalse(MessageDot::unreadFor(User::load($recipient)));
    }

    public function testAMessageAfterThatRaisesItAgain(): void
    {
        $recipient = $this -> userWith(0);
        $sender = self::createUser();

        self::createMessage($sender, $recipient);
        Message::markSeen($recipient);
        self::createMessage($sender, $recipient);

        $this -> assertTrue(MessageDot::unreadFor(User::load($recipient)));
    }

    /** Sending is not receiving - your own message is not news to you. */
    public function testYourOwnSentMessageDoesNotMarkYou(): void
    {
        $sender = $this -> userWith(0);
        self::createMessage($sender, self::createUser());

        $this -> assertFalse(MessageDot::unreadFor(User::load($sender)));
    }

    /**
     * The combined mark stands in for both while the menu is shut, so either
     * kind of thing waiting has to raise it.
     */
    public function testTheCombinedMarkAnswersForMessagesToo(): void
    {
        $recipient = $this -> userWith(0);

        $this -> assertFalse(NavAlertDot::anythingNewFor(User::load($recipient)));

        self::createMessage(self::createUser(), $recipient);

        $this -> assertTrue(NavAlertDot::anythingNewFor(User::load($recipient)));
    }

    public function testTheCombinedMarkAnswersForNotifications(): void
    {
        $recipient = $this -> userWith(0);

        Notification::create($recipient, self::createUser(), 'like', 1);

        $this -> assertTrue(NavAlertDot::anythingNewFor(User::load($recipient)));
    }
}
