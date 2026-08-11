<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/AccountExtras.php for what this fragment
 * covers.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Sitio',
        'siteURLLabel' => 'URL del sitio',
        'siteTitleLabel' => 'Título del sitio',
        'mailFromAddressLabel' => 'Dirección de correo del remitente',
        'serverNameConfirmedLabel' => 'He configurado "ServerName {host}" y "UseCanonicalName On" en la configuración de mi servidor web (solo se comprueba si la prueba automática en vivo no puede completarse; consulta la sección HTTPS de README.md)',
        'databaseLegend' => 'Base de datos',
        'databaseHostLabel' => 'Servidor de la base de datos',
        'databasePortLabel' => 'Puerto de la base de datos',
        'databaseNameLabel' => 'Nombre de la base de datos',
        'databaseAdminUsernameLabel' => 'Nombre de usuario del administrador de la base de datos',
        'databaseAdminPasswordLabel' => 'Contraseña del administrador de la base de datos',
        'webSocketTLSLegend' => 'TLS de WebSocket (opcional)',
        'certificatePathLabel' => 'Ruta del certificado',
        'certificatePathPlaceholder' => 'Déjalo en blanco para generarlo automáticamente con mkcert',
        'keyPathLabel' => 'Ruta de la clave',
        'keyPathPlaceholder' => 'Déjalo en blanco para generarla automáticamente con mkcert',
        'botProtectionLegend' => 'Protección contra bots (opcional)',
        'turnstileSiteKeyLabel' => 'Clave de sitio de Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Déjalo en blanco para omitirlo',
        'turnstileSecretKeyLabel' => 'Clave secreta de Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Déjalo en blanco para omitirlo',
        'submit' => 'Configurar',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Frase de contraseña actual',
        'newPassphraseLabel' => 'Nueva frase de contraseña',
        'confirmNewPassphraseLabel' => 'Confirma la nueva frase de contraseña',
        'accountPasswordLabel' => 'Contraseña de la cuenta',
        'submit' => 'Cambiar frase de contraseña',
    ],

    'PasswordResetForm' => [
        'legend' => 'Elige una nueva contraseña',
        'newPasswordLabel' => 'Nueva contraseña',
        'newPasswordPlaceholder' => 'Al menos 8 caracteres',
        'confirmPasswordLabel' => 'Confirma la nueva contraseña',
        'submit' => 'Restablecer contraseña',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Restablece tu contraseña',
        'emailLabel' => 'Correo electrónico',
        'submit' => 'Enviar enlace de restablecimiento',
    ],

    'EmailRevertForm' => [
        'submit' => 'Revertir cambio de correo electrónico',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Verificar dirección de correo electrónico',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => 'Volver a activarlos',
    ],

    'EmailDigestSetting' => [
        'label' => 'Enviarme por correo lo que me perdí después de un tiempo sin conectarme',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Dispositivo desconocido',
        'browserOnOS' => '{browser} en {os}',
        'thisDevice' => ' (este dispositivo)',
        'lastUsed' => ['before' => 'Usado por última vez ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'Cierra todas las sesiones activas y olvida todos los dispositivos recordados. Se cerrará tu sesión en todos los navegadores, incluido este.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Cerrar sesión en todas partes',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Verificar con Google para eliminar',
    ],

    'GoogleSignInButton' => [
        'label' => 'Continuar con Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Editar perfil',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Recibe notificaciones en este dispositivo aunque el sitio no esté abierto. Esta opción se aplica por navegador: actívala en cada uno donde quieras recibir avisos.',
        'label' => [
            'off' => 'Activar en este dispositivo',
            'on' => 'Desactivar en este dispositivo',
        ],
        'unsupported' => 'Este navegador no admite las notificaciones push',
    ],
];
