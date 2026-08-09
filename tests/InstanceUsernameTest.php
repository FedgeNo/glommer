<?php

declare(strict_types=1);

/**
 * The name the server speaks under, and why nobody else may have it.
 *
 * WebFinger answers for the instance actor before it looks for a member, so a
 * member holding this name is unreachable from the rest of the network: their
 * handle resolves to the server, and every follow and mention aimed at them
 * lands on an actor with no profile. Nothing about that is visible from here -
 * the account works perfectly on this site.
 */
class InstanceUsernameTest extends TestCase
{
    /** Not the software's name, and not a hardcoded anything: the site's own title. */
    public function testTheHandleComesFromTheSiteTitle(): void
    {
        // The title is a real setting read from the real installation, which
        // an unprivileged run cannot reach.
        $this -> requireInstallation();

        $this -> assertSame(
            (string) preg_replace('/[^a-z0-9_]/', '', strtolower((string) Config::get('siteTitle'))),
            ActivityPubActor::instanceUsername()
        );
    }

    public function testTheInstanceNameIsRecognisedWhateverTheCasing(): void
    {
        $instance = ActivityPubActor::instanceUsername();

        $this -> assertTrue(ActivityPubActor::isInstanceUsername($instance));
        $this -> assertTrue(ActivityPubActor::isInstanceUsername(strtoupper($instance)));
        $this -> assertTrue(ActivityPubActor::isInstanceUsername(' ' . $instance . ' '));
    }

    public function testAnOrdinaryNameIsNotTheInstance(): void
    {
        $this -> assertFalse(ActivityPubActor::isInstanceUsername('someone'));
        $this -> assertFalse(ActivityPubActor::isInstanceUsername(''));
        $this -> assertFalse(ActivityPubActor::isInstanceUsername(ActivityPubActor::instanceUsername() . 'x'));
    }
}
