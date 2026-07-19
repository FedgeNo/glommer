<?php

declare(strict_types=1);

class FediverseHandleTest extends TestCase
{
    public function testParsesASingleHandle(): void
    {
        $this -> assertSame(
            [['user' => 'alice', 'domain' => 'example.com', 'handle' => '@alice@example.com']],
            FediverseHandle::parseAll('@alice@example.com')
        );
    }

    public function testWorksWithoutALeadingAt(): void
    {
        $this -> assertSame(
            [['user' => 'alice', 'domain' => 'example.com', 'handle' => '@alice@example.com']],
            FediverseHandle::parseAll('alice@example.com')
        );
    }

    public function testSplitsOnAnyWhitespace(): void
    {
        $handles = FediverseHandle::parseAll("@alice@example.com\n@bob@example.org\t@carol@example.net   @dave@example.io");

        $this -> assertSame(
            ['@alice@example.com', '@bob@example.org', '@carol@example.net', '@dave@example.io'],
            array_column($handles, 'handle')
        );
    }

    public function testSplitsOnAnyOtherPunctuationBetweenHandles(): void
    {
        $handles = FediverseHandle::parseAll('@alice@example.com, @bob@example.org; @carol@example.net | @dave@example.io');

        $this -> assertSame(
            ['@alice@example.com', '@bob@example.org', '@carol@example.net', '@dave@example.io'],
            array_column($handles, 'handle')
        );
    }

    public function testLowercasesAndDedupes(): void
    {
        $handles = FediverseHandle::parseAll('@Alice@Example.com @alice@example.com');

        $this -> assertCount(1, $handles);
        $this -> assertSame('@alice@example.com', $handles[0]['handle']);
    }

    public function testSkipsADomainWithNoRealTLD(): void
    {
        $this -> assertSame([], FediverseHandle::parseAll('@alice@intranet'));
        $this -> assertSame([], FediverseHandle::parseAll('@alice@example.notarealtld'));
    }

    public function testIgnoresProseWithNoHandleShapedSubstring(): void
    {
        $this -> assertSame([], FediverseHandle::parseAll('just some plain text, no handles here at all'));
    }

    public function testEmptyInputYieldsNoHandles(): void
    {
        $this -> assertSame([], FediverseHandle::parseAll(''));
    }
}
