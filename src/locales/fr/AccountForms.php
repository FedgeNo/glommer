<?php

declare(strict_types=1);

/**
 * French for the account/auth forms. See src/locales/en/AccountForms.php for
 * what each of these is.
 */

return [
    'LoginForm' => [
        'legend' => 'Se connecter',
        'identifier' => 'Nom d\'utilisateur ou e-mail',
        'password' => 'Mot de passe',
        'rememberMe' => 'Se souvenir de moi',
        'submit' => 'Se connecter',
    ],

    'SignupForm' => [
        'legend' => 'Créer un compte',
        'usernameLabel' => 'Nom d\'utilisateur',
        'usernamePlaceholder' => 'Lettres minuscules, chiffres et _',
        'emailLabel' => 'E-mail',
        'emailPlaceholder' => 'Adresse e-mail valide',
        'displayName' => 'Nom affiché (optionnel)',
        'bioLabel' => 'Bio (optionnel)',
        'bioPlaceholder' => 'Une courte bio - les #hashtags, les @mentions et les liens deviennent cliquables',
        'passwordLabel' => 'Mot de passe',
        'passwordPlaceholder' => 'Mot de passe : au moins 8 caractères',
        'rememberMe' => 'Se souvenir de moi',
        'submit' => 'S\'inscrire',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Modifier votre mot de passe',
        'currentPassword' => 'Mot de passe actuel',
        'newPasswordLabel' => 'Nouveau mot de passe',
        'newPasswordPlaceholder' => 'Au moins 8 caractères',
        'confirmPassword' => 'Confirmer le nouveau mot de passe',
        'submit' => 'Modifier le mot de passe',
    ],

    'EmailChangeForm' => [
        'legend' => 'Modifier votre adresse e-mail',
        'newEmail' => 'Nouvelle adresse e-mail',
        'currentPassword' => 'Mot de passe actuel',
        'notice' => 'Vous devrez vérifier la nouvelle adresse avant de pouvoir continuer à utiliser le site.',
        'submit' => 'Modifier l\'e-mail',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Supprimer votre compte',
        'warning' => 'Cette action supprime définitivement votre compte, vos publications et vos messages. Elle est irréversible.',
        'currentPassword' => 'Mot de passe actuel',
        'submit' => 'Supprimer le compte',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Déménager vers un autre serveur',
        'movedNotice' => 'Ce compte a déménagé vers {destination}. Vos abonnés ont été invités à vous suivre là-bas.',
        'explanation' => 'Vos abonnés sont invités à vous suivre depuis le nouveau compte. Vos publications restent ici - les adresses des objets appartiennent au serveur qui les a créées, elles ne peuvent donc pas être emportées.',
        'addressNotice' => 'Le compte vers lequel vous déménagez doit d\'abord lister celui-ci sous "Également connu sous le nom de". Votre adresse ici est {address}.',
        'movedToLabel' => 'Déménager vers',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Également connu sous le nom de',
        'aliasesExplanation' => 'Des comptes ailleurs qui sont aussi vous. En lister un ici permet à ce compte de déménager vers celui-ci - c\'est la permission, pas le déménagement lui-même. Une adresse par ligne.',
        'aliasesLabel' => 'Vos autres comptes',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Enregistrer',
    ],

    'TwoFactorForm' => [
        'legend' => 'Saisissez votre code de vérification',
        'explanation' => 'Nous vous avons envoyé un code de vérification par e-mail. Saisissez-le ci-dessous pour terminer la connexion.',
        'code' => 'Code de vérification',
        'submit' => 'Vérifier',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'L\'authentification à deux facteurs est activée', 'off' => 'L\'authentification à deux facteurs est désactivée'],
        'explanation' => [
            'on' => 'Lorsque vous vous connectez, nous vous envoyons par e-mail un code de vérification que vous devez saisir pour terminer la connexion.',
            'off' => 'Ajoutez une seconde étape à la connexion : nous vous enverrons par e-mail un code de vérification à saisir, afin que votre mot de passe seul ne suffise pas pour accéder à votre compte.',
        ],
        'currentPassword' => 'Mot de passe actuel',
        'submit' => ['on' => 'Désactiver l\'authentification à deux facteurs', 'off' => 'Activer l\'authentification à deux facteurs'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Veuillez consulter votre boîte de réception et cliquer sur le lien de vérification que nous vous avons envoyé pour confirmer votre adresse e-mail. Si vous ne le voyez pas, vérifiez votre dossier indésirables/spam.',
    ],

    'VerificationResendButton' => [
        'label' => 'Renvoyer l\'e-mail de vérification',
    ],
];
