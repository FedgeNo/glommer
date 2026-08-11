<?php

declare(strict_types=1);

/**
 * French for the admin Site Settings forms. See src/locales/en/AdminSettings.php
 * for what each of these is.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Courrier sortant',
        'fromAddressLabel' => 'Adresse d\'expédition',
        'fromAddressPlaceholder' => 'Aucun e-mail ne peut être envoyé tant que ceci n\'est pas défini',
        'fromNameLabel' => 'Nom d\'expédition',
        'hostLabel' => 'Hôte SMTP',
        'portLabel' => 'Port SMTP',
        'usernameLabel' => 'Nom d\'utilisateur SMTP',
        'passwordLabel' => 'Mot de passe SMTP',
        'passwordPlaceholder' => [
            'set' => 'Un mot de passe est défini - laissez vide pour le conserver',
            'unset' => 'Mot de passe SMTP',
        ],
        'encryptionLabel' => 'Chiffrement',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, généralement sur le port 587)',
            'ssl' => 'SSL (TLS implicite, généralement sur le port 465)',
            'none' => 'Aucun',
        ],
        'explainer' => 'Laissez l\'hôte SMTP vide pour envoyer via mail() de PHP à la place (non recommandé - voir la section du README consacrée à la délivrabilité). Aucun e-mail ne peut être envoyé tant qu\'une "adresse d\'expédition" n\'est pas définie.',
        'save' => 'Enregistrer',
    ],

    'MapSettingsForm' => [
        'legend' => 'Tuiles de carte',
        'notice' => 'Laissez vide pour utiliser OpenStreetMap. Pour utiliser un fournisseur à clé, collez son modèle d\'URL avec un {apiKey} littéral à l\'endroit où va la clé, ainsi que la clé et l\'attribution ci-dessous.',
        'urlLabel' => 'Modèle d\'URL des tuiles',
        'keyLabel' => 'Clé API',
        'keyPlaceholder' => 'La clé API de votre fournisseur de tuiles',
        'attributionLabel' => 'Attribution',
        'attributionPlaceholder' => '© OpenStreetMap contributors',
        'save' => 'Enregistrer',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Connexion avec Google',
        'clientIdLabel' => 'ID client',
        'clientIdPlaceholder' => 'ID client OAuth Google',
        'secretLabel' => 'Secret client',
        'secretPlaceholder' => [
            'set' => 'Le secret client est défini - laissez vide pour le conserver',
            'unset' => 'Secret client OAuth Google',
        ],
        'explainer' => 'Les deux sont nécessaires pour que "Continuer avec Google" apparaisse à l\'inscription et à la connexion. Dans votre client OAuth Google Cloud, définissez l\'URI de redirection autorisée sur {url} - videz l\'ID client pour désactiver cette fonctionnalité.',
        'save' => 'Enregistrer',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Clé de site',
        'turnstileSiteKeyPlaceholder' => 'Clé de site Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Clé secrète',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'La clé secrète est définie - laissez vide pour la conserver',
            'unset' => 'Clé secrète Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Les deux clés sont nécessaires pour que le CAPTCHA apparaisse à l\'inscription et à la connexion. Videz la clé de site pour le désactiver.',
        'recaptchaLegend' => 'Google reCAPTCHA (récupération après verrouillage du compte)',
        'recaptchaSiteKeyLabel' => 'Clé de site',
        'recaptchaSiteKeyPlaceholder' => 'Clé de site Google reCAPTCHA v2',
        'recaptchaSecretKeyLabel' => 'Clé secrète',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'La clé secrète est définie - laissez vide pour la conserver',
            'unset' => 'Clé secrète Google reCAPTCHA v2',
        ],
        'recaptchaExplainer' => 'Les deux clés sont nécessaires. Une fois définies, un compte qui atteint sa limite de tentatives de connexion peut y accéder de nouveau en réussissant ce test au lieu d\'attendre la fin du verrouillage ; si elles ne sont pas définies, le verrouillage est une attente incompressible. Utilisez reCAPTCHA v2 ("Je ne suis pas un robot"). Videz la clé de site pour le désactiver.',
        'save' => 'Enregistrer',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Utilisé par les fonctionnalités d\'IA du site (résumés de sujets tendance, etc.). Laissez le modèle vide pour utiliser le Free Models Router, qu\'OpenRouter choisit au hasard parmi ce qui est actuellement gratuit et qui ne peut jamais entraîner de coût.',
        'keyLabel' => 'Clé API',
        'keyPlaceholder' => [
            'set' => 'La clé API est définie - laissez vide pour la conserver',
            'unset' => 'Clé API OpenRouter',
        ],
        'clearKeyLabel' => 'Supprimer la clé API enregistrée (désactive les fonctionnalités d\'IA)',
        'modelLabel' => 'Modèle',
        'neverSpendLabel' => 'Ne jamais autoriser cela à dépenser de l\'argent (recommandé)',
        'explainer' => 'Avec cette protection activée, chaque requête plafonne son prix à zéro, elle échoue donc franchement plutôt que de se rabattre sur un modèle payant quand aucun fournisseur gratuit n\'est disponible. Supprimez la clé API enregistrée pour désactiver entièrement les fonctionnalités d\'IA.',
        'save' => 'Enregistrer',
    ],

    'AboutSettingsForm' => [
        'legend' => 'À propos',
        'description' => 'Texte brut - les lignes vides séparent les paragraphes. Le premier paragraphe sera utilisé comme description du site.',
        'save' => 'Enregistrer',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Conditions d\'utilisation',
        'description' => 'Texte brut - les lignes vides séparent les paragraphes.',
        'save' => 'Enregistrer',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Politique de confidentialité',
        'description' => 'Texte brut - les lignes vides séparent les paragraphes.',
        'save' => 'Enregistrer',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'Résumé par e-mail',
        'fieldLabel' => 'Paragraphe de conclusion',
        'notice' => 'Ajouté vers la fin de chaque résumé, après la liste de ce que le membre a manqué. Texte brut. Laissez vide pour revenir au texte fourni par défaut avec ce logiciel.',
        'save' => 'Enregistrer',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Favicon actuel',
        'save' => 'Importer le favicon',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Image de la page d\'accueil',
        'explainer' => 'Affichée par d\'autres sites lorsque quelqu\'un partage un lien vers celui-ci - uniquement des métadonnées Open Graph, jamais sur la page elle-même. Rognée à 1200×630. Sans elle, les aperçus de lien n\'affichent aucune image.',
        'currentAlt' => 'Image actuelle de la page d\'accueil',
        'save' => 'Importer une image',
    ],
];
