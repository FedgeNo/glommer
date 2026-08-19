<?php

declare(strict_types=1);

class RemoteMediaRangeTest extends TestCase
{
    private function normalize(string $range): ?string
    {
        $normalize = new \ReflectionMethod(RemoteMedia::class, 'normalizeRange');
        $normalize -> setAccessible(true);

        $result = $normalize -> invoke(null, $range);

        return is_string($result) ? $result : null;
    }

    /** @param array{status?: int, headers?: array<string, string>} $response */
    private function partial(?string $range, array $response): ?array
    {
        $partial = new \ReflectionMethod(RemoteMedia::class, 'partialResponse');
        $partial -> setAccessible(true);
        $result = $partial -> invoke(null, $range, $response);

        return is_array($result) ? $result : null;
    }

    public function testACompleteResponseCarriesNoPartialHeaders(): void
    {
        $this -> assertNull($this -> partial('bytes=100-', ['status' => 200, 'headers' => []]));
    }

    public function testAValidPartialResponseKeepsItsRange(): void
    {
        $this -> assertSame(
            ['acceptRanges' => 'bytes', 'contentRange' => 'bytes 100-199/1000'],
            $this -> partial('bytes=100-199', [
                'status' => 206,
                'headers' => ['accept-ranges' => 'bytes', 'content-range' => 'bytes 100-199/1000'],
            ])
        );
    }

    public function testMalformedAndMultipleRangesAreIgnored(): void
    {
        $this -> assertNull($this -> normalize('bytes=100-50'));
        $this -> assertNull($this -> normalize('bytes=0-10,20-30'));
        $this -> assertNull($this -> normalize('items=0-10'));
        $this -> assertNull($this -> normalize('bytes=-0'));
    }

    public function testAnInvalidPartialResponseIsRefused(): void
    {
        $this -> assertNull($this -> partial('bytes=100-', [
            'status' => 206,
            'headers' => ['accept-ranges' => 'bytes', 'content-range' => 'bytes 0-99/1000'],
        ]));
        $this -> assertNull($this -> partial('bytes=100-', [
            'status' => 206,
            'headers' => ['content-range' => 'bytes 100-199/1000'],
        ]));
    }
}
