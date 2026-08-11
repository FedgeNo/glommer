<?php

declare(strict_types=1);

/**
 * Italian for the post card, the small controls around it, and the page
 * furniture that doesn't belong to any one feature. See
 * src/locales/en/PostChrome.php for what this fragment covers.
 */

return [
    'EmojiPickerTriggerButton' => [
        'label' => 'Inserisci emoji',
    ],

    'LinkImagePreview' => [
        'alt' => 'Immagine di anteprima del link',
    ],

    'LinkImageRemoveButton' => [
        'label' => 'Rimuovi immagine',
    ],

    'LinkItem' => [
        'alt' => 'Immagine di anteprima del link',
    ],

    'MapScrubber' => [
        'play' => 'Riproduci',
        'pause' => 'Pausa',
        'cumulativeMode' => 'Fino ad allora',
        'windowMode' => 'Solo allora',
        'rangeLabel' => 'Mostra post fino a una data',
        // "post" does not inflect for number in Italian, but the participle
        // describing it still has to: "pubblicato" for one, "pubblicati" for
        // more than one, even though the noun beside {count} reads the same
        // either way.
        //
        // "entro"/"intorno a" rather than the contracted articles "fino al"/
        // "intorno al": {date} arrives already formatted and starts with a
        // bare day number, and Italian's definite article obligatorily elides
        // before a vowel-initial one ("all'11", "all'8") - a fixed "al" is
        // wrong on those days. Both prepositions here take a bare date with
        // no article at all, so neither has that day-of-the-month problem -
        // see Members.php's User.joined for the same fix.
        'cumulativeLabel' => ['one' => 'Pubblicato entro {date} — {count} post', 'other' => 'Pubblicati entro {date} — {count} post'],
        'windowLabel' => ['one' => 'Pubblicato intorno a {date} — {count} post', 'other' => 'Pubblicati intorno a {date} — {count} post'],
    ],

    'Post' => [
        'pageTitleByAuthor' => 'Post di {name}',
        'pageTitleUntitled' => 'Post',
        'imageAltByAuthor' => 'Foto pubblicata da {name}',
        'imageAltUntitled' => 'Foto',
    ],

    'PostBookmarkButton' => [
        'remove' => 'Rimuovi dai salvati',
        'add' => 'Salva',
    ],

    'PostRepostButton' => [
        'undo' => 'Annulla ripubblicazione',
        'repost' => 'Ripubblica',
    ],

    'QuotedPost' => [
        'viewLink' => 'Visualizza il post citato',
    ],

    'ReceivedFriendRequestSection' => [
        'heading' => 'Richieste in sospeso',
    ],

    'ScrollToTopButton' => [
        'label' => 'Torna in cima',
    ],

    'SearchClearButton' => [
        'label' => 'Cancella ricerca',
    ],

    'SitePolicyLinks' => [
        'terms' => 'Termini di servizio',
        'privacy' => 'Informativa sulla privacy',
    ],

    'SkipLink' => [
        'label' => 'Passa al contenuto',
    ],

    'TopicHeading' => [
        'searchLink' => 'Cerca questo',
        'noPosts' => 'Al momento nessun post menziona questo.',
    ],

    'PopularEntityList' => [
        'emptyNotice' => 'Non è stato ancora scritto nulla di questo tipo.',
    ],

    'TrendingEntitySection' => [
        // "Tendenze", as the navigation calls the same thing.
        'heading' => 'Tendenze',
    ],
];
