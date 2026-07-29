<?php

declare(strict_types=1);

/**
 * Minimal server‑side placeholder for the post composer.
 */
class PostComposer extends Composer
{
    public function toDOM(): \DOMElement
    {
        if (Auth::check()) {
            return parent::toDOM();
        }
        $link = new Anchor(ServerURL::absolute('/login'), 'Log in');
        $paragraph = new Heading2;
        $paragraph -> addContent($link);
        $paragraph -> addContent(' to post.');
        $this -> addContent($paragraph);
        return parent::toDOM();
    }
}
