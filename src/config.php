<?php

declare(strict_types=1);

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'glommer'),
    'username' => Env::get('DB_USERNAME', 'glommer'),
    'password' => Env::get('DB_PASSWORD', 'change-me'),
    // Both the mail "from" address/name and the SMTP relay (host/port/
    // username/password/encryption) used to live here (MAIL_FROM_ADDRESS etc.,
    // SMTP_HOST etc.) - they're now Settings DB table settings, editable live
    // from the admin Site Settings page (see Mailer's *_SETTING constants)
    // instead of requiring a .env edit + no live-reload. mailFromName falls
    // back to the site's own title, the only name it could honestly sign mail
    // with (a missing display name is cosmetic either way);
    // mailFromAddress has none - a missing "from" address isn't safe to
    // silently paper over with a fake one (see Mailer::send()), so there's no
    // config.php key for it at all anymore. Left empty, Mailer's SMTP relay
    // falls back to PHP's mail() (the local sendmail handoff, which on a
    // typical VPS lands straight in spam folders).
    'mailFromName' => Env::get('MAIL_FROM_NAME', '') ?: Env::get('SITE_TITLE', ''),
    // No default, for the same reason siteTitle has none, and a sharper one:
    // a placeholder here is a real address belonging to somebody else, and the
    // canonical-host redirect would send every visitor to it. Empty is a
    // misconfiguration, caught in init.php rather than papered over.
    'siteURL' => Env::get('SITE_URL', ''),
    // No default, unlike every other key here: a site's title is its own name,
    // and there is no name that would be right to hand someone else's site.
    // Both install paths require one and bin/install.php fails without it, so
    // the empty string is a misconfiguration rather than a state to design for.
    'siteTitle' => Env::get('SITE_TITLE', ''),
    // How many media transcodes the upload-worker service (bin/upload-worker.php)
    // runs at once. It drains the async upload queue at this bounded rate so a
    // burst of uploads can't spawn unlimited concurrent ffmpeg processes and
    // exhaust the host. 2 is safe on almost any hardware; raise it on a box with
    // spare cores.
    'uploadWorkerConcurrency' => max(1, (int) Env::get('UPLOAD_WORKER_CONCURRENCY', '2')),
    // The WebSocket daemon (bin/websocket-server.php) is a separate long-running
    // process from Apache/PHP-FPM - these let both sides agree on where it
    // lives and share a secret for signing/verifying connection tokens and
    // authenticating the internal push channel between them.
    'WSHost' => Env::get('WS_HOST', '0.0.0.0'),
    'WSPort' => (int) Env::get('WS_PORT', '8090'),
    'WSPushPort' => (int) Env::get('WS_PUSH_PORT', '8091'),
    // No usable default: an unset OR still-placeholder ('change-me') secret
    // resolves to null, so WS auth fails closed (WSToken and the push channel
    // reject a null secret) instead of running on a value anyone can read from
    // .env.example or this file. A real install always has a random one.
    'WSSecret' => in_array($ws = Env::get('WS_SECRET', ''), ['', 'change-me'], true) ? null : $ws,
    'WSTLSCert' => Env::get('WS_TLS_CERT', '') ?: null,
    'WSTLSKey' => Env::get('WS_TLS_KEY', '') ?: null,
];
