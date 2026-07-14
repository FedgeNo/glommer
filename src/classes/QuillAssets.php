<?php

declare(strict_types=1);

class QuillAssets
{
    public static function CSSLink(): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css';

        return $css;
    }

    public static function JSScript(): Script
    {
        $js = new Script();
        $js -> src = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js';

        return $js;
    }
}
