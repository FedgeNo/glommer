<?php

declare(strict_types=1);

/**
 * What a notification calls the person who caused it.
 *
 * A display name is neither unique nor the account's own: two servers can each
 * have a Chris, and anyone can set their name to somebody else's. "Chris
 * boosted your post" leaves the reader unable to tell which Chris, or whether
 * it was even somebody from here - which is exactly the question a
 * notification about a stranger has to answer.
 */
class NotificationNameTest extends TestCase
{
    public function testAnAccountFromAnotherServerIsNamedByItsHandle(): void
    {
        $this -> assertSame(
            '@fedgeno@mastodon.social',
            Notification::nameFor('fedgeno', 'fedgeno@mastodon.social')
        );
    }

    /** Even when the display name is missing, which is common enough. */
    public function testARemoteAccountWithNoDisplayNameStillGetsItsHandle(): void
    {
        $this -> assertSame('@someone@example.social', Notification::nameFor(null, 'someone@example.social'));
        $this -> assertSame('@someone@example.social', Notification::nameFor('', 'someone@example.social'));
    }

    /**
     * A member here keeps their display name. The handle would say nothing
     * extra - there is only one server they could be from.
     */
    public function testAMemberHereIsNamedTheWayTheyAlwaysWere(): void
    {
        $this -> assertSame('Fedge', Notification::nameFor('Fedge', 'fedge'));
        $this -> assertSame('fedge', Notification::nameFor(null, 'fedge'));
        $this -> assertSame('fedge', Notification::nameFor('', 'fedge'));
    }

    /** The wording is the same either way; only the name in it changes. */
    public function testTheHandleIsWhatTheSentenceIsBuiltFrom(): void
    {
        $name = Notification::nameFor('fedgeno', 'fedgeno@mastodon.social');

        $this -> assertSame('@fedgeno@mastodon.social liked your post', Notification::textFor('like', $name));
        $this -> assertSame('@fedgeno@mastodon.social reposted your post', Notification::textFor('repost', $name));
        $this -> assertSame(
            '@fedgeno@mastodon.social followed you from another server',
            Notification::textFor('follow', $name)
        );
    }
}
