<?php

declare(strict_types=1);

/**
 * Italian for the words a page's browser tab carries, and the meta/Open
 * Graph description search engines and link previews read. See
 * src/locales/en/PageTitle.php for what this fragment covers.
 */

return [
    'PageTitle' => [
        'about' => 'Chi siamo',
        'adminBanned' => 'Utenti bannati',
        'adminModSettings' => 'Impostazioni moderazione',
        'adminReports' => 'Segnalazioni',
        'adminSettings' => 'Impostazioni amministratore',
        'adminTests' => 'Test',
        'authGoogleCallbackAccountDeleted' => 'Account eliminato',
        'authGoogleCallbackDeleteAccount' => 'Elimina account',
        'authGoogleCallbackLogin' => 'Accedi',
        'bookmarks' => 'Salvati',
        // Echoes VerificationNotice.instructions (AccountForms.php), which
        // opens with the same instruction.
        'checkInbox' => 'Controlla la tua casella di posta',
        'drafts' => 'Bozze e programmati',
        'draftsEditDraft' => 'Modifica bozza',
        'draftsEditScheduled' => 'Modifica post programmato',
        // Matches the Italian already established for this exact quoted
        // phrase in Notification.passwordRemovedGoogle (MessagingExtras.php).
        'forgotPassword' => 'Password dimenticata',
        'friendsFeed' => 'Feed amici',
        'help' => 'Aiuto',
        'helpDescription' => 'Guide e risposte per usare il sito.',
        'locations' => 'Posizioni',
        'locationsDescription' => 'Post dai luoghi più vicini a te.',
        'locationsPlaceDescription' => 'Post vicino a {place}.',
        'login' => 'Accedi',
        'loginVerificationCode' => 'Codice di verifica',
        'map' => 'Mappa',
        'mapDescription' => 'Una mappa di post da tutto il mondo - trova persone e cose vicino a te.',
        'messages' => 'Messaggi',
        'messagesWithUser' => 'Messaggi con {name}',
        'notifications' => 'Notifiche',
        'privacy' => 'Informativa sulla privacy',
        // quote.php loads the composer (needsEditor), so this is an action
        // label like 'search' -> 'Cerca', not a noun for a saying.
        'quote' => 'Cita',
        'relayFeed' => 'Feed relay',
        'relayFeedDescription' => 'Post pubblici in arrivo dai relay a cui è iscritto questo server.',
        'resetPassword' => 'Reimposta password',
        // Matches AccountExtras.php's EmailRevertForm.submit exactly.
        'revertEmail' => 'Annulla cambio email',
        'search' => 'Cerca',
        'signup' => 'Registrati',
        'tags' => 'Tag',
        'tagsDescription' => 'Esplora gli hashtag di tendenza e popolari su {siteTitle}.',
        'tagsTagDescription' => 'Post con tag {tag} su {siteTitle}.',
        'terms' => 'Termini di servizio',
        'topics' => 'Tendenze',
        'topicsDescription' => 'Di cosa parla la gente su {siteTitle} in questo momento.',
        'topicsEntityDescription' => '{typeLabel} - post che menzionano {entityTitle} su {siteTitle}.',
        // Built around "di cui" (a relative pronoun that never agrees with
        // gender or number) rather than an article or adjective bound to
        // {typePlural} - its gender is unknowable, the same problem
        // ReportCard.summary solves the same way (ModerationAdmin.php).
        'topicsTypeDescription' => '{typePlural} di cui la gente parla su {siteTitle} in questo momento.',
        'unsubscribe' => 'Riepiloghi via email',
        // Mirrors Post's pageTitleByAuthor ('Post di {name}', PostChrome.php):
        // Italian has no possessive 's, so the phrase rebuilds around {name}
        // with "di" instead.
        'userFriends' => 'Amici di {name}',
        // No trailing period, matching the English source (unlike its
        // sibling description keys).
        'userFriendsDescription' => 'Amici di {name} su {siteTitle}',
        'userSettings' => 'Impostazioni utente',
        'users' => 'Utenti',
        'verifyEmail' => 'Verifica email',
    ],
];
