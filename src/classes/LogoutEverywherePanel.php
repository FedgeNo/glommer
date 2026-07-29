<?php

declare(strict_types=1);

class LogoutEverywherePanel extends Div
{
    public ?string $class = 'LogoutEverywherePanel';
    public array $mixins = ['Card', 'd-flex', 'flex-column', 'gap-2', 'align-items-start'];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Paragraph(
            'End every active session and forget every remembered device. ' .
            'You will be signed out of all browsers, including this one.'
        ));

        $button = new Button();
        $button -> addContent('Log out everywhere');
        $button -> class = 'Button LogoutEverywhereButton';
        $this -> addContent($button);

        return parent::toDOM();
    }
}
