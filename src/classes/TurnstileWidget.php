<?php

declare(strict_types=1);

/**
 * The Cloudflare Turnstile widget - the "I am not a robot" box - plus the
 * Cloudflare script that renders it. Drop it into a form; on submit the browser
 * includes a cf-turnstile-response token the endpoint verifies via Turnstile.
 * Only added to a form when Turnstile::isEnabled().
 */
class TurnstileWidget extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'TurnstileWidget';

    public function toDOM(): \DOMElement
    {
        // 'cf-turnstile' and 'data-sitekey' are Cloudflare's required hooks - its
        // api.js finds the widget by that class - not our own class naming.
        $widget = new Div();
        $widget -> class = 'cf-turnstile';
        $widget -> attributes['data-sitekey'] = Turnstile::siteKey();
        $this -> contents[] = $widget;

        $script = new Script();
        $script -> src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        $script -> attributes['async'] = 'async';
        $script -> attributes['defer'] = 'defer';
        $this -> contents[] = $script;

        return parent::toDOM();
    }
}
