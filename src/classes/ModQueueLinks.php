<?php

declare(strict_types=1);

/**
 * The moderation lists that keep pages of their own, and why.
 *
 * Reports and banned users both grow as you scroll them. A list that loads
 * more of itself while you read has no business sitting inside a settings
 * page between other controls, so those two stay where they are and this
 * points at them.
 */
class ModQueueLinks extends Div
{
    public ?string $class = 'ModQueueLinks';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2', 'align-items-start'];

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new Paragraph('The queues are long enough to read a page at a time, so they have pages of their own.'));

        foreach ([
            '/admin/reports' => 'Reports',
            '/admin/banned' => 'Banned Users',
        ] as $path => $label) {
            $link = new Anchor(ServerURL::absolute($path), $label);
            $link -> class = 'Button';
            $this -> addContent($link);
        }

        return parent::toDOM();
    }
}
