<?php

declare(strict_types=1);

/**
 * A nonce'd inline script that publishes server-side values as window
 * globals for the client-side JS to read (window.currentUserId,
 * window.conversationUsers, ...). Values are encoded via
 * Page::safeJsonForScript(), which keeps them safe to embed in a raw-text
 * <script> element.
 */
class JSGlobals extends Script
{
    /** @var array<string, mixed> global name (without the window. prefix) => value */
    public array $globals;

    public function __construct(array $globals)
    {
        parent::__construct();

        $this -> globals = $globals;
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['nonce'] = SecurityHeaders::nonce();

        $statements = '';

        foreach ($this -> globals as $name => $value) {
            $statements .= 'window.' . $name . ' = ' . Page::safeJsonForScript($value) . ';';
        }

        $this -> contents[] = $statements;

        return parent::toDOM();
    }
}
