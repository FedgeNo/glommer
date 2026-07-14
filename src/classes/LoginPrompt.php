<?php

declare(strict_types=1);

class LoginPrompt extends Paragraph
{
    public ?string $class = 'Muted text-sm LoginPrompt';

    public function __construct(string $action)
    {
        parent::__construct();

        $this -> addContent(new Anchor(ServerURL::absolute('/login'), 'Log in'));
        $this -> contents[] = ' to ' . $action . '.';
    }
}
