<?php

declare(strict_types=1);

/**
 * Minimal server‑side placeholder for the reply composer.
 */
class ReplyComposer extends Composer
{
    public function __construct(int $parent_id)
    {
        $this -> attributes['data-parent-id'] = (string) $parent_id;
    }

    public function toDOM(): \DOMElement
    {
        if (Auth::check()) {
            return parent::toDOM();
        }
        $link = new Anchor(ServerURL::absolute('/login'), 'Log in');
        $paragraph = new Heading2;
        $paragraph -> addContent('');
        $paragraph -> addContent($link);
        $paragraph -> addContent(' to reply.');
        $this -> addContent($paragraph);
        return parent::toDOM();
    }
}
