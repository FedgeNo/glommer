<?php

declare(strict_types=1);

/**
 * Italian for reading a received message in the reader's own language. See
 * src/locales/en/MessageTranslation.php for what this fragment covers.
 */

return [
    'MessageTranslateButton' => [
        'name' => 'Traduci questo messaggio',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'Informazioni sulla traduzione dei messaggi',
        'body' => 'Per tradurre un messaggio, il suo testo viene inviato a questo server e tradotto qui. Non viene conservato nulla: la traduzione non viene scritta nel database e il messaggio originale resta invariato. Ma un messaggio tradotto in questo modo è stato letto dal server, quindi non è cifrato end-to-end come lo è un messaggio non tradotto. Possono essere tradotti solo i messaggi che ricevi, e solo quando lo chiedi tu.',
        'confirm' => 'Traducilo',
        'cancel' => 'Non ora',
    ],
];
