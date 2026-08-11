<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/ModerationAdmin.php for what this fragment
 * covers.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Publicación',
            'message' => 'Mensaje',
            'user' => 'Usuario',
        ],
        // Rephrased around "Denuncia sobre" rather than a past participle
        // agreeing with {type} ("denunciada"/"denunciado") - the token can be
        // any of three genders and the phrasing has to hold for all of them.
        'summary' => ['before' => 'Denuncia sobre {type} #{id}, de ', 'after' => ''],
        'reasonLine' => 'Motivo: {reason}',
        'banReporterLabel' => 'Banear al denunciante',
        'banReportedUserLabel' => 'Banear al usuario denunciado',
        'deleteLabel' => 'Eliminar {type}',
        'reportedImageAlt' => 'Imagen denunciada',
        'attachmentUnavailable' => 'Un archivo adjunto denunciado ya no está disponible.',
        'viewAttachment' => 'Ver archivo adjunto denunciado',
        'missing' => [
            'noSnapshot' => 'El contenido denunciado ya no está disponible.',
            'unknownType' => 'Tipo de contenido desconocido.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'No hay denuncias.',
    ],

    'ModQueueLinks' => [
        'intro' => 'Las colas son lo bastante largas como para leerlas página por página, así que tienen sus propias páginas.',
        'reportsLabel' => 'Denuncias',
        'bannedUsersLabel' => 'Usuarios baneados',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Ningún moderador ha hecho nada todavía.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'No hay entidades baneadas de las tendencias.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'No hay servidores bloqueados.',
    ],

    'RelayCard' => [
        'accepted' => 'Suscrito ',
        'waiting' => 'Esperando a que el relé acepte - suscrito ',
    ],

    'RelayList' => [
        'emptyNotice' => 'No estás suscrito a ningún relé. Aquí no llega nada salvo lo que sigan los miembros.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Todavía no ha llegado nada a través de un relé. Las publicaciones aparecen aquí a medida que los servidores del otro lado las publican.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Suscribirse a un relé',
        'explainerOne' => 'Un relé es un flujo compartido y sin filtrar: aquí llega cada publicación pública de cualquier otro servidor suscrito, y las de este servidor salen hacia todos ellos. Así es como una instancia nueva encuentra a alguien, ya que de otro modo la federación solo trae lo que alguien de aquí ya sigue.',
        'explainerTwo' => 'La carga no depende de ti: es lo que esos servidores publiquen, tranquila una semana y con miles de publicaciones por hora la siguiente, y tu almacenamiento, tu cola de entrega y tu cola de moderación la asumen por completo. Las publicaciones retransmitidas no aparecen en el feed principal ni en el de amigos; van al feed de relé, que la gente abre a propósito.',
        'addressLabel' => 'Dirección del relé',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Suscribirse',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Estilo de suscripción',
        'options' => [
            'public' => 'Seguir el flujo público (lo que esperan la mayoría de los relés)',
            'actor' => 'Seguir el actor propio del relé',
        ],
        'retryNotice' => 'Si el relé nunca acepta, cancela la suscripción y prueba el otro estilo: algunos programas de relé solo reconocen uno de los dos.',
    ],

    'SiteCounters' => [
        'members' => 'Miembros: {count} ({joined} se unieron en los últimos {days} días)',
        'activeMembers' => 'Miembros activos en los últimos {days} días: {count} ({posted} de ellos publicaron)',
        'posts' => 'Publicaciones escritas aquí: {count} ({recent} en los últimos {days} días)',
        'deliveries' => 'Entregas federadas en los últimos {days} días: {delivered} llegaron, {undeliverable} se dieron por perdidas',
        'queued' => 'Esperando para salir: {count} ({failing} ya rechazadas al menos una vez)',
        'pendingReads' => 'Publicaciones pendientes de leer desde otros servidores: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Ejecuta la batería de pruebas del sitio y consulta los resultados. Tarda unos segundos, así que se abre en su propia página.',
        'runLabel' => 'Ejecutar pruebas',
    ],

    'HelpArticle' => [
        'backLabel' => 'Volver a toda la ayuda',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'No hay sugerencias por ahora: las sugerencias provienen de los amigos de las personas con las que ya eres amigo. Busca arriba para encontrar a alguien por su nombre.',
        'noMatches' => 'Aquí no hay nadie que coincida con eso.',
    ],
];
