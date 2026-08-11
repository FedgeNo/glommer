<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/MessageTranslation.php for what this fragment
 * covers.
 */

return [
    'MessageTranslateButton' => [
        'name' => 'Traducir este mensaje',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'Sobre la traducción de mensajes',
        'body' => 'Para traducir un mensaje, su texto se envía a este servidor y se traduce aquí. No se guarda nada: la traducción no se escribe en la base de datos y el mensaje original no cambia. Pero un mensaje traducido de esta manera ha sido leído por el servidor, así que no queda cifrado de extremo a extremo como un mensaje sin traducir. Solo se pueden traducir los mensajes que recibes, y solo cuando tú lo pides.',
        'confirm' => 'Traducirlo',
        'cancel' => 'Ahora no',
    ],
];
