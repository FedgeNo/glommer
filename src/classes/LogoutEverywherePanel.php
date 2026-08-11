<?php

declare(strict_types=1);

class LogoutEverywherePanel extends Div
{
    public ?string $class = 'LogoutEverywherePanel';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2', 'align-items-start'];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Paragraph((string) (Strings::for(self::class)['explanation'] ?? '')));

        $this -> addContent(new LogoutEverywhereButton());

        return parent::toDOM();
    }
}
