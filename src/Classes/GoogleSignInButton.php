<?php

declare(strict_types=1);

/**
 * The "Continue with Google" button - an anchor to /auth-google, which starts
 * GoogleAuth's OAuth redirect. Shown only when GoogleAuth::isEnabled(). The
 * Google "G" mark is a CSS data-URI background (CSP-safe - img-src allows
 * data:), so the DOM stays a plain anchor.
 */
class GoogleSignInButton extends HTMLObject
{
    public string $tagName = 'a';
    public ?string $class = 'GoogleSignInButton';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['href'] = ServerURL::absolute('/auth-google');
        $this -> contents[] = 'Continue with Google';

        return parent::toDOM();
    }
}
