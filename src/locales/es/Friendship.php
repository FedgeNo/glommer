<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/Friendship.php for what this fragment covers.
 */

return [
    'FriendRemoveButton' => [
        // "Eliminar de amigos" rather than "Eliminar amigo": this codebase
        // keeps "Eliminar" for destroying something (an account, a post),
        // and "amigo" as its bare object would read as deleting the person
        // rather than ending the friendship.
        'name' => 'Eliminar de amigos',
    ],

    'FriendRequestAcceptButton' => [
        'name' => 'Aceptar',
    ],

    'FriendRequestDenyButton' => [
        'name' => 'Rechazar',
    ],

    'UserFollowButton' => [
        'follow' => 'Seguir',
        'unfollow' => 'Dejar de seguir',
    ],

    'UserBlockButton' => [
        'name' => 'Bloquear',
    ],

    'UserUnblockButton' => [
        'name' => 'Desbloquear',
    ],

    'OtherUser' => [
        // "Enviar mensaje" rather than bare "Mensaje": the English comment
        // warns this is the link to a conversation, not the noun for one
        // message - which is exactly what bare "Mensaje" would read as here.
        'message' => 'Enviar mensaje',
    ],
];
