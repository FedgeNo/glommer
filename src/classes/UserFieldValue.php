<?php

declare(strict_types=1);

/**
 * What one of a remote account's labelled fields says.
 *
 * Linkified the way a bio is, through the same Linkifier every other piece of
 * text here goes through - a field holding a URL becomes a link a reader can
 * follow, without this site rendering the markup it arrived wrapped in.
 */
class UserFieldValue extends DescriptionDetails
{
    public ?string $class = 'UserFieldValue';

    public string $value = '';

    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();

        foreach (DeltaRenderer::linkifyPlainText(self::currentDocument(), $this -> value) as $node) {
            $element -> appendChild($node);
        }

        return $element;
    }
}
