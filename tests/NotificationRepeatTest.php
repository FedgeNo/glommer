<?php

declare(strict_types=1);

/**
 * Liking, reposting and friend-requesting can all be undone and done again.
 * Without a repeat rule each round trip is a fresh notification and a fresh
 * push, so anyone can ring someone's bell as often as they like using nothing
 * but the button. Saying something twice is a different matter and still
 * arrives.
 */
class NotificationRepeatTest extends DatabaseTestCase
{
    private function countFor(int $user_id, string $type): int
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Notifications`
    WHERE `userId` = ? AND `type` = ?
', 'is', $user_id, $type));

        return (int) mysqli_fetch_assoc($result)['count'];
    }

    public function testRelikingTheSamePostDoesNotNotifyAgain(): void
    {
        $author = self::createUser();
        $liker = self::createUser();

        Notification::create($author, $liker, 'like', 1);
        Notification::create($author, $liker, 'like', 1);
        Notification::create($author, $liker, 'like', 1);

        $this -> assertSame(1, $this -> countFor($author, 'like'));
    }

    public function testLikingADifferentPostStillNotifies(): void
    {
        $author = self::createUser();
        $liker = self::createUser();

        Notification::create($author, $liker, 'like', 1);
        Notification::create($author, $liker, 'like', 2);

        $this -> assertSame(2, $this -> countFor($author, 'like'));
    }

    public function testSomebodyElseLikingTheSamePostStillNotifies(): void
    {
        $author = self::createUser();
        $one = self::createUser();
        $two = self::createUser();

        Notification::create($author, $one, 'like', 1);
        Notification::create($author, $two, 'like', 1);

        $this -> assertSame(2, $this -> countFor($author, 'like'));
    }

    /**
     * A friend request names no post, so the null case has to be matched as
     * itself rather than treated as "any post".
     */
    public function testResendingAFriendRequestDoesNotNotifyAgain(): void
    {
        $target = self::createUser();
        $asker = self::createUser();

        Notification::create($target, $asker, 'friendRequest');
        Notification::create($target, $asker, 'friendRequest');

        $this -> assertSame(1, $this -> countFor($target, 'friendRequest'));
    }

    /**
     * The reason the rule names types rather than applying to everything: a
     * second message is a second thing said, and someone in a conversation has
     * to keep hearing about it.
     */
    public function testASecondMessageStillNotifies(): void
    {
        $recipient = self::createUser();
        $sender = self::createUser();

        Notification::create($recipient, $sender, 'message');
        Notification::create($recipient, $sender, 'message');

        $this -> assertSame(2, $this -> countFor($recipient, 'message'));
    }

    public function testASecondReplyToTheSamePostStillNotifies(): void
    {
        $author = self::createUser();
        $replier = self::createUser();

        Notification::create($author, $replier, 'reply', 1);
        Notification::create($author, $replier, 'reply', 1);

        $this -> assertSame(2, $this -> countFor($author, 'reply'));
    }
}
