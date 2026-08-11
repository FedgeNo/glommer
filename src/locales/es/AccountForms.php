<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/AccountForms.php for what this fragment
 * covers.
 */

return [
    'LoginForm' => [
        'legend' => 'Iniciar sesión',
        'identifier' => 'Nombre de usuario o correo electrónico',
        'password' => 'Contraseña',
        'rememberMe' => 'Recuérdame',
        'submit' => 'Iniciar sesión',
    ],

    'SignupForm' => [
        'legend' => 'Crear una cuenta',
        'usernameLabel' => 'Nombre de usuario',
        'usernamePlaceholder' => 'Letras minúsculas, números y _',
        'emailLabel' => 'Correo electrónico',
        'emailPlaceholder' => 'Una dirección de correo electrónico válida',
        'displayName' => 'Nombre para mostrar (opcional)',
        'bioLabel' => 'Biografía (opcional)',
        'bioPlaceholder' => 'Una biografía breve: los #hashtags, @menciones y enlaces se vuelven clicables',
        'passwordLabel' => 'Contraseña',
        'passwordPlaceholder' => 'Contraseña: al menos 8 caracteres',
        'rememberMe' => 'Recuérdame',
        'submit' => 'Registrarse',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Cambia tu contraseña',
        'currentPassword' => 'Contraseña actual',
        'newPasswordLabel' => 'Nueva contraseña',
        'newPasswordPlaceholder' => 'Al menos 8 caracteres',
        'confirmPassword' => 'Confirma la nueva contraseña',
        'submit' => 'Cambiar contraseña',
    ],

    'EmailChangeForm' => [
        'legend' => 'Cambia tu dirección de correo electrónico',
        'newEmail' => 'Nueva dirección de correo electrónico',
        'currentPassword' => 'Contraseña actual',
        'notice' => 'Tendrás que verificar la nueva dirección antes de poder seguir usando el sitio.',
        'submit' => 'Cambiar correo electrónico',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Elimina tu cuenta',
        'warning' => 'Esto elimina de forma permanente tu cuenta, tus publicaciones y tus mensajes. No se puede deshacer.',
        'currentPassword' => 'Contraseña actual',
        'submit' => 'Eliminar cuenta',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Mudarse a otro servidor',
        'movedNotice' => 'Esta cuenta se ha mudado a {destination}. Se pidió a tus seguidores que te siguieran allí.',
        'explanation' => 'Se pide a tus seguidores que te sigan en la cuenta nueva. Tus publicaciones se quedan aquí: las direcciones de los objetos pertenecen al servidor que las creó, así que no se pueden llevar consigo.',
        'addressNotice' => 'La cuenta a la que te mudas debe tener esta cuenta listada antes en "también conocido como". Tu dirección aquí es {address}.',
        'movedToLabel' => 'Mudarse a',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'También conocido como',
        'aliasesExplanation' => 'Cuentas en otros lugares que también eres tú. Añadir una aquí permite que esa cuenta se mude a esta: es el permiso, no la mudanza en sí. Una dirección por línea.',
        'aliasesLabel' => 'Tus otras cuentas',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Guardar',
    ],

    'TwoFactorForm' => [
        'legend' => 'Introduce tu código de verificación',
        'explanation' => 'Te hemos enviado un código de verificación por correo. Introdúcelo abajo para terminar de iniciar sesión.',
        'code' => 'Código de verificación',
        'submit' => 'Verificar',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'La autenticación en dos pasos está activada', 'off' => 'La autenticación en dos pasos está desactivada'],
        'explanation' => [
            'on' => 'Cuando inicies sesión, te enviaremos por correo un código de verificación que deberás introducir para terminar de entrar.',
            'off' => 'Añade un segundo paso al iniciar sesión: te enviaremos por correo un código de verificación que deberás introducir, de modo que tu contraseña por sí sola no baste para entrar.',
        ],
        'currentPassword' => 'Contraseña actual',
        'submit' => ['on' => 'Desactivar la autenticación en dos pasos', 'off' => 'Activar la autenticación en dos pasos'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Revisa tu bandeja de entrada y haz clic en el enlace de verificación que te hemos enviado para confirmar tu dirección de correo electrónico. Si no lo ves, revisa tu carpeta de correo no deseado o spam.',
    ],

    'VerificationResendButton' => [
        'label' => 'Reenviar correo de verificación',
    ],
];
