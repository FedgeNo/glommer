<?php

declare(strict_types=1);

/**
 * The small privacy chip at the top right of the message composer: a couple of
 * words and an emoji saying what this conversation is, with the full
 * explanation a click away (Controllers.js's MessageComposer pops it up).
 *
 * The states are the honest ones a thread can be in: end-to-end encrypted,
 * plaintext because one side hasn't set up keys yet (named, so it's clear
 * whose move it is), or federated - stored on a second server with no way to
 * encrypt it, which no amount of setup can change.
 */
class MessagePrivacyButton extends ButtonButton
{
    /**
     * The chip's own glyphs, here rather than in the locale strings: an emoji
     * is not language, and a translator - human or model - has no business
     * receiving one. The same home MessageTranslateButton gives its globe.
     */
    public const LOCKED = '🔒';
    public const UNLOCKED = '🔓';

    public function __construct(private readonly string $state, private readonly string $handle)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        // An unrecognised state still renders something rather than fataling
        // the composer - the same fallback LoginPrompt uses. Resolved to a
        // key first, so the glyph and the words always tell the same story.
        $state = isset($words[$this -> state]) ? $this -> state : (string) array_key_first($words);
        $entry = $words[$state] ?? [];

        $this -> attributes['data-privacy-explanation'] = str_replace(
            '{handle}',
            $this -> handle,
            (string) ($entry['explanation'] ?? '')
        );
        $this -> contents[] = ($state === 'encrypted' ? self::LOCKED : self::UNLOCKED)
            . ' ' . (string) ($entry['label'] ?? '');

        return parent::toDOM();
    }
}
