<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/MainNavigation.php for what this fragment
 * covers.
 */

return [
    'MainNavigation' => [
        'toggleLabel' => 'Menú',
        'loggedInAs' => 'Sesión iniciada como {name}',
        'friendsFeed' => 'Feed de amigos',
        'relayFeed' => 'Feed de relé',
        'users' => 'Usuarios',
        'tags' => 'Etiquetas',
        // "Tendencias" rather than a literal "Temas": this is the
        // trending-topics page, it is the word Spanish social apps use for
        // one, and it is what the site's own Spanish already calls this same
        // page in the WelcomeBanner string (PostAndSocial.php) - even though
        // AdminSettings.php separately renders "trending-topic summaries" as
        // "temas de tendencia" in a different, more technical context.
        'topics' => 'Tendencias',
        'map' => 'Mapa',
        'locations' => 'Ubicaciones',
        'search' => 'Buscar',
        'messages' => 'Mensajes',
        'bookmarks' => 'Guardados',
        'help' => 'Ayuda',
        'about' => 'Acerca de',
        'login' => 'Iniciar sesión',
        'signup' => 'Registrarse',
        'friends' => 'Amigos',
        'drafts' => 'Borradores y programadas',
        'userSettings' => 'Ajustes de usuario',
        'modSettings' => 'Ajustes de moderación',
        'adminSettings' => 'Ajustes de administración',
    ],

    'LogoutForm' => [
        'submit' => 'Cerrar sesión',
    ],
];
