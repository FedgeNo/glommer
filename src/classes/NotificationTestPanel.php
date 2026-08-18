<?php

declare(strict_types=1);

class NotificationTestPanel extends Div
{
    public ?string $class = 'NotificationTestPanel';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $this -> addContent(new Paragraph((string) ($words['intro'] ?? '')));

        $button = new ButtonButton();
        $button -> addContent((string) ($words['button'] ?? ''));
        $this -> addContent($button);

        return parent::toDOM();
    }
}
