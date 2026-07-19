<?php

declare(strict_types=1);

class URLTest extends TestCase
{
    public function testRejectsNonHTTPScheme(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('ftp://example.com/file'));
    }

    public function testRejectsMalformedURL(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('not a url at all'));
    }

    public function testAcceptsHTTPSPublicHostname(): void
    {
        // A real-TLD hostname with no A record (dns_get_record returns empty)
        // is left to pass - can't rely on real DNS in a test, so this exercises
        // the "valid TLD, unresolvable, don't block" branch deliberately. The
        // TLD (.com) must be a real one now; a random label under it won't
        // resolve.
        $this -> assertTrue(URL::isPublicHTTP('https://glommer-nonexistent-test-a9f3c1.com/path'));
    }

    public function testRejectsSingleLabelHost(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://intranet/admin'));
    }

    public function testRejectsFakeTLD(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('https://example.notarealtld/path'));
    }

    public function testRejectsLocalhostHostname(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://localhost/admin'));
    }

    public function testRejectsDotLocalhostSuffix(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://foo.localhost/admin'));
    }

    public function testRejectsDotLocalSuffix(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://printer.local/admin'));
    }

    public function testRejectsLiteralLoopbackIP(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://127.0.0.1/admin'));
    }

    public function testRejectsLiteralPrivateRangeIP(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://10.0.0.5/admin'));
        $this -> assertFalse(URL::isPublicHTTP('http://192.168.1.1/admin'));
    }

    public function testRejectsBracketedIPv6Loopback(): void
    {
        $this -> assertFalse(URL::isPublicHTTP('http://[::1]/admin'));
    }

    public function testRejectsLiteralPublicIP(): void
    {
        // No IP links at all - only a real registrable hostname is postable,
        // even a public IP is refused.
        $this -> assertFalse(URL::isPublicHTTP('http://8.8.8.8/'));
    }
}
