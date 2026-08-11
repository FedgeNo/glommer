<?php

declare(strict_types=1);

/**
 * Dutch for reading a received message in the reader's own language. See
 * src/locales/en/MessageTranslation.php for the source and the shape each
 * entry is built to.
 */

return [
    'MessageTranslateButton' => [
        'name' => 'Dit bericht vertalen',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'Over het vertalen van berichten',
        'body' => 'Om een bericht te vertalen, wordt de tekst ervan naar deze server gestuurd en hier vertaald. Er wordt niets bewaard: de vertaling wordt niet in de database geschreven en het bericht zelf blijft ongewijzigd. Maar een bericht dat op deze manier is vertaald, is door de server gelezen, en is dus niet end-to-end versleuteld zoals een onvertaald bericht dat wel is. Alleen berichten die je ontvangt kunnen worden vertaald, en alleen wanneer jij daarom vraagt.',
        'confirm' => 'Vertalen',
        'cancel' => 'Niet nu',
    ],
];
