<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/MessagingAndStatus.php for what this fragment
 * covers.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => '¿Olvidaste tu frase de contraseña? Al restablecerla se crean claves nuevas bajo una frase nueva, pero los mensajes cifrados con las claves antiguas no los podrá volver a leer nadie, nunca más.',
        'requirements' => 'Al menos 12 caracteres, y que no sea tu contraseña de la cuenta: esa se envía a este servidor, y tu frase de contraseña no debe hacerlo nunca.',
        'passphraseLabel' => 'Frase de contraseña',
        'resetPassphraseLabel' => 'Nueva frase de contraseña',
        'confirmLabel' => 'Confirma la frase de contraseña',
        'accountPasswordLabel' => 'Contraseña de la cuenta',
        'submitLabel' => 'Activar los mensajes cifrados',
        'resetSubmitLabel' => 'Restablecer claves de cifrado',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'Los mensajes cifrados de extremo a extremo se bloquean y desbloquean en tu navegador: este servidor los retransmite y almacena sin poder leerlos. Tu clave está protegida por una frase de contraseña, y esa misma frase desbloquea tus mensajes desde cualquier navegador. Las conversaciones se cifran en cuanto ambas personas lo han activado; los mensajes a personas de otros servidores permanecen sin cifrar, porque la federación no tiene otra forma de transportarlos.',
        'noRecovery' => 'No hay forma de recuperar una frase de contraseña perdida, ni siquiera para el administrador. Perderla significa perder tus mensajes cifrados.',
        'enabledStatus' => 'Los mensajes cifrados están activados.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Cifrado',
            'explanation' => 'Los mensajes de esta conversación están cifrados de extremo a extremo: se desbloquean con tu frase de contraseña y se leen en el navegador de cada persona, y lo que este servidor almacena es texto cifrado. Comprueba el código de seguridad al final del hilo con la otra persona para asegurarte de que no hay nadie interponiéndose entre los dos. Los mensajes enviados antes de activar el cifrado siguen siendo legibles como antes.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 No cifrado',
            'explanation' => 'Los mensajes aquí se cifrarán de extremo a extremo en cuanto {handle} active los mensajes cifrados en sus ajustes.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 No cifrado',
            'explanation' => 'Los mensajes aquí no están cifrados de extremo a extremo. Activa los mensajes cifrados en Ajustes para proteger esta conversación.',
        ],
        'federated' => [
            'label' => '🔓 No cifrado',
            'explanation' => '{handle} está en otro servidor. Los mensajes de esta conversación se almacenan tanto en ese servidor como en este, y su administrador puede leerlos: el protocolo entre servidores no tiene forma de cifrarlos. Reserva lo sensible para conversaciones dentro de este sitio.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Seguir cuentas del Fediverso',
        'notice' => 'Pega uno o más identificadores, por ejemplo @user@example.social. Puedes separarlos con cualquier símbolo.',
        'handlesLabel' => 'Identificadores del Fediverso a seguir',
        'submit' => 'Seguir',
        'statusPending' => 'pendiente',
        'statusAccepted' => 'aceptado',
    ],

    'ServerBlockForm' => [
        'legend' => 'Bloquear un servidor',
        'description' => 'Rechaza todo lo que provenga de ese servidor y de cualquiera bajo su dominio: no entra ni sale ninguna entrega, y se eliminan los seguimientos existentes en ambas direcciones.',
        'serverLabel' => 'Servidor',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Motivo',
        'reasonPlaceholder' => 'Por qué se bloquea este servidor',
        'submit' => 'Bloquear servidor',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Ejecuta las partes de la configuración de llamada que se pueden comprobar desde un solo navegador. Todo lo previo a la conexión punto a punto real se puede probar aquí; conectar con otra persona requiere que esa persona esté presente.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Ejecutar la comprobación',
    ],

    'WebSocketStatus' => [
        'ok' => 'Servidor WebSocket: en ejecución',
        'failed' => 'Servidor WebSocket: {detail}',
        'clientTesting' => 'Conexión del navegador: probando…',
        'clientConnecting' => 'Conexión del navegador: conectando…',
        'clientConnected' => 'Conexión del navegador: conectada',
        'clientDisconnecting' => 'Conexión del navegador: desconectando…',
        'clientNotConnected' => 'Conexión del navegador: no conectada',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Servicio de subidas: en ejecución',
        'stopped' => 'Servicio de subidas: detenido; los archivos en espera no se transcodificarán hasta que se reinicie',
        'unknown' => 'Servicio de subidas: desconocido; puede que systemctl no esté disponible en este host, o que SELinux esté denegando la consulta de estado del propio servidor web (ejecuta bin/install.php como root para solucionarlo)',
        'queue' => 'Cola: {staging} en espera, {pending} pendientes, {processing} en proceso',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Temporizador de tendencias: en ejecución',
        'stopped' => 'Temporizador de tendencias: detenido; los temas de tendencia solo se actualizarán mediante la autorreparación en la ruta de lectura (Trending::current()), no según un horario. Ejecuta bin/install.php como root para configurarlo.',
        'unknown' => 'Temporizador de tendencias: desconocido; puede que systemctl no esté disponible en este host, o que SELinux esté denegando la consulta de estado del propio servidor web (ejecuta bin/install.php como root para solucionarlo)',
    ],
];
