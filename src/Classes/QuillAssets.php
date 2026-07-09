<?php

declare(strict_types=1);

class QuillAssets
{
    public static function cssLink(): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css';

        return $css;
    }

    public static function jsScript(): Script
    {
        $js = new Script();
        $js -> src = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js';

        return $js;
    }
}
