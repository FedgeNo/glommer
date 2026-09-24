<?php

declare(strict_types=1);

class APITokenSetting extends Div
{
    public ?string $class = 'APITokenSetting';

    public function toDOM(): \DOMElement
    {
        $token = APIToken::current((int) Auth::user() -> userId);

        if ($token !== null) {
            $value = new APITokenValue();
            $value -> addContent($token);
            $this -> addContent($value);
        }

        $form = new APITokenRotateForm($token !== null);
        $this -> addContent($form);

        return parent::toDOM();
    }
}
