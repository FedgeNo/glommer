<?php

declare(strict_types=1);

/**
 * Italian for the words on a member and the controls beside them. See
 * src/locales/en/Members.php for what this fragment covers.
 */

return [
    'User' => [
        // A label-and-value pair rather than "Membro dal {date}": {date}
        // arrives already formatted ("11 agosto 2026") and Italian's definite
        // article obligatorily elides before a vowel-initial day - "dall'11",
        // "dall'8" - which "dal {date}" cannot produce since the string has
        // no way to know what the token will start with. A colon needs no
        // article at all, so it is correct for every day of the month.
        'joined' => 'Data di iscrizione: {date}',
    ],

    'FriendRequestButton' => [
        'add' => 'Aggiungi amico',
        'cancel' => 'Annulla richiesta',
    ],

    'StagedPostWhen' => [
        // Masculine to agree with "post", which is how this codebase's
        // Italian refers to what is scheduled - see PostChrome.php's Post
        // entry and PostAndSocial.php's MapScrubber labels for the same
        // agreement.
        'scheduled' => 'Programmato per {when}',
        'draft' => 'Bozza - si pubblica solo quando lo decidi tu',
    ],
];
