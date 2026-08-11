<?php

declare(strict_types=1);

/**
 * Italian for the account/auth forms. See src/locales/en/AccountForms.php for
 * what this fragment covers.
 */

return [
    'LoginForm' => [
        'legend' => 'Accedi',
        'identifier' => 'Nome utente o email',
        'password' => 'Password',
        'rememberMe' => 'Ricordami',
        'submit' => 'Accedi',
    ],

    'SignupForm' => [
        'legend' => 'Crea un account',
        'usernameLabel' => 'Nome utente',
        'usernamePlaceholder' => 'Lettere minuscole, numeri e _',
        'emailLabel' => 'Email',
        'emailPlaceholder' => 'Un indirizzo email valido',
        'displayName' => 'Nome visualizzato (facoltativo)',
        'bioLabel' => 'Biografia (facoltativo)',
        'bioPlaceholder' => 'Una breve biografia - #hashtag, @menzioni e link diventano cliccabili',
        'passwordLabel' => 'Password',
        'passwordPlaceholder' => 'Password: almeno 8 caratteri',
        'rememberMe' => 'Ricordami',
        'submit' => 'Registrati',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Cambia la tua password',
        'currentPassword' => 'Password attuale',
        'newPasswordLabel' => 'Nuova password',
        'newPasswordPlaceholder' => 'Almeno 8 caratteri',
        'confirmPassword' => 'Conferma la nuova password',
        'submit' => 'Cambia password',
    ],

    'EmailChangeForm' => [
        'legend' => 'Cambia il tuo indirizzo email',
        'newEmail' => 'Nuovo indirizzo email',
        'currentPassword' => 'Password attuale',
        'notice' => 'Dovrai verificare il nuovo indirizzo prima di poter continuare a usare il sito.',
        'submit' => 'Cambia email',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Elimina il tuo account',
        'warning' => 'Questa operazione elimina definitivamente il tuo account, i tuoi post e i tuoi messaggi. Non si può annullare.',
        'currentPassword' => 'Password attuale',
        'submit' => 'Elimina account',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Trasferisciti a un altro server',
        'movedNotice' => 'Questo account si è trasferito a {destination}. Ai tuoi follower è stato chiesto di seguirti lì.',
        'explanation' => 'Ai tuoi follower viene chiesto di seguirti sul nuovo account. I tuoi post restano qui - gli indirizzi degli oggetti appartengono al server che li ha creati, quindi non possono essere portati con te.',
        'addressNotice' => 'L\'account a cui ti stai trasferendo deve prima indicare questo account come "anche conosciuto come". Il tuo indirizzo qui è {address}.',
        'movedToLabel' => 'Trasferisciti a',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Anche conosciuto come',
        'aliasesExplanation' => 'Account altrove che sei sempre tu. Elencarne uno qui permette a quell\'account di trasferirsi su questo - è il permesso, non il trasferimento in sé. Un indirizzo per riga.',
        'aliasesLabel' => 'I tuoi altri account',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Salva',
    ],

    'TwoFactorForm' => [
        'legend' => 'Inserisci il tuo codice di verifica',
        'explanation' => 'Ti abbiamo inviato via email un codice di verifica. Inseriscilo qui sotto per completare l\'accesso.',
        'code' => 'Codice di verifica',
        'submit' => 'Verifica',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'L\'autenticazione a due fattori è attiva', 'off' => 'L\'autenticazione a due fattori non è attiva'],
        'explanation' => [
            'on' => 'Quando accedi, ti invieremo via email un codice di verifica che dovrai inserire per completare l\'accesso.',
            'off' => 'Aggiungi un secondo passaggio all\'accesso: ti invieremo via email un codice di verifica che dovrai inserire, così la tua password da sola non basterà per entrare.',
        ],
        'currentPassword' => 'Password attuale',
        'submit' => ['on' => 'Disattiva l\'autenticazione a due fattori', 'off' => 'Attiva l\'autenticazione a due fattori'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Controlla la tua casella di posta e fai clic sul link di verifica che ti abbiamo inviato per confermare il tuo indirizzo email. Se non lo vedi, controlla la cartella spam.',
    ],

    'VerificationResendButton' => [
        'label' => 'Invia di nuovo l\'email di verifica',
    ],
];
