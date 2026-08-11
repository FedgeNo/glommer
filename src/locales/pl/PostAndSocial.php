<?php

declare(strict_types=1);

/**
 * Polish for the composer, poll and feed-context classes. See
 * src/locales/en/PostAndSocial.php for what each of these is.
 *
 * PollDeadline and PollTally are the two places outside RelativeTime and
 * MapScrubber where a count drives a noun through all four Polish forms
 * together with a verb or a preposition's case - "za {count} dzień/dni/dni/
 * dnia" (accusative for one, the paucal plural for few, the genitive plural
 * "za" still governs for many) and, for voters, a genitive-plural subject of
 * 5+ taking a neuter-singular verb ("{count} osób głosowało", not
 * "głosowały") rather than the ordinary plural few takes.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Zobacz ', 'link' => 'więcej lokalizacji', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Posty w twojej okolicy',
        'description' => 'To pokazuje posty najbliższe danemu punktowi - niezależnie od tego, jak daleko toczy się aktywność. Udostępnij swoją lokalizację, aby zacząć od miejsca, w którym jesteś, albo zamiast tego wybierz punkt na mapie.',
        'useMyLocation' => 'Użyj mojej lokalizacji',
        'pickOnMap' => 'Wybierz na mapie',
        'searchPlaceholder' => 'Albo wpisz nazwę miejsca…',
        'searchLabel' => 'Wyszukaj miejsce',
        'locating' => 'Lokalizowanie…',
        'noGeolocation' => 'Twoja przeglądarka nie może udostępnić lokalizacji.',
        'locationError' => 'Nie udało się ustalić twojej lokalizacji. Sprawdź uprawnienia lokalizacji w przeglądarce.',
    ],

    'PollDeadline' => [
        'final' => 'Wynik końcowy',
        'closes' => ['before' => 'Kończy się ', 'after' => ''],
        'days' => [
            'one' => 'za {count} dzień',
            'few' => 'za {count} dni',
            'many' => 'za {count} dni',
            'other' => 'za {count} dnia',
        ],
        'hours' => [
            'one' => 'za {count} godzinę',
            'few' => 'za {count} godziny',
            'many' => 'za {count} godzin',
            'other' => 'za {count} godziny',
        ],
        'minutes' => [
            'one' => 'za {count} minutę',
            'few' => 'za {count} minuty',
            'many' => 'za {count} minut',
            'other' => 'za {count} minuty',
        ],
        'underMinute' => 'za mniej niż minutę',
    ],

    'PollTally' => [
        'voters' => [
            'one' => '1 osoba głosowała ',
            'few' => '{count} osoby głosowały ',
            'many' => '{count} osób głosowało ',
            'other' => '{count} osoby głosowało ',
        ],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Zaloguj się', 'after' => ', aby opublikować.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Zaloguj się', 'after' => ', aby odpowiedzieć.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => 'Repost użytkownika ', 'after' => ''],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'W odpowiedzi na ', 'after' => ''],
        'untitled' => 'ten post',
        'jumpToStart' => 'Przejdź do początku',
    ],

    'TopicSummaryCard' => [
        'label' => 'Podsumowanie wygenerowane przez AI',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Witamy w ', 'after' => ''],
        'paragraphs' => [
            'Napisz coś w polu poniżej, a trafi to na twój kanał. Każdy może odpowiedzieć, a odpowiedź to po prostu post z postem nadrzędnym, więc rozmowy zagnieżdżają się tak głęboko, jak potrzeba.',
            'Dodawaj ludzi do znajomych, a ich posty pojawią się na twoim kanale. Kanał globalny - nazwa serwisu w lewym górnym rogu - pokazuje wszystko, co tu napisano, i jest najlepszym miejscem, aby znaleźć kogoś do dodania.',
            'Ta witryna jest częścią Fediwersum: możesz obserwować konta na Mastodonie i innych serwerach po ich pełnym identyfikatorze, a to, co publikujesz, dociera do osób, które cię tam obserwują. Wyszukaj identyfikator w rodzaju @someone@example.social, a ten serwer go odnajdzie.',
            'Oznacz post #hashtagiem, a pojawi się na stronie tego hashtagu, a także w sekcji Na czasie, jeśli wystarczająco dużo osób o nim pisze.',
            'Wiadomości między użytkownikami są szyfrowane od końca do końca - serwer przechowuje zaszyfrowaną treść, której nie może odczytać. Włącz to w Ustawieniach.',
            'Nie musisz publikować od razu: zapisz szkic albo ustaw godzinę, a post opublikuje się sam. Oba znajdziesz w sekcji Szkice i zaplanowane.',
        ],
        'more' => ['before' => 'Więcej znajdziesz w ', 'link' => 'artykułach pomocy', 'after' => ', w tym jak przenieść tutaj konto z innego miejsca.'],
        'dontShowAgain' => 'Nie pokazuj tego ponownie',
    ],
];
