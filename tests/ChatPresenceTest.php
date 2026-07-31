<?php

declare(strict_types=1);

/**
 * Whether two people count as reading the same thread at the same time - the
 * one condition a call is offered under, so every way of getting it wrong
 * matters: reading a different thread, having wandered off, or the pair only
 * agreeing in one direction.
 */
class ChatPresenceTest extends DatabaseTestCase
{
    /** Backdates a heartbeat, to stand in for time passing. */
    private function ageHeartbeat(int $user_id, int $seconds): void
    {
        DB::run('
UPDATE `Users`
    SET `chatLastSeen` = NOW() - INTERVAL ? SECOND
    WHERE `userId` = ?
', 'ii', $seconds, $user_id);
    }

    public function testSomeoneReadingYourThreadIsPresent(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();

        ChatPresence::enter($reader, $viewer);

        $this -> assertTrue(ChatPresence::isPresentWith($reader, $viewer));
    }

    public function testSomeoneWhoHasOpenedNothingIsNotPresent(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();

        $this -> assertFalse(ChatPresence::isPresentWith($reader, $viewer));
    }

    public function testSomeoneReadingADifferentThreadIsNotPresent(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();
        $someone_else = self::createUser();

        ChatPresence::enter($reader, $someone_else);

        // Online, and in a conversation - just not this one.
        $this -> assertFalse(ChatPresence::isPresentWith($reader, $viewer));
    }

    public function testAStaleHeartbeatIsNotPresent(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();

        ChatPresence::enter($reader, $viewer);
        $this -> ageHeartbeat($reader, ChatPresence::PRESENCE_SECONDS + 5);

        // Nobody sends anything when a laptop closes, so the window is the only
        // thing that can decide they have gone.
        $this -> assertFalse(ChatPresence::isPresentWith($reader, $viewer));
    }

    public function testAHeartbeatInsideTheWindowIsStillPresent(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();

        ChatPresence::enter($reader, $viewer);
        $this -> ageHeartbeat($reader, ChatPresence::PRESENCE_SECONDS - 5);

        // One missed beat must not read as leaving, or a call would be withdrawn
        // mid-conversation over a single slow request.
        $this -> assertTrue(ChatPresence::isPresentWith($reader, $viewer));
    }

    public function testLeavingClearsPresenceImmediately(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();

        ChatPresence::enter($reader, $viewer);
        ChatPresence::leave($reader);

        $this -> assertFalse(ChatPresence::isPresentWith($reader, $viewer));
    }

    public function testMovingToAnotherThreadLeavesTheFirst(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();
        $someone_else = self::createUser();

        ChatPresence::enter($reader, $viewer);
        ChatPresence::enter($reader, $someone_else);

        $this -> assertFalse(ChatPresence::isPresentWith($reader, $viewer), 'they should no longer count as in the first thread');
        $this -> assertTrue(ChatPresence::isPresentWith($reader, $someone_else));
    }

    public function testPresenceIsDirectional(): void
    {
        $reader = self::createUser();
        $viewer = self::createUser();

        ChatPresence::enter($reader, $viewer);

        // The reader has the viewer's thread open; the viewer has opened nothing.
        // A call needs both, so this half alone must not read as mutual.
        $this -> assertTrue(ChatPresence::isPresentWith($reader, $viewer));
        $this -> assertFalse(ChatPresence::isPresentWith($viewer, $reader));
    }
}
