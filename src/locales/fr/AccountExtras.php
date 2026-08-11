<?php

declare(strict_types=1);

/**
 * French for the smaller account-related classes. See src/locales/en/AccountExtras.php
 * for what each of these is.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Site',
        'siteURLLabel' => 'URL du site',
        'siteTitleLabel' => 'Titre du site',
        'mailFromAddressLabel' => 'Adresse d\'expédition des e-mails',
        'serverNameConfirmedLabel' => 'J\'ai défini "ServerName {host}" et "UseCanonicalName On" dans la configuration de mon serveur web (vérifié seulement si le test automatique en direct ne peut pas aboutir - voir la section HTTPS du README.md)',
        'databaseLegend' => 'Base de données',
        'databaseHostLabel' => 'Hôte de la base de données',
        'databasePortLabel' => 'Port de la base de données',
        'databaseNameLabel' => 'Nom de la base de données',
        'databaseAdminUsernameLabel' => 'Nom d\'utilisateur admin de la base de données',
        'databaseAdminPasswordLabel' => 'Mot de passe admin de la base de données',
        'webSocketTLSLegend' => 'TLS WebSocket (optionnel)',
        'certificatePathLabel' => 'Chemin du certificat',
        'certificatePathPlaceholder' => 'Laissez vide pour générer automatiquement via mkcert',
        'keyPathLabel' => 'Chemin de la clé',
        'keyPathPlaceholder' => 'Laissez vide pour générer automatiquement via mkcert',
        'botProtectionLegend' => 'Protection anti-robots (optionnel)',
        'turnstileSiteKeyLabel' => 'Clé de site Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Laissez vide pour ignorer',
        'turnstileSecretKeyLabel' => 'Clé secrète Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Laissez vide pour ignorer',
        'submit' => 'Configurer',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Phrase secrète actuelle',
        'newPassphraseLabel' => 'Nouvelle phrase secrète',
        'confirmNewPassphraseLabel' => 'Confirmer la nouvelle phrase secrète',
        'accountPasswordLabel' => 'Mot de passe du compte',
        'submit' => 'Modifier la phrase secrète',
    ],

    'PasswordResetForm' => [
        'legend' => 'Choisissez un nouveau mot de passe',
        'newPasswordLabel' => 'Nouveau mot de passe',
        'newPasswordPlaceholder' => 'Au moins 8 caractères',
        'confirmPasswordLabel' => 'Confirmer le nouveau mot de passe',
        'submit' => 'Réinitialiser le mot de passe',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Réinitialisez votre mot de passe',
        'emailLabel' => 'E-mail',
        'submit' => 'Envoyer le lien de réinitialisation',
    ],

    'EmailRevertForm' => [
        'submit' => 'Annuler le changement d\'e-mail',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Vérifier l\'adresse e-mail',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => 'Les réactiver',
    ],

    'EmailDigestSetting' => [
        'label' => 'M\'envoyer par e-mail ce que j\'ai manqué après une longue absence',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Appareil inconnu',
        'browserOnOS' => '{browser} sur {os}',
        'thisDevice' => ' (cet appareil)',
        'lastUsed' => ['before' => 'Dernière utilisation ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'Mettez fin à toutes les sessions actives et oubliez tous les appareils mémorisés. Vous serez déconnecté de tous les navigateurs, y compris celui-ci.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Se déconnecter partout',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Vérifier avec Google pour supprimer',
    ],

    'GoogleSignInButton' => [
        'label' => 'Continuer avec Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Modifier le profil',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Recevez des notifications sur cet appareil même lorsque le site n\'est pas ouvert. C\'est un choix propre à chaque navigateur - activez-le partout où vous voulez être joignable.',
        'label' => [
            'off' => 'Activer sur cet appareil',
            'on' => 'Désactiver sur cet appareil',
        ],
        'unsupported' => 'Les notifications push ne sont pas prises en charge par ce navigateur',
    ],
];
