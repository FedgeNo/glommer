<?php

declare(strict_types=1);

/**
 * French for the messaging and admin-status classes. See
 * src/locales/en/MessagingAndStatus.php for what each of these is.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Vous avez oublié votre phrase secrète ? La réinitialiser crée de nouvelles clés sous une nouvelle phrase - mais les messages chiffrés avec les anciennes clés ne pourront plus jamais être lus, par personne.',
        'requirements' => 'Au moins 12 caractères, et pas votre mot de passe de compte - celui-ci est envoyé à ce serveur, alors que votre phrase secrète ne doit jamais l\'être.',
        'passphraseLabel' => 'Phrase secrète',
        'resetPassphraseLabel' => 'Nouvelle phrase secrète',
        'confirmLabel' => 'Confirmer la phrase secrète',
        'accountPasswordLabel' => 'Mot de passe du compte',
        'submitLabel' => 'Activer les messages chiffrés',
        'resetSubmitLabel' => 'Réinitialiser les clés de chiffrement',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'Les messages chiffrés de bout en bout sont verrouillés et déverrouillés dans votre navigateur : ce serveur les relaie et les stocke sans pouvoir les lire. Votre clé est protégée par une phrase secrète, et la même phrase secrète déverrouille vos messages depuis n\'importe quel navigateur. Les conversations sont chiffrées une fois que les deux personnes ont activé cette fonctionnalité ; les messages destinés à des personnes sur d\'autres serveurs restent non chiffrés, car la fédération n\'a aucun autre moyen de les transporter.',
        'noRecovery' => 'Il n\'existe aucun moyen de récupérer une phrase secrète perdue - pas même pour l\'administrateur. La perdre signifie perdre vos messages chiffrés.',
        'enabledStatus' => 'Les messages chiffrés sont activés.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Chiffré',
            'explanation' => 'Les messages de cette conversation sont chiffrés de bout en bout : ils sont déverrouillés avec votre phrase secrète et lus dans vos navigateurs, et ce que ce serveur stocke est du texte chiffré. Vérifiez le code de sécurité en bas de la conversation avec l\'autre personne pour être sûr que personne ne s\'est interposé entre vous. Les messages envoyés avant l\'activation du chiffrement restent lisibles comme avant.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Non chiffré',
            'explanation' => 'Les messages ici seront chiffrés de bout en bout une fois que {handle} aura activé les messages chiffrés dans ses paramètres.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Non chiffré',
            'explanation' => 'Les messages ici ne sont pas chiffrés de bout en bout. Activez les messages chiffrés dans les paramètres pour sécuriser cette conversation.',
        ],
        'federated' => [
            'label' => '🔓 Non chiffré',
            'explanation' => '{handle} est sur un autre serveur. Les messages de cette conversation sont stockés sur ce serveur en plus de celui-ci, et son administrateur peut les lire - le protocole entre serveurs n\'a aucun moyen de les chiffrer. Réservez tout ce qui est sensible aux conversations sur ce site.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Suivre des comptes du fédivers',
        'notice' => 'Collez un ou plusieurs identifiants, par ex. @user@example.social - n\'importe quel séparateur entre eux fonctionne.',
        'handlesLabel' => 'Identifiants du fédivers à suivre',
        'submit' => 'Suivre',
        'statusPending' => 'en attente',
        'statusAccepted' => 'accepté',
    ],

    'ServerBlockForm' => [
        'legend' => 'Bloquer un serveur',
        'description' => 'Refuse tout ce qui vient de ce serveur et de tout ce qui en dépend : aucune livraison entrante, aucune sortante, et les abonnements existants dans les deux sens sont supprimés.',
        'serverLabel' => 'Serveur',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Motif',
        'reasonPlaceholder' => 'Pourquoi ce serveur est bloqué',
        'submit' => 'Bloquer le serveur',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Exécute les parties de la configuration d\'appel vérifiables depuis un seul navigateur. Tout ce qui précède la connexion pair-à-pair proprement dite est testable ici ; se connecter à une autre personne nécessite cette personne.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Lancer la vérification',
    ],

    'WebSocketStatus' => [
        'ok' => 'Serveur WebSocket : actif',
        'failed' => 'Serveur WebSocket : {detail}',
        'clientTesting' => 'Connexion du navigateur : test en cours…',
        'clientConnecting' => 'Connexion du navigateur : connexion en cours…',
        'clientConnected' => 'Connexion du navigateur : connectée',
        'clientDisconnecting' => 'Connexion du navigateur : déconnexion en cours…',
        'clientNotConnected' => 'Connexion du navigateur : non connectée',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Service de traitement : actif',
        'stopped' => 'Service de traitement : à l\'arrêt - les envois en attente ne seront jamais transcodés tant qu\'il n\'aura pas redémarré',
        'unknown' => 'Service de traitement : inconnu - soit systemctl n\'est pas disponible sur cet hôte, soit SELinux refuse la requête de statut du serveur web lui-même (exécutez bin/install.php en tant que root pour corriger cela)',
        'queue' => 'File d\'attente : {staging} en préparation, {pending} en attente, {processing} en cours',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Minuteur des tendances : actif',
        'stopped' => 'Minuteur des tendances : à l\'arrêt - les sujets tendance ne seront actualisés que par l\'auto-réparation du chemin de lecture (Trending::current()), pas selon un horaire. Exécutez bin/install.php en tant que root pour le configurer.',
        'unknown' => 'Minuteur des tendances : inconnu - soit systemctl n\'est pas disponible sur cet hôte, soit SELinux refuse la requête de statut du serveur web lui-même (exécutez bin/install.php en tant que root pour corriger cela)',
    ],
];
