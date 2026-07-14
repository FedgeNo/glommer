<?php

declare(strict_types=1);

class SubmitButton extends Button
{
    public function __construct(string $label)
    {
        parent::__construct();

        $this -> type = 'submit';
        $this -> class = 'Btn align-self-end';
        $this -> contents[] = $label;
    }
}
