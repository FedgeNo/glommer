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

        $this -> addContent(new APITokenRotateForm($token !== null));
        $this -> addContent(new APITokenRevokeForm($token !== null));

        return parent::toDOM();
    }
}
