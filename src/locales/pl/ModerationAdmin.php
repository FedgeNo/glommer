<?php

declare(strict_types=1);

/**
 * Polish for the moderation queue and relay admin pages. See
 * src/locales/en/ModerationAdmin.php for what each of these is.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Post',
            'message' => 'Wiadomość',
            'user' => 'Użytkownik',
        ],
        // {type} is one of the capitalized, nominative targetTypes values above
        // (Post/Wiadomość/Użytkownik) - a label, not a sentence role - so it can
        // sit here and in deleteLabel below without needing a different case or
        // capitalization in either place, even though "Post", "Wiadomość" and
        // "Użytkownik" are three different genders.
        'summary' => ['before' => '{type} nr {id} - zgłoszenie od ', 'after' => ''],
        'reasonLine' => 'Powód: {reason}',
        'banReporterLabel' => 'Zbanuj zgłaszającego',
        'banReportedUserLabel' => 'Zbanuj zgłoszonego użytkownika',
        'deleteLabel' => 'Usuń: {type}',
        'reportedImageAlt' => 'Zgłoszony obraz',
        'attachmentUnavailable' => 'Zgłoszony załącznik nie jest już dostępny.',
        'viewAttachment' => 'Wyświetl zgłoszony załącznik',
        'missing' => [
            'noSnapshot' => 'Zgłoszona treść nie jest już dostępna.',
            'unknownType' => 'Nieznany typ treści.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'Brak zgłoszeń.',
    ],

    'ModQueueLinks' => [
        'intro' => 'Kolejki są na tyle długie, że czyta się je stronami, więc mają własne strony.',
        'reportsLabel' => 'Zgłoszenia',
        'bannedUsersLabel' => 'Zbanowani użytkownicy',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Żaden moderator nic jeszcze nie zrobił.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'Brak zbanowanych tematów.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'Brak zablokowanych serwerów.',
    ],

    'RelayCard' => [
        'accepted' => 'Zasubskrybowano ',
        'waiting' => 'Oczekiwanie na akceptację przekaźnika - zasubskrybowano ',
    ],

    'RelayList' => [
        'emptyNotice' => 'Brak subskrypcji żadnych przekaźników. Nic tu nie trafia poza tym, co obserwują użytkownicy.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Nic jeszcze nie napłynęło przez przekaźnik. Posty pojawiają się tutaj w miarę jak publikują je serwery po drugiej stronie.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Subskrybuj przekaźnik',
        'explainerOne' => 'Przekaźnik to współdzielony strumień danych: każdy publiczny post z każdego innego zasubskrybowanego serwera trafia tutaj, a posty z tego serwera trafiają do nich wszystkich. Dzięki temu nowa instancja w ogóle kogokolwiek znajduje, bo w innym wypadku federacja przenosi tylko to, co ktoś tutaj już obserwuje.',
        'explainerTwo' => 'Obciążenia nie da się przewidzieć - zależy od tego, co publikują tamte serwery: spokojnie przez tydzień, a potem tysiące postów na godzinę, i to wszystko obciąża twoją przestrzeń dyskową, kolejkę dostarczania i kolejkę moderacji. Posty z przekaźnika nie trafiają do głównego kanału ani kanału znajomych; trafiają do Kanału przekaźnika, który ludzie otwierają świadomie.',
        'addressLabel' => 'Adres przekaźnika',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Subskrybuj',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Styl subskrypcji',
        'options' => [
            'public' => 'Obserwuj publiczny strumień (czego oczekuje większość przekaźników)',
            'actor' => 'Obserwuj aktora samego przekaźnika',
        ],
        'retryNotice' => 'Jeśli przekaźnik nigdy nie zaakceptuje subskrypcji, wycofaj ją i spróbuj innego stylu - niektóre oprogramowanie przekaźników rozpoznaje tylko jeden z nich.',
    ],

    'SiteCounters' => [
        'members' => 'Użytkownicy: {count} ({joined} dołączyło w ciągu ostatnich {days} dni)',
        'activeMembers' => 'Użytkownicy aktywni tu w ciągu ostatnich {days} dni: {count} ({posted} z nich opublikowało posty)',
        'posts' => 'Posty napisane tutaj: {count} ({recent} w ciągu ostatnich {days} dni)',
        'deliveries' => 'Dostawy federacyjne w ciągu ostatnich {days} dni: {delivered} dostarczono, {undeliverable} porzucono',
        'queued' => 'Oczekujące na wysłanie: {count} ({failing} już odrzucono co najmniej raz)',
        'pendingReads' => 'Posty oczekujące na odczytanie z innych serwerów: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Uruchom zestaw testów witryny i zobacz wyniki. Zajmuje to kilka sekund, dlatego otwiera się na osobnej stronie.',
        'runLabel' => 'Uruchom testy',
    ],

    'HelpArticle' => [
        'backLabel' => 'Powrót do całej pomocy',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'Obecnie brak sugestii - sugestie pochodzą od znajomych twoich znajomych. Wyszukaj powyżej, aby znaleźć kogoś po imieniu.',
        'noMatches' => 'Nikt tutaj do tego nie pasuje.',
    ],
];
