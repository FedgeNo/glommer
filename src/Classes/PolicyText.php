<?php

declare(strict_types=1);

/**
 * Renders an admin-authored plain-text policy (terms/privacy) as paragraphs:
 * blank-line-separated chunks become <p> elements, everything entity-escaped
 * by the normal text-node handling - the admin writes plain text, not HTML.
 */
class PolicyText extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'PolicyText';

    public function __construct(public readonly string $text)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $chunks = preg_split('/\R\s*\R/', trim($this -> text));

        foreach ($chunks as $chunk) {
            if (trim($chunk) !== '') {
                $this -> contents[] = new Paragraph(trim($chunk));
            }
        }

        return parent::toDOM();
    }
}
