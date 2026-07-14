<?php

declare(strict_types=1);

/**
 * Log out as a POST form, not a plain link - logout changes state, so it must
 * be CSRF-protected (init.php verifies the token on every POST). A bare
 * GET link would let a third-party page force-log-out a victim. Styled to sit
 * inline among the account dropdown's other links (see LogoutButton in CSS).
 */
class LogoutForm extends Form
{
    public ?string $class = 'LogoutForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/logout');
        $this -> method = 'POST';

        $button = new Button();
        $button -> type = 'submit';
        $button -> class = 'LogoutButton';
        $button -> contents[] = 'Log out';
        $this -> contents[] = $button;

        return parent::toDOM();
    }
}
