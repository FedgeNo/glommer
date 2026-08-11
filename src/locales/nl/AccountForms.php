<?php

declare(strict_types=1);

/**
 * Dutch for the account/auth forms. See src/locales/en/AccountForms.php for
 * the source and the shape each entry is built to.
 */

return [
    'LoginForm' => [
        'legend' => 'Inloggen',
        'identifier' => 'Gebruikersnaam of e-mail',
        'password' => 'Wachtwoord',
        'rememberMe' => 'Onthoud mij',
        'submit' => 'Inloggen',
    ],

    'SignupForm' => [
        'legend' => 'Account aanmaken',
        'usernameLabel' => 'Gebruikersnaam',
        'usernamePlaceholder' => 'Kleine letters, cijfers en _',
        'emailLabel' => 'E-mail',
        'emailPlaceholder' => 'Geldig e-mailadres',
        'displayName' => 'Weergavenaam (optioneel)',
        'bioLabel' => 'Bio (optioneel)',
        'bioPlaceholder' => 'Een korte bio - #hashtags, @vermeldingen en links worden klikbaar',
        'passwordLabel' => 'Wachtwoord',
        'passwordPlaceholder' => 'Wachtwoord: minstens 8 tekens',
        'rememberMe' => 'Onthoud mij',
        'submit' => 'Registreren',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Wachtwoord wijzigen',
        'currentPassword' => 'Huidig wachtwoord',
        'newPasswordLabel' => 'Nieuw wachtwoord',
        'newPasswordPlaceholder' => 'Minstens 8 tekens',
        'confirmPassword' => 'Bevestig nieuw wachtwoord',
        'submit' => 'Wachtwoord wijzigen',
    ],

    'EmailChangeForm' => [
        'legend' => 'E-mailadres wijzigen',
        'newEmail' => 'Nieuw e-mailadres',
        'currentPassword' => 'Huidig wachtwoord',
        'notice' => 'Je moet het nieuwe adres verifiëren voordat je de site kunt blijven gebruiken.',
        'submit' => 'E-mail wijzigen',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Account verwijderen',
        'warning' => 'Dit verwijdert je account, posts en berichten definitief. Dit kan niet ongedaan worden gemaakt.',
        'currentPassword' => 'Huidig wachtwoord',
        'submit' => 'Account verwijderen',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Verhuizen naar een andere server',
        'movedNotice' => 'Dit account is verhuisd naar {destination}. Je volgers is gevraagd je daar te volgen.',
        'explanation' => 'Je volgers wordt gevraagd je op het nieuwe account te volgen. Je posts blijven hier - objectadressen horen bij de server die ze heeft gemaakt, dus ze kunnen niet worden meegenomen.',
        'addressNotice' => 'Het account waar je naartoe verhuist, moet dit account eerst vermelden onder "ook bekend als". Je adres hier is {address}.',
        'movedToLabel' => 'Verhuizen naar',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Ook bekend als',
        'aliasesExplanation' => 'Accounts elders die ook van jou zijn. Als je er hier een vermeldt, mag dat account naar dit account verhuizen - het is de toestemming, niet de verhuizing zelf. Eén adres per regel.',
        'aliasesLabel' => 'Je andere accounts',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Opslaan',
    ],

    'TwoFactorForm' => [
        'legend' => 'Voer je verificatiecode in',
        'explanation' => 'We hebben je een verificatiecode gemaild. Voer deze hieronder in om het inloggen te voltooien.',
        'code' => 'Verificatiecode',
        'submit' => 'Verifiëren',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'Tweestapsverificatie staat aan', 'off' => 'Tweestapsverificatie staat uit'],
        'explanation' => [
            'on' => 'Als je inlogt, mailen we je een verificatiecode die je moet invoeren om het inloggen te voltooien.',
            'off' => 'Voeg een tweede stap toe aan het inloggen: we mailen je een verificatiecode die je moet invoeren, zodat je wachtwoord alleen niet genoeg is om binnen te komen.',
        ],
        'currentPassword' => 'Huidig wachtwoord',
        'submit' => ['on' => 'Tweestapsverificatie uitschakelen', 'off' => 'Tweestapsverificatie inschakelen'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Controleer je inbox en klik op de verificatielink die we hebben gestuurd om je e-mailadres te bevestigen. Zie je die niet, controleer dan je ongewenste e-mail/spammap.',
    ],

    'VerificationResendButton' => [
        'label' => 'Verificatie-e-mail opnieuw versturen',
    ],
];
