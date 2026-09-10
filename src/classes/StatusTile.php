<?php

declare(strict_types=1);

/** A status and its details, linked to the relevant admin section. */
class StatusTile extends Anchor
{
    public ?string $class = 'StatusTile';
    public array $view = [];

    public function __construct(array $view)
    {
        parent::__construct($view['href']);
        $this -> view = $view;
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-tile'] = $this -> view['id'];
        $this -> attributes['data-state'] = $this -> view['state'];
        $symbol = new Span();
        $symbol -> addContent($this -> view['symbol']);
        $symbol -> attributes['aria-hidden'] = 'true';
        $this -> addContent($symbol);
        foreach (['label', 'value', 'detail'] as $field) {
            $text = new Span();
            $text -> addContent($this -> view[$field]);
            $text -> attributes['data-field'] = $field;
            $this -> addContent($text);
        }

        return parent::toDOM();
    }
}
