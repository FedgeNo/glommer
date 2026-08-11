<?php

declare(strict_types=1);

/**
 * German for reading a received message in the reader's own language. See
 * src/locales/en/MessageTranslation.php for the source and the shape each
 * entry is built to.
 */

return [
    'MessageTranslateButton' => [
        'name' => 'Diese Nachricht übersetzen',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'Über das Übersetzen von Nachrichten',
        'body' => 'Um eine Nachricht zu übersetzen, wird ihr Text an diesen Server gesendet und hier übersetzt. Es wird nichts gespeichert: Die Übersetzung wird nicht in die Datenbank geschrieben, und die Nachricht selbst bleibt unverändert. Aber eine so übersetzte Nachricht wurde vom Server gelesen und ist deshalb nicht so Ende-zu-Ende-verschlüsselt wie eine unübersetzte Nachricht. Nur Nachrichten, die du erhältst, können übersetzt werden, und nur wenn du danach fragst.',
        'confirm' => 'Übersetzen',
        'cancel' => 'Nicht jetzt',
    ],
];
