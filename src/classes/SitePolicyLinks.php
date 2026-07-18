<?php

declare(strict_types=1);

/**
 * The About page's links out to the site's Terms of Service and Privacy
 * Policy - the one reachable place linking to both.
 */
class SitePolicyLinks extends Div
{
    public ?string $class = 'SitePolicyLinks d-flex gap-3';

    public function toDOM(): \DOMElement
    {
        $terms_link = new Anchor(ServerURL::absolute('/terms'), 'Terms of Service');
        $terms_link -> class = 'Btn';
        $this -> contents[] = $terms_link;

        $privacy_link = new Anchor(ServerURL::absolute('/privacy'), 'Privacy Policy');
        $privacy_link -> class = 'Btn';
        $this -> contents[] = $privacy_link;

        return parent::toDOM();
    }
}
