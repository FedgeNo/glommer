<?php

declare(strict_types=1);

class LogoutButtonTest extends TestCase
{
    public function testItLooksLikeANavigationLinkRatherThanAPrimaryButton(): void
    {
        $button = new LogoutButton();
        $element = $button -> toDOM();

        $this -> assertSame('LogoutButton', $element -> getAttribute('class'));
        $this -> assertSame('submit', $element -> getAttribute('type'));
        $this -> assertFalse(in_array('Button', preg_split('/\s+/', $element -> getAttribute('class')) ?: [], true));
    }
}
