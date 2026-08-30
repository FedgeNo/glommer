<?php

declare(strict_types=1);

class UploadProcessorTest extends TestCase
{
    private function containerMagicMatches(string $prefix, array $formats): bool
    {
        $method = new \ReflectionMethod(UploadProcessor::class, 'containerMagicMatches');
        $method -> setAccessible(true);

        return (bool) $method -> invoke(null, $prefix, $formats);
    }

    public function testATypeWithoutADisplayExtensionIsRefused(): void
    {
        try {
            UploadProcessor::srcPath(1, 'UnknownItem');
        } catch (\InvalidArgumentException $exception) {
            $this -> assertTrue(str_contains($exception -> getMessage(), 'UnknownItem'));

            return;
        }

        $this -> assertTrue(false, 'Expected an InvalidArgumentException');
    }

    public function testAContainerSignatureMustStartTheUpload(): void
    {
        $this -> assertTrue($this -> containerMagicMatches('ID3' . str_repeat("\0", 20), ['mp3']));
        $this -> assertFalse($this -> containerMagicMatches('<html>ID3' . str_repeat("\0", 20), ['mp3']));
    }

    public function testAnOriginalAlwaysUsesAnInertSuffix(): void
    {
        $method = new \ReflectionMethod(UploadProcessor::class, 'outputPaths');
        $method -> setAccessible(true);
        $paths = $method -> invoke(null, 12, 'AudioItem', 'php');

        $this -> assertTrue(str_ends_with((string) $paths['original'], '/12-original.bin'));
        $this -> assertFalse(str_ends_with((string) $paths['original'], '.php'));
    }
}
