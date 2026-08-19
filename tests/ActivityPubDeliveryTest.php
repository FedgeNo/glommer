<?php

declare(strict_types=1);

class ActivityPubDeliveryTest extends TestCase
{
    public function testAnInboxWithoutARequestPathIsRefusedBeforeSending(): void
    {
        $this -> assertFalse(ActivityPubDelivery::post(
            'https://remote.invalid',
            ['type' => 'Create'],
            'https://local.invalid/actor#main-key',
            'not-needed'
        ));
    }

    public function testAnActivityThatCannotBeEncodedIsRefusedBeforeSending(): void
    {
        $this -> assertFalse(ActivityPubDelivery::post(
            'https://remote.invalid/inbox',
            ['type' => 'Create', 'actor' => "\xB1\x31"],
            'https://local.invalid/actor#main-key',
            'not-needed'
        ));
    }

    public function testARemoteActorCannotSignADeliveryAsOneOfOurMembers(): void
    {
        $author = new User();
        $author -> remoteActorURI = 'https://remote.invalid/users/someone';

        $this -> assertFalse(ActivityPubDelivery::postAs(
            $author,
            'https://remote.invalid/inbox',
            ['type' => 'Create']
        ));
    }
}
