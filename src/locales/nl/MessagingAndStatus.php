<?php

declare(strict_types=1);

/**
 * Dutch for the messaging and admin-status classes. See
 * src/locales/en/MessagingAndStatus.php for the source and the shape each
 * entry is built to.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Wachtwoordzin vergeten? Opnieuw instellen maakt nieuwe sleutels aan onder een nieuwe wachtwoordzin - maar berichten die met de oude sleutels zijn versleuteld, kunnen door niemand ooit nog worden gelezen.',
        'requirements' => 'Minstens 12 tekens, en niet je accountwachtwoord - dat wordt naar deze server gestuurd, en je wachtwoordzin mag dat nooit worden.',
        'passphraseLabel' => 'Wachtwoordzin',
        'resetPassphraseLabel' => 'Nieuwe wachtwoordzin',
        'confirmLabel' => 'Bevestig wachtwoordzin',
        'accountPasswordLabel' => 'Accountwachtwoord',
        'submitLabel' => 'Versleutelde berichten inschakelen',
        'resetSubmitLabel' => 'Versleutelingssleutels opnieuw instellen',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'End-to-end versleutelde berichten worden in je browser vergrendeld en ontgrendeld: deze server geeft ze door en slaat ze op zonder ze te kunnen lezen. Je sleutel is beveiligd met een wachtwoordzin, en dezelfde wachtwoordzin ontgrendelt je berichten vanuit elke browser. Gesprekken worden versleuteld zodra beide personen dit hebben ingeschakeld; berichten aan mensen op andere servers blijven onversleuteld, omdat federatie ze anders niet kan overbrengen.',
        'noRecovery' => 'Er is geen manier om een verloren wachtwoordzin te herstellen - ook niet voor de beheerder. Als je hem kwijtraakt, raak je ook je versleutelde berichten kwijt.',
        'enabledStatus' => 'Versleutelde berichten staan aan.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Versleuteld',
            'explanation' => 'Berichten in dit gesprek zijn end-to-end versleuteld: ze worden ontgrendeld met je wachtwoordzin en gelezen in jullie browsers, en wat deze server opslaat is cijfertekst. Controleer de veiligheidscode onderaan de conversatie samen met de ander om zeker te weten dat niemand zich tussen jullie bevindt. Berichten die zijn verstuurd voordat versleuteling werd ingeschakeld, blijven leesbaar zoals ze waren.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Niet versleuteld',
            'explanation' => 'Berichten hier worden end-to-end versleuteld zodra {handle} versleutelde berichten inschakelt in hun instellingen.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Niet versleuteld',
            'explanation' => 'Berichten hier zijn niet end-to-end versleuteld. Schakel versleutelde berichten in bij Instellingen om dit gesprek te beveiligen.',
        ],
        'federated' => [
            'label' => '🔓 Niet versleuteld',
            'explanation' => '{handle} zit op een andere server. Berichten in dit gesprek worden zowel op die server als op deze opgeslagen, en de beheerder daarvan kan ze lezen - het protocol tussen servers heeft geen manier om ze te versleutelen. Bewaar gevoelige zaken voor gesprekken op deze site.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Fediverse-accounts volgen',
        'notice' => 'Plak een of meer handles, bijv. @gebruiker@example.social - elk scheidingsteken ertussen werkt.',
        'handlesLabel' => 'Te volgen Fediverse-handles',
        'submit' => 'Volgen',
        'statusPending' => 'in behandeling',
        'statusAccepted' => 'geaccepteerd',
    ],

    'ServerBlockForm' => [
        'legend' => 'Een server blokkeren',
        'description' => 'Weigert alles van die server en alles daaronder: geen berichten meer in, geen meer uit, en bestaande volgrelaties in beide richtingen worden verbroken.',
        'serverLabel' => 'Server',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Reden',
        'reasonPlaceholder' => 'Waarom deze server is geblokkeerd',
        'submit' => 'Server blokkeren',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Voert de onderdelen van het opzetten van een gesprek uit die vanuit één browser gecontroleerd kunnen worden. Alles tot aan de daadwerkelijke peer-to-peerverbinding is hier te testen; verbinding maken met iemand anders vereist die persoon.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Controle uitvoeren',
    ],

    'WebSocketStatus' => [
        'ok' => 'WebSocket-server: actief',
        'failed' => 'WebSocket-server: {detail}',
        'clientTesting' => 'Browserverbinding: testen…',
        'clientConnecting' => 'Browserverbinding: verbinden…',
        'clientConnected' => 'Browserverbinding: verbonden',
        'clientDisconnecting' => 'Browserverbinding: verbinding verbreken…',
        'clientNotConnected' => 'Browserverbinding: niet verbonden',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Workerservice: actief',
        'stopped' => 'Workerservice: niet actief - klaarstaande uploads worden nooit getranscodeerd totdat deze opnieuw wordt gestart',
        'unknown' => 'Workerservice: onbekend - systemctl is niet beschikbaar op deze host, of SELinux weigert de eigen statusquery van de webserver (voer bin/install.php uit als root om dit op te lossen)',
        'queue' => 'Wachtrij: {staging} klaarstaand, {pending} in afwachting, {processing} in verwerking',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Trendingtimer: actief',
        'stopped' => 'Trendingtimer: niet actief - trending topics worden alleen ververst via het zelfherstel van het leespad (Trending::current()), niet volgens een schema. Voer bin/install.php uit als root om dit in te stellen.',
        'unknown' => 'Trendingtimer: onbekend - systemctl is niet beschikbaar op deze host, of SELinux weigert de eigen statusquery van de webserver (voer bin/install.php uit als root om dit op te lossen)',
    ],
];
