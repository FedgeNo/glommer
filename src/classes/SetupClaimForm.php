<?php

declare(strict_types=1);

class SetupClaimForm extends FormForm
{
    public function toDOM(): \DOMElement
    {
        $code = new InputField('setupCode', 'Setup code', 'password', 'Setup code', 64);
        $code -> autocomplete = 'off';
        $this -> contents[] = $code;
        $this -> contents[] = new SubmitButton('Authorize this browser');
        return parent::toDOM();
    }
}
