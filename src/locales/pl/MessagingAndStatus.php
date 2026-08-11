<?php

declare(strict_types=1);

/**
 * Polish for the messaging and admin-status classes. See
 * src/locales/en/MessagingAndStatus.php for what each of these is.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Nie pamiętasz hasła szyfrującego? Zresetowanie tworzy nowe klucze pod nowym hasłem - ale wiadomości zaszyfrowane starymi kluczami nie będą już mogły zostać odczytane przez nikogo.',
        'requirements' => 'Co najmniej 12 znaków, i nie hasło do twojego konta - to hasło jest wysyłane na ten serwer, a hasło szyfrujące nigdy nie powinno tam trafić.',
        'passphraseLabel' => 'Hasło szyfrujące',
        'resetPassphraseLabel' => 'Nowe hasło szyfrujące',
        'confirmLabel' => 'Potwierdź hasło szyfrujące',
        'accountPasswordLabel' => 'Hasło do konta',
        'submitLabel' => 'Włącz szyfrowane wiadomości',
        'resetSubmitLabel' => 'Zresetuj klucze szyfrowania',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'Wiadomości szyfrowane od końca do końca są blokowane i odblokowywane w twojej przeglądarce: ten serwer je przekazuje i przechowuje, nie mogąc ich odczytać. Twój klucz jest chroniony hasłem szyfrującym, a to samo hasło odblokowuje twoje wiadomości z dowolnej przeglądarki. Rozmowy są szyfrowane, gdy obie strony to włączą; wiadomości do osób na innych serwerach pozostają nieszyfrowane, ponieważ federacja nie ma innego sposobu na ich przekazanie.',
        'noRecovery' => 'Nie ma sposobu na odzyskanie utraconego hasła szyfrującego - nawet dla administratora. Utrata hasła oznacza utratę zaszyfrowanych wiadomości.',
        'enabledStatus' => 'Szyfrowane wiadomości są włączone.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Szyfrowane',
            'explanation' => 'Wiadomości w tej rozmowie są szyfrowane od końca do końca: są odblokowywane hasłem szyfrującym i odczytywane w waszych przeglądarkach, a to, co przechowuje ten serwer, to zaszyfrowany tekst. Sprawdźcie razem z drugą osobą kod bezpieczeństwa na dole wątku, aby mieć pewność, że nikt nie jest pomiędzy wami. Wiadomości wysłane przed włączeniem szyfrowania pozostają czytelne tak jak wcześniej.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Nieszyfrowane',
            'explanation' => 'Wiadomości tutaj będą szyfrowane od końca do końca, gdy {handle} włączy szyfrowane wiadomości w swoich ustawieniach.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Nieszyfrowane',
            'explanation' => 'Wiadomości tutaj nie są szyfrowane od końca do końca. Włącz szyfrowane wiadomości w Ustawieniach, aby zabezpieczyć tę rozmowę.',
        ],
        'federated' => [
            'label' => '🔓 Nieszyfrowane',
            'explanation' => '{handle} jest na innym serwerze. Wiadomości w tej rozmowie są przechowywane zarówno na tamtym serwerze, jak i na tym, a jego administrator może je odczytać - protokół między serwerami nie ma sposobu na ich zaszyfrowanie. Wszystko, co wrażliwe, zachowuj na rozmowy w obrębie tej witryny.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Obserwuj konta z Fediwersum',
        'notice' => 'Wklej jeden lub więcej identyfikatorów, np. @user@example.social - zadziała dowolny separator między nimi.',
        'handlesLabel' => 'Identyfikatory z Fediwersum do obserwowania',
        'submit' => 'Obserwuj',
        'statusPending' => 'oczekujące',
        'statusAccepted' => 'zaakceptowane',
    ],

    'ServerBlockForm' => [
        'legend' => 'Zablokuj serwer',
        'description' => 'Odrzuca wszystko z tego serwera i wszystkiego, co pod nim działa: brak dostaw przychodzących, brak wychodzących, a istniejące obserwacje w obie strony zostają zerwane.',
        'serverLabel' => 'Serwer',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Powód',
        'reasonPlaceholder' => 'Dlaczego ten serwer jest blokowany',
        'submit' => 'Zablokuj serwer',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Uruchamia te elementy konfiguracji połączenia, które można sprawdzić z jednej przeglądarki. Wszystko aż do faktycznego połączenia peer-to-peer można tu przetestować; połączenie z drugą osobą wymaga udziału tej osoby.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Uruchom sprawdzanie',
    ],

    'WebSocketStatus' => [
        'ok' => 'Serwer WebSocket: działa',
        'failed' => 'Serwer WebSocket: {detail}',
        'clientTesting' => 'Połączenie przeglądarki: testowanie…',
        'clientConnecting' => 'Połączenie przeglądarki: łączenie…',
        'clientConnected' => 'Połączenie przeglądarki: połączono',
        'clientDisconnecting' => 'Połączenie przeglądarki: rozłączanie…',
        'clientNotConnected' => 'Połączenie przeglądarki: brak połączenia',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Usługa przetwarzania: działa',
        'stopped' => 'Usługa przetwarzania: nie działa - oczekujące pliki nigdy nie zostaną przetworzone, dopóki usługa nie zostanie zrestartowana',
        'unknown' => 'Usługa przetwarzania: nieznany stan - albo systemctl jest niedostępny na tym hoście, albo SELinux odrzuca zapytanie serwera WWW o własny status (uruchom bin/install.php jako root, aby to naprawić)',
        'queue' => 'Kolejka: {staging} w przygotowaniu, {pending} oczekujących, {processing} przetwarzanych',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Harmonogram tendencji: działa',
        'stopped' => 'Harmonogram tendencji: nie działa - popularne tematy będą odświeżane tylko przez samonaprawę przy odczycie (Trending::current()), a nie według harmonogramu. Uruchom bin/install.php jako root, aby go skonfigurować.',
        'unknown' => 'Harmonogram tendencji: nieznany stan - albo systemctl jest niedostępny na tym hoście, albo SELinux odrzuca zapytanie serwera WWW o własny status (uruchom bin/install.php jako root, aby to naprawić)',
    ],
];
