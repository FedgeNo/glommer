<?php

declare(strict_types=1);

/**
 * The controls on somebody else's profile, in Portuguese - befriending them,
 * following them across the Fediverse, writing to them, and the two ways of
 * having nothing more to do with them. See src/locales/en/Friendship.php for
 * what these classes are.
 */

return [
    'FriendRemoveButton' => [
        'name' => 'Remover amigo',
    ],

    'FriendRequestAcceptButton' => [
        'name' => 'Aceitar',
    ],

    'FriendRequestDenyButton' => [
        'name' => 'Recusar',
    ],

    'UserFollowButton' => [
        'follow' => 'Seguir',
        'unfollow' => 'Deixar de seguir',
    ],

    'UserBlockButton' => [
        'name' => 'Bloquear',
    ],

    'UserUnblockButton' => [
        'name' => 'Desbloquear',
    ],

    'OtherUser' => [
        // A verb phrase rather than "Mensagem", which is the noun for one
        // message (see MainNavigation's 'messages' => 'Mensagens') - this
        // opens a conversation, it isn't a count of anything.
        'message' => 'Enviar mensagem',
    ],
];
