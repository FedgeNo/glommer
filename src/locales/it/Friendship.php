<?php

declare(strict_types=1);

/**
 * Italian for the controls on somebody else's profile. See
 * src/locales/en/Friendship.php for what this fragment covers.
 */

return [
    'FriendRemoveButton' => [
        // "Rimuovi dagli amici" rather than "Rimuovi amico": this codebase
        // keeps "Rimuovi"/"Elimina" for destroying something outright, and
        // "amico" as its bare object would read as deleting the person
        // rather than ending the friendship.
        'name' => 'Rimuovi dagli amici',
    ],

    'FriendRequestAcceptButton' => [
        'name' => 'Accetta',
    ],

    'FriendRequestDenyButton' => [
        'name' => 'Rifiuta',
    ],

    'UserFollowButton' => [
        'follow' => 'Segui',
        'unfollow' => 'Non seguire più',
    ],

    'UserBlockButton' => [
        'name' => 'Blocca',
    ],

    'UserUnblockButton' => [
        'name' => 'Sblocca',
    ],

    'OtherUser' => [
        // "Invia messaggio" rather than bare "Messaggio": the English
        // comment warns this is the link to a conversation, not the noun for
        // one message - which is exactly what the bare noun would read as
        // here.
        'message' => 'Invia messaggio',
    ],
];
