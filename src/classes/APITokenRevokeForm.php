<?php

declare(strict_types=1);

class APITokenRevokeForm extends FormForm
{
    public function __construct(private bool $issued)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = '/revoke-api-token';

        if (!$this -> issued) {
            $this -> attributes['hidden'] = '';
        }

        $words = Strings::for(APITokenSetting::class);
        $this -> addContent(new SubmitButton((string) ($words['revoke'] ?? '')));

        return parent::toDOM();
    }
}
