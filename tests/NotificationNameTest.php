<?php

declare(strict_types=1);

/**
 * What a notification calls the person who caused it.
 *
 * A display name is neither unique nor the account's own: two servers can each
 * have a Chris, and anyone can set their name to somebody else's. So the
 * username always travels with it - that is the part nobody else can take -
 * and it is written with its @ wherever it is shown, the same as on a profile.
 */
class NotificationNameTest extends TestCase
{
    public function testTheUsernameTravelsWithTheName(): void
    {
        $this -> assertSame('Fedge (@fedge)', Notification::nameFor('Fedge', 'fedge'));
    }

    /** An account from elsewhere carries its host in the same breath. */
    public function testAnAccountFromAnotherServerShowsTheWholeHandle(): void
    {
        $this -> assertSame(
            'fedgeno (@fedgeno@mastodon.social)',
            Notification::nameFor('fedgeno', 'fedgeno@mastodon.social')
        );
    }

    /** With no display name there is nothing to pair the username with. */
    public function testWithNoDisplayNameTheUsernameStandsAlone(): void
    {
        $this -> assertSame('@fedge', Notification::nameFor(null, 'fedge'));
        $this -> assertSame('@fedge', Notification::nameFor('', 'fedge'));
        $this -> assertSame('@someone@example.social', Notification::nameFor(null, 'someone@example.social'));
    }

    /** And a name that is only the username again is not said twice. */
    public function testANameThatRepeatsTheUsernameIsNotSaidTwice(): void
    {
        $this -> assertSame('@fedge', Notification::nameFor('fedge', 'fedge'));
    }

    /** The wording is the same either way; only the name in it changes. */
    public function testTheNameIsWhatTheSentenceIsBuiltFrom(): void
    {
        $name = Notification::nameFor('fedgeno', 'fedgeno@mastodon.social');

        $this -> assertSame(
            'fedgeno (@fedgeno@mastodon.social) liked your post',
            Notification::textFor('like', $name)
        );
        $this -> assertSame(
            'fedgeno (@fedgeno@mastodon.social) reposted your post',
            Notification::textFor('repost', $name)
        );
        $this -> assertSame(
            'fedgeno (@fedgeno@mastodon.social) followed you from another server',
            Notification::textFor('follow', $name)
        );
    }
}
