<?php

declare(strict_types=1);

/**
 * The same-host predicate is what stops a remote server handing back an
 * actor id belonging to an account on another host together with its own
 * public key - which would take over that account's identity here.
 */
class RemoteActorTest extends TestCase
{
    private function sameHost(string $url, string $other_url): bool
    {
        $method = new \ReflectionMethod(RemoteActor::class, 'sameHost');
        $method -> setAccessible(true);

        return $method -> invoke(null, $url, $other_url);
    }

    public function testAcceptsTheSameHostWithADifferentPath(): void
    {
        $this -> assertTrue($this -> sameHost('https://example.social/users/alice', 'https://example.social/.well-known/x'));
    }

    public function testIsCaseInsensitiveOnTheHost(): void
    {
        $this -> assertTrue($this -> sameHost('https://Example.Social/users/alice', 'https://example.social/users/alice'));
    }

    public function testRejectsADifferentHost(): void
    {
        $this -> assertFalse($this -> sameHost('https://mastodon.social/users/victim', 'https://evil.test/actor'));
    }

    public function testRejectsASubdomainAsADifferentHost(): void
    {
        $this -> assertFalse($this -> sameHost('https://evil.example.social/actor', 'https://example.social/actor'));
    }

    public function testRejectsNonHTTPSchemes(): void
    {
        $this -> assertFalse($this -> sameHost('file:///etc/passwd', 'file:///etc/passwd'));
        $this -> assertFalse($this -> sameHost('javascript:alert(1)', 'javascript:alert(1)'));
    }

    public function testTwoUnparseableValuesAreNeverTreatedAsMatching(): void
    {
        $this -> assertFalse($this -> sameHost('', ''));
        $this -> assertFalse($this -> sameHost('not a url', 'not a url'));
    }
}
