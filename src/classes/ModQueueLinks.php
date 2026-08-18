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

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $this -> addContent(new Paragraph((string) ($words['intro'] ?? '')));

        foreach ([
            '/admin/reports' => (string) ($words['reportsLabel'] ?? ''),
            '/admin/banned' => (string) ($words['bannedUsersLabel'] ?? ''),
        ] as $path => $label) {
            $link = new Anchor(ServerURL::absolute($path), $label);
            $link -> class = 'Button';
            $this -> addContent($link);
        }

        return parent::toDOM();
    }
}
