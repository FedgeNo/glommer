<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/AdminSettings.php for what this fragment
 * covers.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Correo saliente',
        'fromAddressLabel' => 'Dirección del remitente',
        'fromAddressPlaceholder' => 'No se puede enviar ningún correo hasta que se configure esto',
        'fromNameLabel' => 'Nombre del remitente',
        'hostLabel' => 'Servidor SMTP',
        'portLabel' => 'Puerto SMTP',
        'usernameLabel' => 'Usuario SMTP',
        'passwordLabel' => 'Contraseña SMTP',
        'passwordPlaceholder' => [
            'set' => 'La contraseña está establecida; déjala en blanco para conservarla',
            'unset' => 'Contraseña SMTP',
        ],
        'encryptionLabel' => 'Cifrado',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, habitual en el puerto 587)',
            'ssl' => 'SSL (TLS implícito, habitual en el puerto 465)',
            'none' => 'Ninguno',
        ],
        'explainer' => 'Deja el servidor SMTP en blanco para enviar mediante mail() de PHP en su lugar (no recomendado; consulta la sección de entregabilidad del README). No se puede enviar ningún correo hasta que se configure una dirección de remitente.',
        'save' => 'Guardar',
    ],

    'MapSettingsForm' => [
        'legend' => 'Teselas del mapa',
        'notice' => 'Déjalo en blanco para usar OpenStreetMap. Para usar un proveedor con clave, pega su plantilla de URL con un {apiKey} literal donde debe ir la clave, más la clave y la atribución más abajo.',
        'urlLabel' => 'Plantilla de URL de teselas',
        'keyLabel' => 'Clave de API',
        'keyPlaceholder' => 'La clave de API de tu proveedor de teselas',
        'attributionLabel' => 'Atribución',
        'attributionPlaceholder' => '© OpenStreetMap contributors',
        'save' => 'Guardar',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Inicio de sesión con Google',
        'clientIdLabel' => 'ID de cliente',
        'clientIdPlaceholder' => 'ID de cliente de OAuth de Google',
        'secretLabel' => 'Secreto de cliente',
        'secretPlaceholder' => [
            'set' => 'El secreto de cliente está establecido; déjalo en blanco para conservarlo',
            'unset' => 'Secreto de cliente de OAuth de Google',
        ],
        'explainer' => 'Ambos son necesarios para que aparezca "Continuar con Google" al registrarse e iniciar sesión. En tu cliente de OAuth de Google Cloud, configura el URI de redirección autorizado como {url}; borra el ID de cliente para desactivarlo.',
        'save' => 'Guardar',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Clave de sitio',
        'turnstileSiteKeyPlaceholder' => 'Clave de sitio de Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Clave secreta',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'La clave secreta está establecida; déjala en blanco para conservarla',
            'unset' => 'Clave secreta de Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Ambas claves son necesarias para que aparezca el CAPTCHA al registrarse e iniciar sesión. Borra la clave de sitio para desactivarlo.',
        'recaptchaLegend' => 'Google reCAPTCHA (recuperación de bloqueo de cuenta)',
        'recaptchaSiteKeyLabel' => 'Clave de sitio',
        'recaptchaSiteKeyPlaceholder' => 'Clave de sitio de Google reCAPTCHA v2',
        'recaptchaSecretKeyLabel' => 'Clave secreta',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'La clave secreta está establecida; déjala en blanco para conservarla',
            'unset' => 'Clave secreta de Google reCAPTCHA v2',
        ],
        'recaptchaExplainer' => 'Ambas claves son necesarias. Cuando están configuradas, una cuenta que alcanza su límite de intentos de inicio de sesión puede volver a entrar superando esta prueba en lugar de esperar el bloqueo; si no están configuradas, el bloqueo es una espera obligatoria. Usa reCAPTCHA v2 ("No soy un robot"). Borra la clave de sitio para desactivarlo.',
        'save' => 'Guardar',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Lo usan las funciones de IA del sitio (resúmenes de temas de tendencia, etc.). Deja el modelo en blanco para usar el Free Models Router, que OpenRouter elige al azar entre lo que esté disponible gratis en cada momento y que nunca puede generar coste.',
        'keyLabel' => 'Clave de API',
        'keyPlaceholder' => [
            'set' => 'La clave de API está establecida; déjala en blanco para conservarla',
            'unset' => 'Clave de API de OpenRouter',
        ],
        'clearKeyLabel' => 'Eliminar la clave de API guardada (desactiva las funciones de IA)',
        'modelLabel' => 'Modelo',
        'neverSpendLabel' => 'No permitir nunca que esto gaste dinero (recomendado)',
        'explainer' => 'Con esta protección activada, cada solicitud limita su precio a cero, así que falla directamente en lugar de recurrir a un modelo de pago cuando no hay ningún proveedor gratuito disponible. Elimina la clave de API guardada para desactivar las funciones de IA por completo.',
        'save' => 'Guardar',
    ],

    'AboutSettingsForm' => [
        'legend' => 'Acerca de',
        'description' => 'Texto sin formato: las líneas en blanco separan los párrafos. El primer párrafo se usará como descripción del sitio.',
        'save' => 'Guardar',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Términos del servicio',
        'description' => 'Texto sin formato: las líneas en blanco separan los párrafos.',
        'save' => 'Guardar',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Política de privacidad',
        'description' => 'Texto sin formato: las líneas en blanco separan los párrafos.',
        'save' => 'Guardar',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'Resumen por correo',
        'fieldLabel' => 'Párrafo de cierre',
        'notice' => 'Se añade cerca del final de cada resumen, después de la lista de lo que el miembro se perdió. Texto sin formato. Déjalo en blanco para volver al texto que trae este software de fábrica.',
        'save' => 'Guardar',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Favicon actual',
        'save' => 'Subir favicon',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Imagen de portada',
        'explainer' => 'Otros sitios la muestran cuando alguien comparte un enlace a este: son solo metadatos Open Graph, nunca aparece en la página. Se recorta a 1200×630. Sin ella, las vistas previas de enlace no llevan ninguna imagen.',
        'currentAlt' => 'Imagen de portada actual',
        'save' => 'Subir imagen',
    ],
];
