<?php

declare(strict_types=1);

/**
 * French for the notification and direct-messaging classes not already
 * covered by MessagingAndStatus.php. See src/locales/en/MessagingExtras.php
 * for what each of these is.
 */

return [
    'Notification' => [
        'postReady' => 'Votre média a terminé son traitement et est maintenant en ligne',
        'scheduledPostLive' => 'Votre publication programmée est maintenant en ligne',
        'uploadPartlyFailed' => 'Votre publication est en ligne, mais un ou plusieurs de ses fichiers n\'ont pas pu être traités',
        'uploadFailed' => 'Un de vos envois n\'a pas pu être traité et n\'a pas été publié',
        'mailerFailed' => 'L\'envoi de l\'e-mail a échoué - le service de messagerie est peut-être hors service. Veuillez vérifier votre configuration de messagerie.',
        'mailFromNotConfigured' => 'Aucune adresse "d\'expédition" n\'est configurée, les e-mails ne peuvent donc pas être envoyés. Définissez-en une dans les paramètres du site (section Courrier) ou via bin/install.php.',
        'systemError' => 'Une erreur serveur est survenue. Consultez le journal des erreurs pour plus de détails.',
        'passwordRemovedGoogle' => 'Votre mot de passe a été supprimé lorsque vous vous êtes connecté avec Google. Utilisez "Mot de passe oublié" si vous voulez en définir un nouveau.',
        'like' => '{name} a aimé votre publication',
        'repost' => '{name} a republié votre publication',
        'reply' => '{name} a répondu à votre publication',
        'friendRequest' => '{name} vous a envoyé une demande d\'ami',
        'friendAccepted' => '{name} a accepté votre demande d\'ami',
        'message' => '{name} vous a envoyé un message',
        'mention' => '{name} vous a mentionné dans une publication',
        'follow' => '{name} vous a suivi depuis un autre serveur',
        'default' => '{name} a fait quelque chose',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Aucune notification pour le moment.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Notifications',
        'unseen' => 'Notifications non vues',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Envoyez-vous une notification de test (à l\'administrateur). Elle doit apparaître instantanément sous forme de toast et dans le menu déroulant des notifications.',
        'button' => 'Envoyer une notification de test',
        'sending' => 'Envoi en cours…',
        'sent' => 'Envoyé',
        'failed' => 'Échec',
    ],

    'MessageDot' => [
        'label' => 'Messages non lus',
    ],

    'NavAlertDot' => [
        'label' => 'Quelque chose de nouveau dans le menu',
    ],

    'Message' => [
        'encrypted' => 'Message chiffré',
        'decryptionFailed' => 'Ce message a été chiffré avec des clés qui n\'existent plus.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Message',
        'bodyPlaceholder' => 'Écrivez un message',
        'send' => 'Envoyer',
    ],

    'MessageList' => [
        'emptyNotice' => 'Aucun message pour le moment.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Lisez-vous ce code mutuellement d\'une autre façon - à voix haute, en personne, lors d\'un appel. S\'il correspond des deux côtés, personne ne s\'est interposé entre vous.',
        'changed' => 'Ce code a changé depuis que vous l\'avez vérifié. Cela se produit quand l\'un de vous réinitialise ses clés de chiffrement - mais c\'est aussi ce à quoi ressemblerait quelqu\'un en train de lire cette conversation. Vérifiez le nouveau code avec cette personne avant de lui faire confiance.',
        'verified' => 'Vous avez vérifié ce code.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Marquer comme vérifié',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Phrase secrète',
        'passphrasePlaceholder' => 'Phrase secrète pour déverrouiller cette conversation',
        'submit' => 'Déverrouiller',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Dernier message ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Média sensible',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Afficher les médias sensibles par défaut',
    ],
];
