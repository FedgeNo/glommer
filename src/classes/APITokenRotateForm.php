<?php

declare(strict_types=1);

class APITokenRotateForm extends FormForm
{
    public function __construct(private bool $issued)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = '/rotate-api-token';
        $words = Strings::for(APITokenSetting::class);
        $this -> addContent(new SubmitButton((string) ($words[$this -> issued ? 'rotate' : 'issue'] ?? '')));

        return parent::toDOM();
    }
}
