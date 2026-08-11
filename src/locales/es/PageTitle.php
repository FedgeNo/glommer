<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/PageTitle.php for what this fragment covers.
 */

return [
    'PageTitle' => [
        'about' => 'Acerca de',
        'adminBanned' => 'Usuarios baneados',
        'adminModSettings' => 'Ajustes de moderación',
        'adminReports' => 'Denuncias',
        'adminSettings' => 'Ajustes de administración',
        'adminTests' => 'Pruebas',
        'authGoogleCallbackAccountDeleted' => 'Cuenta eliminada',
        'authGoogleCallbackDeleteAccount' => 'Eliminar cuenta',
        'authGoogleCallbackLogin' => 'Iniciar sesión',
        'bookmarks' => 'Guardados',
        // Echoes VerificationNotice.instructions ("Revisa tu bandeja de
        // entrada...", AccountForms.php), which opens with the same instruction.
        'checkInbox' => 'Revisa tu bandeja de entrada',
        'drafts' => 'Borradores y programadas',
        'draftsEditDraft' => 'Editar borrador',
        'draftsEditScheduled' => 'Editar publicación programada',
        // Matches the Spanish already established for this exact quoted
        // phrase in Notification.passwordRemovedGoogle (MessagingExtras.php).
        'forgotPassword' => '¿Olvidaste tu contraseña?',
        'friendsFeed' => 'Feed de amigos',
        'help' => 'Ayuda',
        'helpDescription' => 'Guías y respuestas para usar el sitio.',
        'locations' => 'Ubicaciones',
        'locationsDescription' => 'Publicaciones de los lugares más cercanos a ti.',
        'locationsPlaceDescription' => 'Publicaciones cerca de {place}.',
        'login' => 'Iniciar sesión',
        'loginVerificationCode' => 'Código de verificación',
        'map' => 'Mapa',
        'mapDescription' => 'Un mapa de publicaciones de todo el mundo - encuentra personas y cosas cerca de ti.',
        'messages' => 'Mensajes',
        'messagesWithUser' => 'Mensajes con {name}',
        'notifications' => 'Notificaciones',
        'privacy' => 'Política de privacidad',
        // quote.php loads the composer (needsEditor), so this is an action
        // label like 'search' -> 'Buscar', not the noun 'Cita' (which also
        // means "appointment").
        'quote' => 'Citar',
        'relayFeed' => 'Feed de relé',
        'relayFeedDescription' => 'Publicaciones públicas que llegan de los relés a los que está suscrito este servidor.',
        'resetPassword' => 'Restablecer contraseña',
        'revertEmail' => 'Revertir cambio de correo electrónico',
        'search' => 'Buscar',
        'signup' => 'Registrarse',
        'tags' => 'Etiquetas',
        'tagsDescription' => 'Explora las etiquetas populares y de tendencia en {siteTitle}.',
        'tagsTagDescription' => 'Publicaciones etiquetadas con {tag} en {siteTitle}.',
        'terms' => 'Términos del servicio',
        'topics' => 'Tendencias',
        'topicsDescription' => 'De qué habla la gente en {siteTitle} ahora mismo.',
        'topicsEntityDescription' => '{typeLabel} - publicaciones que mencionan {entityTitle} en {siteTitle}.',
        // Built around "que la gente comenta" (a transitive verb, so the
        // relative "que" needs no gendered article) rather than "de los/las
        // que" - {typePlural} arrives untranslated and its gender is
        // unknowable, the same problem ReportCard.summary solves the same
        // way (ModerationAdmin.php).
        'topicsTypeDescription' => '{typePlural} que la gente comenta en {siteTitle} ahora mismo.',
        'unsubscribe' => 'Resúmenes por correo',
        // Mirrors Post's pageTitleByAuthor ('Publicación de {name}',
        // PostChrome.php): Spanish has no possessive 's, so the phrase
        // rebuilds around {name} with "de" instead.
        'userFriends' => 'Amigos de {name}',
        // No trailing period, matching the English source (unlike its
        // sibling description keys).
        'userFriendsDescription' => 'Amigos de {name} en {siteTitle}',
        'userSettings' => 'Ajustes de usuario',
        'users' => 'Usuarios',
        'verifyEmail' => 'Verificar correo electrónico',
    ],
];
