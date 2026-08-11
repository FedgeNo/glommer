<?php

declare(strict_types=1);

/**
 * Italian for the controls that belong to no one feature. See
 * src/locales/en/Chrome.php for what this fragment covers.
 */

return [
    'CarouselAutoplayButton' => [
        'name' => 'Riproduzione automatica',
    ],

    'CarouselNextButton' => [
        'name' => 'Successivo',
    ],

    'CarouselPrevButton' => [
        'name' => 'Precedente',
    ],

    'MediaFullscreenButton' => [
        'name' => 'Schermo intero',
    ],

    'ComposerFilesRemoveButton' => [
        'name' => 'Rimuovi file',
    ],

    'NotificationDropdown' => [
        'showAll' => 'Mostra tutte',
    ],

    'HelpSearch' => [
        'placeholder' => 'Cerca nella guida…',
    ],

    'WelcomeBannerDismissButton' => [
        'name' => 'Ho capito',
    ],

    'StagedPostDiscardButton' => [
        'name' => 'Scarta',
    ],

    'StagedPostEditButton' => [
        'name' => 'Modifica',
    ],

    'StagedPostPublishButton' => [
        'name' => 'Pubblica ora',
    ],

    'LogoutButton' => [
        'name' => 'Esci',
    ],

    'AvatarUploadForm' => [
        'submit' => 'Aggiorna avatar',
    ],

    'BannedUserSearchBox' => [
        'placeholder' => 'Cerca utenti bannati…',
    ],

    'BannedUserSection' => [
        'heading' => 'Utenti bannati',
    ],

    'SentFriendRequestSection' => [
        'heading' => 'Richieste inviate (in attesa di risposta)',
    ],

    'BannedTrendingEntity' => [
        // "Ban di {name}" rather than a participle like "Bannato da {name}":
        // the entity that was banned can be a hashtag, a person, a place, an
        // organization... each a different grammatical gender, and none of
        // them known here. The noun "ban" stays masculine regardless of what
        // was banned, so it carries no agreement to get wrong. "applicato da"
        // rather than "di": genitive "di" reads either way ("Mario's ban" as
        // easily as "a ban by Mario"), where "da" is unambiguously agentive -
        // the same fix blockedBy already has below.
        'bannedBy' => ['before' => 'Ban applicato da {name} ', 'after' => ''],
        'reason' => ' - {reason}',
    ],

    'BlockedServerCard' => [
        // "server" is always masculine in Italian, so unlike bannedBy above,
        // the participle here has a fixed gender to agree with.
        'blockedBy' => ['before' => 'Bloccato da {name} ', 'after' => ''],
        'deletedAccount' => 'un account eliminato',
        'reason' => ' - {reason}',
    ],
];
