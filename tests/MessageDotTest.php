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

    /** Opening the conversations list is one way to clear it. */
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

    public function testOneUnreadConversationCanBeClearedWhenItsThreadOpens(): void
    {
        $recipient = $this -> userWith(0);
        $sender = self::createUser();
        self::createMessage($sender, $recipient);

        $this -> assertSame(1, Message::unreadConversationCount($recipient));
        Message::markSeen($recipient);
        $this -> assertFalse(MessageDot::unreadFor(User::load($recipient)));
    }

    public function testSeveralUnreadConversationsRemainUnreadUntilTheInboxOpens(): void
    {
        $recipient = $this -> userWith(0);
        self::createMessage(self::createUser(), $recipient);
        self::createMessage(self::createUser(), $recipient);

        $this -> assertSame(2, Message::unreadConversationCount($recipient));
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

    private function assertNavigationAfterSeeing(int $recipient, bool $unseen_notifications): void
    {
        $session = $_SESSION ?? [];
        $_SESSION['userId'] = $recipient;
        Auth::clearUserCache();

        try {
            // Bootstrap has already loaded this user before Messages opens.
            $this -> assertTrue(MessageDot::unreadFor(Auth::user()));

            Message::markSeen($recipient);

            (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
            HTMLObject::currentDocument() -> appendChild((new MainNavigation()) -> toDOM());
            $xpath = new \DOMXPath(HTMLObject::currentDocument());

            foreach (['MessageDot' => 2, 'NotificationDot' => 1, 'NavAlertDot' => 1] as $class => $count) {
                $dots = $xpath -> query('//span[contains(concat(" ", @class, " "), " ' . $class . ' ")]');
                $this -> assertSame($count, $dots -> length, $class);

                foreach ($dots as $dot) {
                    $this -> assertSame(
                        $class !== 'MessageDot' && $unseen_notifications,
                        in_array('Active', explode(' ', $dot -> getAttribute('class')), true),
                        $class
                    );
                }
            }
        } finally {
            $_SESSION = $session;
            Auth::clearUserCache();
        }
    }

    public function testOpeningMessagesClearsEveryDotWhenOnlyMessagesAreUnseen(): void
    {
        $recipient = $this -> userWith(0);
        $sender = self::createUser();
        self::createMessage($sender, $recipient);
        Notification::create($recipient, $sender, 'message');
        Notification::create($recipient, self::createUser(), 'message');

        $this -> assertNavigationAfterSeeing($recipient, false);
        $this -> assertSame(Notification::newestIdFor($recipient), User::load($recipient) -> lastNotificationId);
        $this -> assertSame(2, count((new NotificationList(['userId' => $recipient])) -> items));
    }

    public function testOpeningMessagesPreservesOtherUnseenNotificationsInEitherOrder(): void
    {
        foreach (['like', 'reply', 'friendRequest'] as $type) {
            foreach ([[$type, 'message'], ['message', $type]] as $types) {
                $recipient = $this -> userWith(0);
                $sender = self::createUser();
                self::createMessage($sender, $recipient);

                foreach ($types as $notification_type) {
                    Notification::create($recipient, $sender, $notification_type);
                }

                $this -> assertNavigationAfterSeeing($recipient, true);
                $this -> assertSame(0, User::load($recipient) -> lastNotificationId);
            }
        }
    }

    public function testPreviouslySeenOtherNotificationsDoNotKeepTheDotsLit(): void
    {
        $recipient = $this -> userWith(0);
        $sender = self::createUser();
        Notification::create($recipient, $sender, 'like');
        Notification::markSeen($recipient);
        self::createMessage($sender, $recipient);
        Notification::create($recipient, $sender, 'message');

        $this -> assertNavigationAfterSeeing($recipient, false);
    }

    public function testOpeningMessagesWithoutNotificationsClearsTheMessageDots(): void
    {
        $recipient = $this -> userWith(0);
        self::createMessage(self::createUser(), $recipient);

        $this -> assertNavigationAfterSeeing($recipient, false);
    }

    public function testANewNotificationAfterOpeningMessagesRaisesTheCombinedDotAgain(): void
    {
        $recipient = $this -> userWith(0);
        $sender = self::createUser();
        self::createMessage($sender, $recipient);
        Notification::create($recipient, $sender, 'message');
        Message::markSeen($recipient);

        $this -> assertFalse(NavAlertDot::anythingNewFor(User::load($recipient)));

        Notification::create($recipient, $sender, 'like');

        $this -> assertTrue(NavAlertDot::anythingNewFor(User::load($recipient)));
    }
}
