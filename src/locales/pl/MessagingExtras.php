<?php

declare(strict_types=1);

/**
 * Polish for the notification and direct-messaging classes not already
 * covered by MessagingAndStatus.php. See src/locales/en/MessagingExtras.php
 * for what each of these is.
 *
 * {name} arrives as a bare display name or handle the code cannot decline, so
 * every notification below keeps it in the nominative and leads with it as
 * the subject rather than bending it into another case. Polish still marks
 * the verb for the actor's gender and there is no data here to choose one, so
 * these default to the masculine form the language itself falls back to when
 * a subject's gender is not known - the same default "ktoś zrobił" takes.
 */

return [
    'Notification' => [
        'postReady' => 'Twoje media zostały przetworzone i są już widoczne',
        'scheduledPostLive' => 'Twój zaplanowany post jest już opublikowany',
        'uploadPartlyFailed' => 'Twój post jest opublikowany, ale co najmniej jednego z jego plików nie udało się przetworzyć',
        'uploadFailed' => 'Nie udało się przetworzyć jednego z twoich przesłanych plików, więc nie został opublikowany',
        'mailerFailed' => 'Dostarczenie e-maila nie powiodło się - system pocztowy może nie działać. Sprawdź konfigurację poczty.',
        'mailFromNotConfigured' => 'Nie skonfigurowano adresu nadawcy poczty, więc e-maile nie mogą być wysyłane. Ustaw go w Ustawieniach administracyjnych (sekcja Poczta wychodząca) lub przez bin/install.php.',
        'systemError' => 'Wystąpił błąd serwera. Sprawdź dziennik błędów, aby poznać szczegóły.',
        'passwordRemovedGoogle' => 'Twoje hasło zostało usunięte podczas logowania przez Google. Użyj opcji "Nie pamiętam hasła", jeśli chcesz ustawić nowe.',
        'like' => '{name} polubił twój post',
        'repost' => '{name} repostował twój post',
        'reply' => '{name} odpowiedział na twój post',
        'friendRequest' => '{name} wysłał ci zaproszenie do znajomych',
        'friendAccepted' => '{name} zaakceptował twoje zaproszenie do znajomych',
        'message' => '{name} wysłał ci wiadomość',
        'mention' => '{name} wspomniał o tobie w poście',
        'follow' => '{name} zaczął cię obserwować z innego serwera',
        'default' => '{name} coś zrobił',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Brak powiadomień.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Powiadomienia',
        'unseen' => 'Nieprzeczytane powiadomienia',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Wyślij testowe powiadomienie do siebie (administratora). Powinno pojawić się natychmiast jako powiadomienie toast oraz na liście powiadomień.',
        'button' => 'Wyślij testowe powiadomienie',
        'sending' => 'Wysyłanie…',
        'sent' => 'Wysłano!',
        'failed' => 'Niepowodzenie',
    ],

    'MessageDot' => [
        'label' => 'Nieprzeczytane wiadomości',
    ],

    'NavAlertDot' => [
        'label' => 'Coś nowego w menu',
    ],

    'Message' => [
        'encrypted' => 'Zaszyfrowana wiadomość',
        'decryptionFailed' => 'Ta wiadomość została zaszyfrowana kluczami, które już nie istnieją.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Wiadomość',
        'bodyPlaceholder' => 'Napisz wiadomość',
        'send' => 'Wyślij',
    ],

    'MessageList' => [
        'emptyNotice' => 'Brak wiadomości.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Odczytajcie sobie ten kod nawzajem w inny sposób - na głos, osobiście, podczas rozmowy. Jeśli zgadza się po obu stronach, nikt nie jest między wami.',
        'changed' => 'Ten kod zmienił się od czasu ostatniego sprawdzenia. Dzieje się tak, gdy jedna ze stron resetuje swoje klucze szyfrowania - ale wygląda to tak samo, jak wyglądałoby czyjeś podsłuchiwanie tej rozmowy. Sprawdźcie nowy kod z drugą osobą, zanim mu zaufacie.',
        'verified' => 'Ten kod został sprawdzony.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Oznacz jako zweryfikowany',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Hasło szyfrujące',
        'passphrasePlaceholder' => 'Hasło szyfrujące do odblokowania tej rozmowy',
        'submit' => 'Odblokuj',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Ostatnia wiadomość ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Wrażliwe media',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Domyślnie pokazuj wrażliwe media',
    ],
];
