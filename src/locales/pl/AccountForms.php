<?php

declare(strict_types=1);

/**
 * Polish for the account/auth forms. See src/locales/en/AccountForms.php for
 * what each of these is.
 */

return [
    'LoginForm' => [
        'legend' => 'Zaloguj się',
        'identifier' => 'Nazwa użytkownika lub e-mail',
        'password' => 'Hasło',
        'rememberMe' => 'Zapamiętaj mnie',
        'submit' => 'Zaloguj się',
    ],

    'SignupForm' => [
        'legend' => 'Utwórz konto',
        'usernameLabel' => 'Nazwa użytkownika',
        'usernamePlaceholder' => 'Małe litery, cyfry i _',
        'emailLabel' => 'E-mail',
        'emailPlaceholder' => 'Prawidłowy adres e-mail',
        'displayName' => 'Wyświetlana nazwa (opcjonalnie)',
        'bioLabel' => 'Bio (opcjonalnie)',
        'bioPlaceholder' => 'Krótkie bio - #hashtagi, @wzmianki i linki stają się klikalne',
        'passwordLabel' => 'Hasło',
        'passwordPlaceholder' => 'Hasło: co najmniej 8 znaków',
        'rememberMe' => 'Zapamiętaj mnie',
        'submit' => 'Zarejestruj się',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Zmień hasło',
        'currentPassword' => 'Obecne hasło',
        'newPasswordLabel' => 'Nowe hasło',
        'newPasswordPlaceholder' => 'Co najmniej 8 znaków',
        'confirmPassword' => 'Potwierdź nowe hasło',
        'submit' => 'Zmień hasło',
    ],

    'EmailChangeForm' => [
        'legend' => 'Zmień adres e-mail',
        'newEmail' => 'Nowy adres e-mail',
        'currentPassword' => 'Obecne hasło',
        'notice' => 'Nowy adres trzeba będzie zweryfikować, zanim będzie można dalej korzystać z witryny.',
        'submit' => 'Zmień e-mail',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Usuń konto',
        'warning' => 'To nieodwracalnie usuwa twoje konto, posty i wiadomości. Tej operacji nie można cofnąć.',
        'currentPassword' => 'Obecne hasło',
        'submit' => 'Usuń konto',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Przenieś się na inny serwer',
        'movedNotice' => 'To konto zostało przeniesione na {destination}. Twoi obserwujący zostali poproszeni, aby obserwować cię tam.',
        'explanation' => 'Twoi obserwujący są proszeni, aby obserwować cię na nowym koncie. Twoje posty zostają tutaj - adresy obiektów należą do serwera, który je utworzył, więc nie da się ich zabrać ze sobą.',
        'addressNotice' => 'Konto, na które się przenosisz, musi najpierw wymienić to konto pod "znane również jako". Twój adres tutaj to {address}.',
        'movedToLabel' => 'Przenieś na',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Znane również jako',
        'aliasesExplanation' => 'Konta gdzie indziej, które również należą do ciebie. Wpisanie konta tutaj pozwala mu przenieść się na to konto - to jest zgoda, a nie samo przeniesienie. Jeden adres w wierszu.',
        'aliasesLabel' => 'Twoje inne konta',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Zapisz',
    ],

    'TwoFactorForm' => [
        'legend' => 'Wpisz kod weryfikacyjny',
        'explanation' => 'Wysłaliśmy ci e-mailem kod weryfikacyjny. Wpisz go poniżej, aby dokończyć logowanie.',
        'code' => 'Kod weryfikacyjny',
        'submit' => 'Zweryfikuj',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'Uwierzytelnianie dwuskładnikowe jest włączone', 'off' => 'Uwierzytelnianie dwuskładnikowe jest wyłączone'],
        'explanation' => [
            'on' => 'Podczas logowania wyślemy ci e-mailem kod weryfikacyjny, który trzeba będzie wpisać, aby dokończyć logowanie.',
            'off' => 'Dodaj drugi krok logowania: wyślemy ci e-mailem kod weryfikacyjny, który trzeba będzie wpisać, aby samo hasło nie wystarczało do zalogowania.',
        ],
        'currentPassword' => 'Obecne hasło',
        'submit' => ['on' => 'Wyłącz uwierzytelnianie dwuskładnikowe', 'off' => 'Włącz uwierzytelnianie dwuskładnikowe'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Sprawdź skrzynkę odbiorczą i kliknij link weryfikacyjny, który wysłaliśmy, aby potwierdzić adres e-mail. Jeśli go nie widzisz, sprawdź folder ze spamem.',
    ],

    'VerificationResendButton' => [
        'label' => 'Wyślij ponownie e-mail weryfikacyjny',
    ],
];
