<?php

declare(strict_types=1);

/** Reading a received message in the reader's own language. */

return [
    'MessageTranslateButton' => [
        // The button shows a glyph, so this is the only thing that says what
        // it does - to a screen reader, and in the tooltip.
        'name' => 'Translate This Message',
    ],

    // Said once, before the first message this member ever translates, and
    // then not again. Translating hands the words to this server: that is a
    // real change in who can see them, and it is not something to discover
    // afterwards.
    'MessageTranslationNotice' => [
        'heading' => 'About Translating Messages',
        'body' => 'To translate a message, its text is sent to this server and translated here. Nothing is kept: the translation is not written to the database and the message itself is unchanged. But a message translated this way has been read by the server, so it is not end-to-end encrypted the way an untranslated message is. Only messages you receive can be translated, and only when you ask.',
        'confirm' => 'Translate It',
        'cancel' => 'Not Now',
    ],
];
