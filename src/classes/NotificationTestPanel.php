<?php

declare(strict_types=1);

class NotificationTestPanel extends Div
{
    public ?string $class = 'NotificationTestPanel';
    public array $mixins = ['Card', 'd-flex', 'flex-column', 'gap-2', 'align-items-start'];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Paragraph(
            'Send a test notification to yourself (the admin). ' .
            'It should appear instantly as a toast and in the notification dropdown.'
        ));

        $button = new Button();
        $button -> class = 'Button';
        $button -> addContent('Send test notification');
        $this -> addContent($button);

        return parent::toDOM();
    }
}
