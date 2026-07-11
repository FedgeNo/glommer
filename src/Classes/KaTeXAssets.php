<?php

declare(strict_types=1);

class KaTeXAssets
{
    private const VERSION = '0.16.11';

    public static function CSSLink(): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = 'https://cdn.jsdelivr.net/npm/katex@' . self::VERSION . '/dist/katex.min.css';

        return $css;
    }

    public static function JSScript(): Script
    {
        $js = new Script();
        $js -> src = 'https://cdn.jsdelivr.net/npm/katex@' . self::VERSION . '/dist/katex.min.js';

        return $js;
    }

    /**
     * The auto-render extension that scans an element for delimited LaTeX
     * source and replaces it with rendered math. Must load after JSScript()
     * (it references the global KaTeX object at call time, not load time, but
     * keeping load order consistent avoids any ambiguity).
     */
    public static function autoRenderScript(): Script
    {
        $js = new Script();
        $js -> src = 'https://cdn.jsdelivr.net/npm/katex@' . self::VERSION . '/dist/contrib/auto-render.min.js';

        return $js;
    }
}
