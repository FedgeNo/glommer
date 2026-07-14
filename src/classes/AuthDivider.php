<?php

declare(strict_types=1);

/**
 * A labelled "or" separator between the primary sign-in option (Continue with
 * Google) and the email/password form beneath it. The flanking lines are drawn
 * in CSS (::before / ::after), so the element is just the label text.
 */
class AuthDivider extends HTMLObject
{
    public ?string $class = 'AuthDivider';

    public function __construct(private readonly string $label = 'or')
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = $this -> label;

        return parent::toDOM();
    }
}
