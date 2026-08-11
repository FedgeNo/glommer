<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/Members.php for what this fragment covers.
 */

return [
    'User' => [
        'joined' => 'Se unió el {date}',
    ],

    'FriendRequestButton' => [
        'add' => 'Añadir amigo',
        'cancel' => 'Cancelar solicitud',
    ],

    'StagedPostWhen' => [
        // Feminine to match how this state is already named elsewhere:
        // MainNavigation's "Borradores y programadas" and PageTitle's
        // "Editar publicación programada" both agree with "publicación".
        'scheduled' => 'Programada para {when}',
        'draft' => 'Borrador - se publica solo cuando tú lo digas',
    ],
];
