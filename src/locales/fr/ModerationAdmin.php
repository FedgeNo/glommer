<?php

declare(strict_types=1);

/**
 * French for the moderation queue and relay admin pages. See
 * src/locales/en/ModerationAdmin.php for what each of these is.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Publication',
            'message' => 'Message',
            'user' => 'Utilisateur',
        ],
        'summary' => ['before' => 'Signalement - {type} n° {id}, par ', 'after' => ''],
        'reasonLine' => 'Motif : {reason}',
        'banReporterLabel' => 'Bannir l\'auteur du signalement',
        'banReportedUserLabel' => 'Bannir l\'utilisateur signalé',
        'deleteLabel' => 'Supprimer {type}',
        'reportedImageAlt' => 'Image signalée',
        'attachmentUnavailable' => 'Une pièce jointe signalée n\'est plus disponible.',
        'viewAttachment' => 'Voir la pièce jointe signalée',
        'missing' => [
            'noSnapshot' => 'Le contenu signalé n\'est plus disponible.',
            'unknownType' => 'Type de contenu inconnu.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'Aucun signalement.',
    ],

    'ModQueueLinks' => [
        'intro' => 'Les files d\'attente sont assez longues pour se lire une page à la fois, elles ont donc leurs propres pages.',
        'reportsLabel' => 'Signalements',
        'bannedUsersLabel' => 'Utilisateurs bannis',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Aucun modérateur n\'a encore rien fait.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'Aucune entité bannie des tendances.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'Aucun serveur bloqué.',
    ],

    'RelayCard' => [
        'accepted' => 'Abonné ',
        'waiting' => 'En attente que le relais accepte - abonné ',
    ],

    'RelayList' => [
        'emptyNotice' => 'Non abonné à aucun relais. Rien n\'arrive ici à part ce que les membres suivent.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Rien n\'est encore passé par un relais. Les publications apparaissent ici au fur et à mesure que les serveurs de l\'autre côté les publient.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'S\'abonner à un relais',
        'explainerOne' => 'Un relais est un flux partagé à haut débit : chaque publication publique de chaque autre serveur abonné arrive ici, et celles de ce serveur repartent vers tous les autres. C\'est ainsi qu\'une nouvelle instance trouve qui que ce soit, puisque la fédération ne transporte sinon que ce que quelqu\'un ici suit déjà.',
        'explainerTwo' => 'La charge n\'est pas la vôtre à prévoir - elle correspond à ce que ces serveurs publient, calme une semaine et des milliers de publications par heure la suivante, et votre stockage, votre file d\'attente de livraison et votre file d\'attente de modération la supportent tous. Les publications relayées restent hors des fils principal et amis ; elles vont dans le fil Relais, que les gens ouvrent volontairement.',
        'addressLabel' => 'Adresse du relais',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'S\'abonner',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Style d\'abonnement',
        'options' => [
            'public' => 'Suivre le flux public (ce qu\'attendent la plupart des relais)',
            'actor' => 'Suivre l\'acteur du relais lui-même',
        ],
        'retryNotice' => 'Si le relais n\'accepte jamais, désabonnez-vous et essayez l\'autre style - certains logiciels de relais ne reconnaissent qu\'un seul des deux.',
    ],

    'SiteCounters' => [
        'members' => 'Membres : {count} ({joined} inscrits ces {days} derniers jours)',
        'activeMembers' => 'Membres actifs ces {days} derniers jours : {count} ({posted} d\'entre eux ont publié)',
        'posts' => 'Publications écrites ici : {count} ({recent} au cours des {days} derniers jours)',
        'deliveries' => 'Distributions fédérées ces {days} derniers jours : {delivered} arrivées, {undeliverable} abandonnées',
        'queued' => 'En attente d\'envoi : {count} ({failing} déjà refusées au moins une fois)',
        'pendingReads' => 'Publications en attente de lecture depuis d\'autres serveurs : {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Exécutez la suite de tests du site et consultez les résultats. Cela prend quelques secondes, elle s\'ouvre donc sur sa propre page.',
        'runLabel' => 'Exécuter les tests',
    ],

    'HelpArticle' => [
        'backLabel' => 'Retour à toute l\'aide',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'Aucune suggestion pour le moment - les suggestions proviennent des amis des personnes dont vous êtes déjà ami. Recherchez ci-dessus pour trouver quelqu\'un par son nom.',
        'noMatches' => 'Personne ici ne correspond à cela.',
    ],
];
