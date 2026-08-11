<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/MessagingExtras.php for what this fragment
 * covers.
 */

return [
    'Notification' => [
        'postReady' => 'Tu contenido multimedia ha terminado de procesarse y ya está publicado',
        'scheduledPostLive' => 'Tu publicación programada ya está publicada',
        'uploadPartlyFailed' => 'Tu publicación ya está en línea, pero uno o más de sus archivos no se pudieron procesar',
        'uploadFailed' => 'Uno de tus archivos no se pudo procesar y no se publicó',
        'mailerFailed' => 'Error al enviar el correo: es posible que el servicio de correo no esté funcionando. Comprueba tu configuración de correo.',
        'mailFromNotConfigured' => 'No hay ninguna dirección de remitente configurada, así que no se pueden enviar correos. Configúrala en Ajustes de administración (sección "Correo saliente") o mediante bin/install.php.',
        'systemError' => 'Se produjo un error del servidor. Consulta el registro de errores para más detalles.',
        'passwordRemovedGoogle' => 'Tu contraseña se eliminó al iniciar sesión con Google. Usa "¿Olvidaste tu contraseña?" si quieres establecer una nueva.',
        'like' => 'A {name} le gustó tu publicación',
        'repost' => '{name} republicó tu publicación',
        'reply' => '{name} respondió a tu publicación',
        'friendRequest' => '{name} te envió una solicitud de amistad',
        'friendAccepted' => '{name} aceptó tu solicitud de amistad',
        'message' => '{name} te envió un mensaje',
        'mention' => '{name} te mencionó en una publicación',
        'follow' => '{name} te siguió desde otro servidor',
        'default' => '{name} hizo algo',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Todavía no hay notificaciones.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Notificaciones',
        'unseen' => 'Notificaciones sin ver',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Envíate una notificación de prueba a ti mismo (el administrador). Debería aparecer al instante como aviso emergente y en el menú desplegable de notificaciones.',
        'button' => 'Enviar notificación de prueba',
        'sending' => 'Enviando…',
        'sent' => 'Enviado',
        'failed' => 'Fallido',
    ],

    'MessageDot' => [
        'label' => 'Mensajes sin leer',
    ],

    'NavAlertDot' => [
        'label' => 'Algo nuevo en el menú',
    ],

    'Message' => [
        'encrypted' => 'Mensaje cifrado',
        'decryptionFailed' => 'Este mensaje se cifró con claves que ya no existen.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Mensaje',
        'bodyPlaceholder' => 'Escribe un mensaje',
        'send' => 'Enviar',
    ],

    'MessageList' => [
        'emptyNotice' => 'Todavía no hay mensajes.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Comparte este código en voz alta con la otra persona, por otro medio: en persona o por llamada. Si coincide en ambos lados, no hay nadie interponiéndose entre los dos.',
        'changed' => 'Este código ha cambiado desde que lo comprobaste. Eso ocurre cuando alguno de los dos restablece sus claves de cifrado, pero también es lo que parecería si alguien estuviera leyendo esta conversación. Comprueba el nuevo código con esa persona antes de confiar en él.',
        'verified' => 'Has comprobado este código.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Marcar como verificado',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Frase de contraseña',
        'passphrasePlaceholder' => 'Frase de contraseña para desbloquear esta conversación',
        'submit' => 'Desbloquear',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Último mensaje ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Contenido sensible',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Mostrar contenido sensible de forma predeterminada',
    ],
];
