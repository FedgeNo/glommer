<?php

declare(strict_types=1);

class QuillAssetsTest extends TestCase
{
    public function testAssetURLsAndIntegrityPinTheSameExactRelease(): void
    {
        $css = QuillAssets::CSSLink();
        $js = QuillAssets::JSScript();

        $this -> assertSame('https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css', $css -> href);
        $this -> assertSame('https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js', $js -> src);
        $this -> assertTrue(str_starts_with((string) $css -> attributes['integrity'], 'sha384-'));
        $this -> assertTrue(str_starts_with((string) $js -> attributes['integrity'], 'sha384-'));
        $this -> assertSame('anonymous', $css -> attributes['crossorigin']);
        $this -> assertSame('anonymous', $js -> attributes['crossorigin']);
    }
}
