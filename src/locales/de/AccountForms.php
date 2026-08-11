<?php

declare(strict_types=1);

/**
 * German for the account/auth forms. See src/locales/en/AccountForms.php for
 * the source and the shape each entry is built to.
 */

return [
    'LoginForm' => [
        'legend' => 'Anmelden',
        'identifier' => 'Benutzername oder E-Mail',
        'password' => 'Passwort',
        'rememberMe' => 'Angemeldet bleiben',
        'submit' => 'Anmelden',
    ],

    'SignupForm' => [
        'legend' => 'Konto erstellen',
        'usernameLabel' => 'Benutzername',
        'usernamePlaceholder' => 'Kleinbuchstaben, Zahlen und _',
        'emailLabel' => 'E-Mail',
        'emailPlaceholder' => 'Gültige E-Mail-Adresse',
        'displayName' => 'Anzeigename (optional)',
        'bioLabel' => 'Bio (optional)',
        'bioPlaceholder' => 'Eine kurze Bio - #Hashtags, @Erwähnungen und Links werden klickbar',
        'passwordLabel' => 'Passwort',
        'passwordPlaceholder' => 'Passwort: mindestens 8 Zeichen',
        'rememberMe' => 'Angemeldet bleiben',
        'submit' => 'Registrieren',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Passwort ändern',
        'currentPassword' => 'Aktuelles Passwort',
        'newPasswordLabel' => 'Neues Passwort',
        'newPasswordPlaceholder' => 'Mindestens 8 Zeichen',
        'confirmPassword' => 'Neues Passwort bestätigen',
        'submit' => 'Passwort ändern',
    ],

    'EmailChangeForm' => [
        'legend' => 'E-Mail-Adresse ändern',
        'newEmail' => 'Neue E-Mail-Adresse',
        'currentPassword' => 'Aktuelles Passwort',
        'notice' => 'Du musst die neue Adresse bestätigen, bevor du die Seite weiter nutzen kannst.',
        'submit' => 'E-Mail ändern',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Konto löschen',
        'warning' => 'Dies löscht dein Konto, deine Beiträge und Nachrichten dauerhaft. Das kann nicht rückgängig gemacht werden.',
        'currentPassword' => 'Aktuelles Passwort',
        'submit' => 'Konto löschen',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Zu einem anderen Server umziehen',
        'movedNotice' => 'Dieses Konto ist zu {destination} umgezogen. Deine Follower wurden gebeten, dir dorthin zu folgen.',
        'explanation' => 'Deine Follower werden gebeten, dem neuen Konto zu folgen. Deine Beiträge bleiben hier - Objektadressen gehören dem Server, der sie erstellt hat, und können nicht mitgenommen werden.',
        'addressNotice' => 'Das Konto, zu dem du umziehst, muss dieses hier zuerst unter "auch bekannt als" auflisten. Deine Adresse hier lautet {address}.',
        'movedToLabel' => 'Umziehen zu',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Auch bekannt als',
        'aliasesExplanation' => 'Konten anderswo, die ebenfalls du sind. Eines hier einzutragen erlaubt diesem Konto, zu diesem hier umzuziehen - es ist die Erlaubnis, nicht der Umzug selbst. Eine Adresse pro Zeile.',
        'aliasesLabel' => 'Deine anderen Konten',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Speichern',
    ],

    'TwoFactorForm' => [
        'legend' => 'Gib deinen Bestätigungscode ein',
        'explanation' => 'Wir haben dir einen Bestätigungscode per E-Mail geschickt. Gib ihn unten ein, um die Anmeldung abzuschließen.',
        'code' => 'Bestätigungscode',
        'submit' => 'Bestätigen',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'Zwei-Faktor-Authentifizierung ist aktiviert', 'off' => 'Zwei-Faktor-Authentifizierung ist deaktiviert'],
        'explanation' => [
            'on' => 'Beim Anmelden schicken wir dir einen Bestätigungscode per E-Mail, den du eingeben musst, um die Anmeldung abzuschließen.',
            'off' => 'Füge der Anmeldung einen zweiten Schritt hinzu: Wir schicken dir einen Bestätigungscode per E-Mail, den du eingeben musst, damit dein Passwort allein nicht zum Anmelden reicht.',
        ],
        'currentPassword' => 'Aktuelles Passwort',
        'submit' => ['on' => 'Zwei-Faktor-Authentifizierung deaktivieren', 'off' => 'Zwei-Faktor-Authentifizierung aktivieren'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Bitte sieh in deinem Postfach nach und klicke auf den Bestätigungslink, den wir dir geschickt haben, um deine E-Mail-Adresse zu bestätigen. Falls du ihn nicht siehst, schau in deinem Spam-Ordner nach.',
    ],

    'VerificationResendButton' => [
        'label' => 'Bestätigungs-E-Mail erneut senden',
    ],
];
