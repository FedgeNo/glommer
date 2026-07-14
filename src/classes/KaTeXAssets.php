<?php

declare(strict_types=1);

class KaTeXAssets
{
    private const VERSION = '0.16.11';

    // Pinned to this exact version's bytes (sha384, computed directly from
    // the files jsdelivr serves for @0.16.11) - the browser refuses to
    // apply/execute the file if what the CDN actually sends doesn't hash to
    // this, so a compromised or MITM'd CDN can't swap in different content
    // under the same trusted URL. Bump alongside VERSION if it's ever
    // upgraded - a version bump with a stale hash would just break KaTeX
    // entirely (the browser blocks the mismatched file), not silently
    // reintroduce the gap.
    private const CSS_INTEGRITY = 'sha384-nB0miv6/jRmo5UMMR1wu3Gz6NLsoTkbqJghGIsx//Rlm+ZU03BU6SQNC66uf4l5+';
    private const JS_INTEGRITY = 'sha384-7zkQWkzuo3B5mTepMUcHkMB5jZaolc2xDwL6VFqjFALcbeS9Ggm/Yr2r3Dy4lfFg';
    private const AUTO_RENDER_INTEGRITY = 'sha384-43gviWU0YVjaDtb/GhzOouOXtZMP/7XUzwPTstBeZFe/+rCMvRwr4yROQP43s0Xk';

    public static function CSSLink(): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = 'https://cdn.jsdelivr.net/npm/katex@' . self::VERSION . '/dist/katex.min.css';
        $css -> attributes['integrity'] = self::CSS_INTEGRITY;
        $css -> attributes['crossorigin'] = 'anonymous';

        return $css;
    }

    public static function JSScript(): Script
    {
        $js = new Script();
        $js -> src = 'https://cdn.jsdelivr.net/npm/katex@' . self::VERSION . '/dist/katex.min.js';
        $js -> attributes['integrity'] = self::JS_INTEGRITY;
        $js -> attributes['crossorigin'] = 'anonymous';

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
        $js -> attributes['integrity'] = self::AUTO_RENDER_INTEGRITY;
        $js -> attributes['crossorigin'] = 'anonymous';

        return $js;
    }
}
