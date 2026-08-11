<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/PostAndSocial.php for what this fragment
 * covers.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Ver ', 'link' => 'más ubicaciones', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Publicaciones cerca de ti',
        'description' => 'Esto muestra las publicaciones más cercanas a un punto, dondequiera que haya actividad, por lejos que esté. Comparte tu ubicación para empezar desde donde estás, o elige un punto en el mapa.',
        'useMyLocation' => 'Usar mi ubicación',
        'pickOnMap' => 'Elegir en el mapa',
        'searchPlaceholder' => 'O escribe el nombre de un lugar…',
        'searchLabel' => 'Buscar un lugar',
        'locating' => 'Localizando…',
        'noGeolocation' => 'Tu navegador no puede compartir una ubicación.',
        'locationError' => 'No se pudo obtener tu ubicación. Comprueba el permiso de ubicación de tu navegador.',
    ],

    'PollDeadline' => [
        'final' => 'Resultado final',
        'closes' => ['before' => 'Cierra ', 'after' => ''],
        'days' => ['one' => 'en {count} día', 'other' => 'en {count} días'],
        'hours' => ['one' => 'en {count} hora', 'other' => 'en {count} horas'],
        'minutes' => ['one' => 'en {count} minuto', 'other' => 'en {count} minutos'],
        'underMinute' => 'en menos de un minuto',
    ],

    'PollTally' => [
        'voters' => ['one' => '1 persona votó ', 'other' => '{count} personas votaron '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Inicia sesión', 'after' => ' para publicar.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Inicia sesión', 'after' => ' para responder.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' republicó'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'En respuesta a ', 'after' => ''],
        'untitled' => 'esta publicación',
        'jumpToStart' => 'Ir al inicio',
    ],

    'TopicSummaryCard' => [
        'label' => 'Resumen generado por IA',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Te damos la bienvenida a ', 'after' => ''],
        'paragraphs' => [
            'Escribe algo en el cuadro de abajo y se publicará en tu feed. Cualquiera puede responder, y una respuesta no es más que una publicación con un padre, así que las conversaciones se anidan tan profundo como haga falta.',
            'Añade personas como amigos y sus publicaciones se sumarán a tu feed. El feed Global muestra todo lo que se escribe aquí, que es el lugar para encontrar a alguien a quien añadir.',
            'Este sitio forma parte del Fediverso: puedes seguir cuentas de Mastodon y de otros servidores por su identificador completo, y lo que publiques llegará a quienes te sigan allí. Busca un identificador como @someone@example.social y este servidor lo encontrará.',
            'Etiqueta una publicación con #hashtags y aparecerá en la página de esa etiqueta, y en Tendencias si suficientes personas están escribiendo sobre ella.',
            'Los mensajes entre miembros están cifrados de extremo a extremo: el servidor almacena texto cifrado que no puede leer. Actívalo en Ajustes.',
            'No tienes que publicar de inmediato: guarda un borrador, o fija una hora y se publicará solo. Ambos están en Borradores y programadas.',
        ],
        'more' => ['before' => 'Hay más información en ', 'link' => 'las páginas de ayuda', 'after' => ', incluido cómo trasladar una cuenta aquí desde otro sitio.'],
        'dontShowAgain' => 'No volver a mostrar esto',
    ],
];
