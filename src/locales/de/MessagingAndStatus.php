<?php

declare(strict_types=1);

/**
 * German for the messaging and admin-status classes. See
 * src/locales/en/MessagingAndStatus.php for the source and the shape each
 * entry is built to.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Passphrase vergessen? Ein Zurücksetzen erzeugt neue Schlüssel unter einer neuen Passphrase - aber Nachrichten, die mit den alten Schlüsseln verschlüsselt wurden, kann niemand je wieder lesen.',
        'requirements' => 'Mindestens 12 Zeichen, und nicht dein Kontopasswort - das wird an diesen Server gesendet, und deine Passphrase darf das niemals.',
        'passphraseLabel' => 'Passphrase',
        'resetPassphraseLabel' => 'Neue Passphrase',
        'confirmLabel' => 'Passphrase bestätigen',
        'accountPasswordLabel' => 'Kontopasswort',
        'submitLabel' => 'Verschlüsselte Nachrichten aktivieren',
        'resetSubmitLabel' => 'Verschlüsselungsschlüssel zurücksetzen',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'Ende-zu-Ende-verschlüsselte Nachrichten werden in deinem Browser gesperrt und entsperrt: Dieser Server leitet sie nur weiter und speichert sie, ohne sie lesen zu können. Dein Schlüssel ist durch eine Passphrase geschützt, und dieselbe Passphrase entsperrt deine Nachrichten in jedem Browser. Unterhaltungen werden verschlüsselt, sobald beide Seiten dies aktiviert haben; Nachrichten an Personen auf anderen Servern bleiben unverschlüsselt, weil die Föderation sie sonst nicht übertragen kann.',
        'noRecovery' => 'Es gibt keine Möglichkeit, eine verlorene Passphrase wiederherzustellen - nicht einmal für die Administration. Sie zu verlieren bedeutet, deine verschlüsselten Nachrichten zu verlieren.',
        'enabledStatus' => 'Verschlüsselte Nachrichten sind aktiviert.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Verschlüsselt',
            'explanation' => 'Nachrichten in dieser Unterhaltung sind Ende-zu-Ende-verschlüsselt: Sie werden mit deiner Passphrase entsperrt und in euren Browsern gelesen, und was dieser Server speichert, ist Chiffretext. Vergleiche den Sicherheitscode am Ende des Threads mit der anderen Person, um sicherzugehen, dass niemand zwischen euch steht. Nachrichten, die vor dem Aktivieren der Verschlüsselung gesendet wurden, bleiben lesbar wie zuvor.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Nicht verschlüsselt',
            'explanation' => 'Nachrichten hier werden Ende-zu-Ende-verschlüsselt, sobald {handle} verschlüsselte Nachrichten in den Einstellungen aktiviert.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Nicht verschlüsselt',
            'explanation' => 'Nachrichten hier sind nicht Ende-zu-Ende-verschlüsselt. Aktiviere verschlüsselte Nachrichten in den Einstellungen, um diese Unterhaltung zu sichern.',
        ],
        'federated' => [
            'label' => '🔓 Nicht verschlüsselt',
            'explanation' => '{handle} ist auf einem anderen Server. Nachrichten in dieser Unterhaltung werden dort ebenso gespeichert wie hier, und die dortige Administration kann sie lesen - das Protokoll zwischen Servern hat keine Möglichkeit, sie zu verschlüsseln. Behalte Sensibles für Unterhaltungen auf dieser Seite.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Fediverse-Konten folgen',
        'notice' => 'Füge ein oder mehrere Handles ein, z. B. @user@example.social - jedes Trennzeichen dazwischen funktioniert.',
        'handlesLabel' => 'Fediverse-Handles zum Folgen',
        'submit' => 'Folgen',
        'statusPending' => 'ausstehend',
        'statusAccepted' => 'akzeptiert',
    ],

    'ServerBlockForm' => [
        'legend' => 'Server blockieren',
        'description' => 'Weist alles von diesem Server und allem darunter ab: keine Zustellungen rein, keine raus, und bestehende Folge-Beziehungen in beide Richtungen werden aufgelöst.',
        'serverLabel' => 'Server',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Grund',
        'reasonPlaceholder' => 'Warum dieser Server blockiert wird',
        'submit' => 'Server blockieren',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Führt die Teile des Anrufaufbaus aus, die sich von einem einzelnen Browser aus prüfen lassen. Alles bis zur eigentlichen Peer-to-Peer-Verbindung lässt sich hier testen; die Verbindung zu einer anderen Person braucht diese Person.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Prüfung ausführen',
    ],

    'WebSocketStatus' => [
        'ok' => 'WebSocket-Server: Läuft',
        'failed' => 'WebSocket-Server: {detail}',
        'clientTesting' => 'Browser-Verbindung: Wird getestet…',
        'clientConnecting' => 'Browser-Verbindung: Verbindet…',
        'clientConnected' => 'Browser-Verbindung: Verbunden',
        'clientDisconnecting' => 'Browser-Verbindung: Trennt…',
        'clientNotConnected' => 'Browser-Verbindung: Nicht verbunden',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Worker-Dienst: Läuft',
        'stopped' => 'Worker-Dienst: Läuft nicht - bereitgestellte Uploads werden erst transkodiert, wenn er neu gestartet wird',
        'unknown' => 'Worker-Dienst: Unbekannt - entweder ist systemctl auf diesem Host nicht verfügbar, oder SELinux blockiert die eigene Statusabfrage des Webservers (führe bin/install.php als root aus, um das zu beheben)',
        'queue' => 'Warteschlange: {staging} in Vorbereitung, {pending} ausstehend, {processing} in Bearbeitung',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Trending-Timer: Läuft',
        'stopped' => 'Trending-Timer: Läuft nicht - Trendthemen aktualisieren sich nur über die Selbstheilung beim Lesezugriff (Trending::current()), nicht nach Zeitplan. Führe bin/install.php als root aus, um ihn einzurichten.',
        'unknown' => 'Trending-Timer: Unbekannt - entweder ist systemctl auf diesem Host nicht verfügbar, oder SELinux blockiert die eigene Statusabfrage des Webservers (führe bin/install.php als root aus, um das zu beheben)',
    ],
];
