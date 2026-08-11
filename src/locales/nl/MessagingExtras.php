<?php

declare(strict_types=1);

/**
 * Dutch for the notification and direct-messaging classes not already
 * covered by MessagingAndStatus.php. See src/locales/en/MessagingExtras.php
 * for the source and the shape each entry is built to.
 */

return [
    'Notification' => [
        'postReady' => 'Je media is klaar met verwerken en staat nu live',
        'scheduledPostLive' => 'Je geplande post staat nu live',
        'uploadPartlyFailed' => 'Je post staat live, maar een of meer bestanden konden niet worden verwerkt',
        'uploadFailed' => 'Een van je uploads kon niet worden verwerkt en is niet geplaatst',
        'mailerFailed' => 'E-mailbezorging is mislukt - de mailer is mogelijk uit de lucht. Controleer je mailconfiguratie.',
        'mailFromNotConfigured' => 'Er is geen "afzend"-adres voor e-mail geconfigureerd, dus er kunnen geen e-mails worden verstuurd. Stel er een in bij Beheerdersinstellingen (sectie Uitgaande e-mail) of via bin/install.php.',
        'systemError' => 'Er is een serverfout opgetreden. Controleer het foutenlogboek voor details.',
        'passwordRemovedGoogle' => 'Je wachtwoord is verwijderd toen je inlogde met Google. Gebruik "Wachtwoord vergeten" als je een nieuw wachtwoord wilt instellen.',
        'like' => '{name} vindt je post leuk',
        'repost' => '{name} heeft je post gerepost',
        'reply' => '{name} heeft gereageerd op je post',
        'friendRequest' => '{name} heeft je een vriendschapsverzoek gestuurd',
        'friendAccepted' => '{name} heeft je vriendschapsverzoek geaccepteerd',
        'message' => '{name} heeft je een bericht gestuurd',
        'mention' => '{name} noemde je in een post',
        'follow' => '{name} is je gaan volgen vanaf een andere server',
        'default' => '{name} heeft iets gedaan',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Nog geen meldingen.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Meldingen',
        'unseen' => 'Ongeziene meldingen',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Stuur een testmelding naar jezelf (de beheerder). Deze zou direct moeten verschijnen als toast en in het meldingenmenu.',
        'button' => 'Testmelding versturen',
        'sending' => 'Versturen…',
        'sent' => 'Verstuurd!',
        'failed' => 'Mislukt',
    ],

    'MessageDot' => [
        'label' => 'Ongelezen berichten',
    ],

    'NavAlertDot' => [
        'label' => 'Iets nieuws in het menu',
    ],

    'Message' => [
        'encrypted' => 'Versleuteld bericht',
        'decryptionFailed' => 'Dit bericht is versleuteld met sleutels die niet meer bestaan.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Bericht',
        'bodyPlaceholder' => 'Schrijf een bericht',
        'send' => 'Versturen',
    ],

    'MessageList' => [
        'emptyNotice' => 'Nog geen berichten.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Lees deze code op een andere manier aan elkaar voor - hardop, persoonlijk, tijdens een gesprek. Komt hij bij jullie beiden overeen, dan zit er niemand tussen jullie.',
        'changed' => 'Deze code is veranderd sinds je hem hebt gecontroleerd. Dat gebeurt wanneer een van jullie de versleutelingssleutels opnieuw instelt - maar het is ook hoe het eruitziet als iemand dit gesprek meeleest. Controleer de nieuwe code met de ander voordat je hem vertrouwt.',
        'verified' => 'Je hebt deze code gecontroleerd.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Markeren als geverifieerd',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Wachtwoordzin',
        'passphrasePlaceholder' => 'Wachtwoordzin om dit gesprek te ontgrendelen',
        'submit' => 'Ontgrendelen',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Laatste bericht ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Gevoelige media',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Gevoelige media standaard tonen',
    ],
];
