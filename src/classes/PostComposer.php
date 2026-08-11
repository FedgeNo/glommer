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
        $sentence = Strings::for(self::class)['prompt'] ?? [];
        $link = new Anchor(ServerURL::absolute('/login'), (string) ($sentence['link'] ?? ''));
        $paragraph = new Heading2;
        $paragraph -> addContent((string) ($sentence['before'] ?? ''));
        $paragraph -> addContent($link);
        $paragraph -> addContent((string) ($sentence['after'] ?? ''));
        $this -> addContent($paragraph);
        return parent::toDOM();
    }
}
