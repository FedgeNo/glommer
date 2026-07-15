<?php

declare(strict_types=1);

/**
 * The check-your-inbox notice shown to unverified accounts: the instruction
 * line plus its actions (resend the email, or log out).
 */
class VerificationNotice extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'd-flex flex-column gap-3 VerificationNotice';

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new Paragraph('Please check your inbox and click the verification link we sent to confirm your email address. If you don\'t see it, check your junk/spam folder.');

        $actions = new Div();
        $actions -> class = 'd-flex gap-2';

        $resend_button = new Button();
        $resend_button -> type = 'button';
        $resend_button -> class = 'Btn ResendVerificationButton';
        $resend_button -> contents[] = 'Resend verification email';
        $actions -> addContent($resend_button);

        $actions -> addContent(new Anchor(ServerURL::absolute('/logout'), 'Log out'));

        $this -> contents[] = $actions;

        return parent::toDOM();
    }
}
