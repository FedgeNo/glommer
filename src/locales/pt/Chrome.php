<?php

declare(strict_types=1);

/**
 * The controls that belong to no one feature, in Portuguese - the media
 * carousel, the composer's file row, the notification dropdown, the help
 * search, and the odd button on a card somewhere. See
 * src/locales/en/Chrome.php for what these classes are.
 */

return [
    'CarouselAutoplayButton' => [
        'name' => 'Reprodução automática',
    ],

    'CarouselNextButton' => [
        'name' => 'Seguinte',
    ],

    'CarouselPrevButton' => [
        'name' => 'Anterior',
    ],

    'MediaFullscreenButton' => [
        'name' => 'Ecrã inteiro',
    ],

    'ComposerFilesRemoveButton' => [
        'name' => 'Remover ficheiros',
    ],

    'NotificationDropdown' => [
        'showAll' => 'Mostrar tudo',
    ],

    'HelpSearch' => [
        'placeholder' => 'Pesquisar na ajuda…',
    ],

    'WelcomeBannerDismissButton' => [
        'name' => 'Entendido',
    ],

    'StagedPostDiscardButton' => [
        'name' => 'Descartar',
    ],

    'StagedPostEditButton' => [
        'name' => 'Editar',
    ],

    'StagedPostPublishButton' => [
        'name' => 'Publicar agora',
    ],

    'LogoutButton' => [
        'name' => 'Terminar sessão',
    ],

    'AvatarUploadForm' => [
        'submit' => 'Atualizar avatar',
    ],

    'BannedUserSearchBox' => [
        'placeholder' => 'Pesquisar utilizadores banidos…',
    ],

    'BannedUserSection' => [
        'heading' => 'Utilizadores banidos',
    ],

    'SentFriendRequestSection' => [
        'heading' => 'Pedidos enviados (a aguardar resposta)',
    ],

    'BannedTrendingEntity' => [
        // Trailing space, same as English: RelativeTime's element goes
        // between this and 'after', and its own words already lead with
        // "há" - see src/locales/pt/RelativeTime.php - so nothing more is
        // needed once it lands.
        'bannedBy' => ['before' => 'Banido por {name} ', 'after' => ''],
        'reason' => ' - {reason}',
    ],

    'BlockedServerCard' => [
        'blockedBy' => ['before' => 'Bloqueado por {name} ', 'after' => ''],
        'deletedAccount' => 'uma conta eliminada',
        'reason' => ' - {reason}',
    ],
];
