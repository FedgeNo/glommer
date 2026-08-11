<?php

declare(strict_types=1);

class LoginPrompt extends Paragraph
{
    public ?string $class = 'LoginPrompt';
    public array $mixins = ['muted', 'text-sm'];

    public function __construct(string $action)
    {
        parent::__construct();

        // A slot either side of the link, the empty one included: whether the
        // words come before "log in" or after it is a fact about the language,
        // and a slot that exists on one side only settles that here for every
        // language at once.
        $this -> contents[] = '';
        $this -> addContent(new Anchor(ServerURL::absolute('/login'), 'Log in'));
        $this -> contents[] = ' to ' . $action . '.';
    }
}
