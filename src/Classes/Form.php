<?php

declare(strict_types=1);

class Form extends HTMLObject
{
    public string $tagName = 'form';
    public ?string $action = null;
    public string $method = 'GET';
    public ?string $enctype = null;

    public function toDOM(): \DOMElement
    {
        if (!is_string($this -> action)) {
            throw new Exception('Form action is required');
        }

        $valid_methods = ['GET', 'POST'];
        if (!in_array(strtoupper($this -> method), $valid_methods)) {
            throw new Exception('Invalid form method: ' . $this -> method);
        }

        $this -> attributes['action'] = $this -> action;
        $this -> attributes['method'] = strtoupper($this -> method);

        if ($this -> enctype !== null) {
            $this -> attributes['enctype'] = $this -> enctype;
        }

        if (strtoupper($this -> method) === 'POST') {
            $csrf_input = new HiddenInput();
            $csrf_input -> name = 'csrfToken';
            $csrf_input -> value = CSRF::token();
            $this -> contents[] = $csrf_input;
        }

        return parent::toDOM();
    }
}
