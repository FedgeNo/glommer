<?php

declare(strict_types=1);

class MessageThrottleTest extends DatabaseTestCase
{
    public function testUnansweredCountIsZeroForAFreshConversation(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        $this -> assertSame(0, Message::unansweredCount($a, $b));
    }

    public function testUnansweredCountTracksOneSidedMessages(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        self::createMessage($a, $b);
        self::createMessage($a, $b);
        self::createMessage($a, $b);

        $this -> assertSame(3, Message::unansweredCount($a, $b));
        $this -> assertSame(0, Message::unansweredCount($b, $a));
    }

    public function testAReplyResetsTheSenderCountToZero(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        self::createMessage($a, $b);
        self::createMessage($a, $b);
        self::createMessage($b, $a);

        $this -> assertSame(0, Message::unansweredCount($a, $b));
    }

    public function testOnlyMessagesAfterTheLatestReplyCount(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        self::createMessage($a, $b);
        self::createMessage($b, $a);
        self::createMessage($a, $b);
        self::createMessage($a, $b);

        $this -> assertSame(2, Message::unansweredCount($a, $b));
    }

    public function testCountReachesTheConfiguredThrottleMax(): void
    {
        $a = self::createUser();
        $b = self::createUser();

        for ($i = 0; $i < Message::MAX_UNANSWERED; $i++) {
            self::createMessage($a, $b);
        }

        $this -> assertSame(Message::MAX_UNANSWERED, Message::unansweredCount($a, $b));
    }
}
