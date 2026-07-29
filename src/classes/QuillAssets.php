<?php

declare(strict_types=1);

/**
 * The Quill rich-text editor, loaded from the CDN (no build step). Pinned to
 * exact bytes with sha384 integrity hashes computed from what jsdelivr serves,
 * so a compromised or MITM'd CDN can't swap different code in under a trusted
 * URL - which matters more here than anywhere else on the site, since this is
 * the script running in the box people type into.
 *
 * The URLs track quill@2 rather than a fixed patch version, so a patch release
 * will break the hash and take the editor down until both are bumped together.
 * That is the intended trade: a loud failure rather than an unverified script in
 * the composer.
 */
class QuillAssets
{
    private const CSS_INTEGRITY = 'sha384-ecIckRi4QlKYya/FQUbBUjS4qp65jF/J87Guw5uzTbO1C1Jfa/6kYmd6dXUF6D7i';
    private const JS_INTEGRITY = 'sha384-utBUCeG4SYaCm4m7GQZYr8Hy8Fpy3V4KGjBZaf4WTKOcwhCYpt/0PfeEe3HNlwx8';

    public static function CSSLink(): Link
    {
        $css = new Link();
        $css -> rel = 'stylesheet';
        $css -> href = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css';
        $css -> attributes['integrity'] = self::CSS_INTEGRITY;
        $css -> attributes['crossorigin'] = 'anonymous';

        return $css;
    }

    public static function JSScript(): Script
    {
        $js = new Script();
        $js -> src = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js';
        $js -> attributes['integrity'] = self::JS_INTEGRITY;
        $js -> attributes['crossorigin'] = 'anonymous';

        return $js;
    }
}
