<?php

declare(strict_types=1);

/**
 * Asks the server for one received message in the reader's own language.
 *
 * Only ever on a message somebody else sent: the point is reading what arrived,
 * and nobody needs their own words handed back to them. Nothing is translated
 * until this is pressed - a conversation that quietly went through a translator
 * is one the reader never agreed to send anywhere.
 *
 * Two glyphs, the same pair PostTranslateButton uses, because the way back is a
 * different act from the way there and the button is all the reader has to tell
 * them which one they are looking at.
 */
class MessageTranslateButton extends ToggleButton
{
    public const TRANSLATE = '🌐';
    public const SHOW_ORIGINAL = '↩️';

    public function __construct(private readonly int $messageId)
    {
        parent::__construct();

        $this -> labels = [self::TRANSLATE, self::SHOW_ORIGINAL];
    }

    public function toDOM(): \DOMElement
    {
        $this -> nameIt((string) (Strings::for(self::class)['name'] ?? ''));
        $this -> attributes['data-message-id'] = (string) $this -> messageId;

        return parent::toDOM();
    }
}
