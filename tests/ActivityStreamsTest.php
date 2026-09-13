<?php

declare(strict_types=1);

class ActivityStreamsTest extends TestCase
{
    public function testReferencesDistinguishLinksFromObjects(): void
    {
        $uri = 'https://remote.test/notes/1';
        $this -> assertSame($uri, ActivityStreams::reference($uri));
        $this -> assertSame($uri, ActivityStreams::reference([['href' => $uri]]));
        $this -> assertNull(ActivityStreams::reference([$uri, 'https://remote.test/notes/2']));
        $this -> assertSame($uri, ActivityStreams::reference(['id' => $uri, 'type' => 'Note']));
        $this -> assertSame($uri, ActivityStreams::reference(['type' => ['Link'], 'id' => 'https://remote.test/link', 'href' => $uri]));
        $this -> assertNull(ActivityStreams::reference(['type' => 'Link', 'id' => $uri]));
        $this -> assertNull(ActivityStreams::reference(['href' => 42, 'id' => $uri]));
    }

    public function testAudiencesAcceptSingleObjectsAndMixedReferenceLists(): void
    {
        $this -> assertSame(['https://a.test/u'], ActivityStreams::references(['id' => 'https://a.test/u']));
        $this -> assertSame(['https://a.test/u', 'https://b.test/u'], ActivityStreams::references([
            'https://a.test/u', ['type' => 'Link', 'href' => 'https://b.test/u'], ['id' => 'https://a.test/u'], null,
        ]));
    }

    public function testTypesAcceptArraysFullIRIsAndUnknownExtensions(): void
    {
        foreach (['Create', ['Create'], ActivityStreams::CONTEXT . '#Create', ['https://extension.test/Example', ActivityStreams::CONTEXT . '#Create', 'Create']] as $type) {
            $this -> assertSame('Create', ActivityStreams::type($type, ['Create', 'Delete']));
        }

        $this -> assertNull(ActivityStreams::type(['Create', 'Delete'], ['Create', 'Delete']));
        $this -> assertNull(ActivityStreams::type('https://extension.test/Create', ['Create']));
        $this -> assertNull(ActivityStreams::type(['bad' => 'Create'], ['Create']));
    }

    public function testAttributionCannotHideAnotherAuthorInsideALinkOrArray(): void
    {
        $actor = 'https://remote.test/author';
        $this -> assertTrue(ActivityStreams::attributedTo([], $actor));
        $this -> assertTrue(ActivityStreams::attributedTo(['attributedTo' => ['type' => 'Link', 'href' => $actor]], $actor));
        $this -> assertTrue(ActivityStreams::attributedTo(['attributedTo' => [$actor, ['id' => $actor]]], $actor));

        foreach ([null, [], ['type' => 'Link', 'id' => $actor], [$actor, 'https://remote.test/other'], [$actor, false]] as $attribution) {
            $this -> assertFalse(ActivityStreams::attributedTo(['attributedTo' => $attribution], $actor));
        }
    }

    public function testBothActivityStreamsMediaTypesAreRecognized(): void
    {
        foreach (['application/activity+json', 'Application/Activity+JSON; charset=utf-8', 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', 'application/ld+json; charset=utf-8; PROFILE="https://other.test/profile https://www.w3.org/ns/activitystreams"'] as $type) {
            $this -> assertTrue(ActivityStreams::isMediaType($type), $type);
        }
    }

    public function testOtherMediaTypesAndProfilesAreRejected(): void
    {
        foreach ([null, '', 'text/html', 'application/json', 'application/ld+json', 'application/ld+json; profile="https://other.test/"', 'application/ld+json; profile="https://www.w3.org/ns/activitystreams-extra"'] as $type) {
            $this -> assertFalse(ActivityStreams::isMediaType($type));
        }
    }

    public function testLinkedAudiencesPreserveDirectMessageRouting(): void
    {
        $this -> assertFalse(ActivityPubMessage::isDirect(['to' => ['type' => 'Link', 'href' => ActivityPubActor::PUBLIC_AUDIENCE]], []));
        $this -> assertTrue(ActivityPubMessage::isDirect([], ['to' => ['type' => 'Link', 'href' => 'https://local.test/users/alice']]));
    }
}
