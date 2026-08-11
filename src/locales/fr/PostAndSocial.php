<?php

declare(strict_types=1);

/**
 * French for the composer, poll and feed-context classes. See
 * src/locales/en/PostAndSocial.php for what each of these is.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Voir ', 'link' => 'plus de lieux', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Publications près de vous',
        'description' => 'Ceci affiche les publications les plus proches d\'un point - où que se trouve l\'activité, aussi loin soit-elle. Partagez votre position pour partir d\'où vous êtes, ou choisissez plutôt un endroit sur la carte.',
        'useMyLocation' => 'Utiliser ma position',
        'pickOnMap' => 'Choisir sur la carte',
        'searchPlaceholder' => 'Ou saisissez le nom d\'un lieu…',
        'searchLabel' => 'Rechercher un lieu',
        'locating' => 'Localisation en cours…',
        'noGeolocation' => 'Votre navigateur ne peut pas partager de position.',
        'locationError' => 'Impossible d\'obtenir votre position. Vérifiez l\'autorisation de localisation de votre navigateur.',
    ],

    'PollDeadline' => [
        'final' => 'Résultat final',
        'closes' => ['before' => 'Ferme ', 'after' => ''],
        'days' => ['one' => 'dans {count} jour', 'other' => 'dans {count} jours'],
        'hours' => ['one' => 'dans {count} heure', 'other' => 'dans {count} heures'],
        'minutes' => ['one' => 'dans {count} minute', 'other' => 'dans {count} minutes'],
        'underMinute' => 'dans moins d\'une minute',
    ],

    'PollTally' => [
        'voters' => ['one' => '{count} personne a voté ', 'other' => '{count} personnes ont voté '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Connectez-vous', 'after' => ' pour publier.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Connectez-vous', 'after' => ' pour répondre.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' a republié'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'En réponse à ', 'after' => ''],
        'untitled' => 'cette publication',
        'jumpToStart' => 'Aller au début',
    ],

    'TopicSummaryCard' => [
        'label' => 'Résumé généré par IA',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Bienvenue sur ', 'after' => ''],
        'paragraphs' => [
            'Écrivez quelque chose dans la zone ci-dessous et cela part sur votre fil. Tout le monde peut répondre, et une réponse n\'est qu\'une publication avec un parent, donc les conversations s\'imbriquent aussi profondément que nécessaire.',
            'Ajoutez des personnes comme amis et leurs publications rejoignent votre fil. Le fil Global - le nom du site, en haut à gauche - montre tout ce qui est écrit ici, c\'est l\'endroit pour trouver quelqu\'un à ajouter.',
            'Ce site fait partie du fédivers : vous pouvez suivre des comptes sur Mastodon et d\'autres serveurs grâce à leur identifiant complet, et ce que vous publiez atteint les personnes qui vous suivent là-bas. Recherchez un identifiant comme @someone@example.social et ce serveur ira le trouver.',
            'Ajoutez un #hashtag à une publication et elle apparaît sur la page de ce hashtag, ainsi que dans les tendances si suffisamment de personnes en parlent.',
            'Les messages entre membres sont chiffrés de bout en bout - le serveur stocke un texte chiffré qu\'il ne peut pas lire. Activez cela dans les paramètres.',
            'Vous n\'êtes pas obligé de publier tout de suite : enregistrez un brouillon, ou programmez une heure et la publication se fait toute seule. Les deux se trouvent sous Brouillons et programmées.',
        ],
        'more' => ['before' => 'Vous trouverez plus d\'informations dans ', 'link' => 'les pages d\'aide', 'after' => ', notamment comment déménager un compte ici depuis ailleurs.'],
        'dontShowAgain' => 'Ne plus afficher ceci',
    ],
];
