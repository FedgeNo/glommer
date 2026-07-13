# Glommer

Glommer is a self-hosted social publishing platform - posts, replies, friends, messaging, and notifications, built as a plain PHP + MySQL app with no frontend framework.

## Features

- **Posts** - text, a title, an optional link (with an automatically-fetched title/description/image preview pulled in at compose time), or attached images/video/audio, posted through a rich-text editor with support for hashtags and math formulas
- **Hashtags** - extract #hashtags from posts, browse public tag pages, and explore trending tags
- **Search** - full-text search of posts at `/search`, plus user search at `/users`
- **Replies** - threaded replies to any post, shown as a full conversation thread
- **Likes**
- **Friends** - friend requests (send/accept/deny/cancel), remove-friend, and a friends-only feed. Everyone's friends are public at `/users/{username}/friends` (with pending/sent request sections)
- **Users** - find people by username/display name, plus friend-of-friend suggestions ranked by mutual friends
- **Messaging** - direct conversations with other users
- **Live messaging** - conversations update in real time over a WebSocket connection when the other person replies
- **Notifications** - live-updating via WebSocket (toast pop-ups, unseen-count dot) for likes, replies, friend requests/acceptances, messages, and media-processing results (a post going live, or files that couldn't be processed)
- **Help** - a public, searchable help section at `/help/` (articles authored in-code, searched in-PHP)
- **Moderation** - blocking users; reporting a specific post, message, or user; an admin/mod reports queue with content snapshots (showing the reported post/message state at report time, not what it's since been edited to)
- **Site settings** - an admin panel for the optional Cloudflare Turnstile CAPTCHA (sign-up/sign-in bot protection), a custom favicon, and the editable Terms of Service (`/terms/`) and Privacy Policy
- **Accounts** - signup with email verification, login/logout with "Remember me" persistent sessions, forgot/reset password (a password change logs out every other session and device), email change with verification, and account deletion
- **RSS** - a site-wide feed at `/feed.xml` and a per-user feed at `/users/{username}/feed.xml`, auto-discoverable from the relevant pages
- **Relative timestamps** ("3m ago") that stay correct against server time, falling back to an absolute date after 7 days
- **Infinite scroll** for feeds, notifications, message history, and the friends/requests lists
- **Everything AJAX** - all site updates via AJAX/JSON with minimal full-page reloads

## Requirements

- PHP 8.1+ with the `mysqli`, `gd`, `curl`, `dom`, `libxml`, `fileinfo`, and `mbstring` extensions (the WebSocket and upload-worker daemons also use `pcntl` and `sockets`)
- MySQL or MariaDB
- For video/audio uploads: `ffmpeg`, `ffprobe`, `timeout` (coreutils), and `bash` on `PATH`, with `exec()`/`shell_exec()` enabled - each transcode runs sandboxed under wall-clock, CPU, and memory limits
- Outbound HTTPS access (for link preview fetching)
- A web server (e.g. Apache with `mod_rewrite`) pointed at the project root, running the included `.htaccess`
- Two long-running background processes, both **separate from the web server** and both set up for you by `bin/install.php`:
  - `bin/websocket-server.php` - powers live notifications and messaging (the installers verify it's reachable before completing)
  - `bin/upload-worker.php` - transcodes queued video/audio uploads at a bounded concurrency

## Running the WebSocket server

`bin/websocket-server.php` is a stand-alone daemon (no Composer, no external libraries - hand-rolled RFC 6455 handshake/framing over plain PHP streams) that must already be running before either installer is used.

Recommended: a user-level systemd service (no root needed):

```ini
# ~/.config/systemd/user/glommer-websocket.service
[Unit]
Description=Glommer WebSocket server
After=network.target

[Service]
ExecStart=/usr/bin/php /path/to/glommer/bin/websocket-server.php
Restart=always
RestartSec=2
WatchdogSec=30
RuntimeMaxSec=1d
WorkingDirectory=/path/to/glommer

[Install]
WantedBy=default.target
```

```
systemctl --user daemon-reload
systemctl --user enable --now glommer-websocket.service
loginctl enable-linger "$USER"   # keep it running after logout and start it on boot
```

The `enable-linger` step is essential on a headless server: a user-level service otherwise only runs while that user has an active login session, so without lingering the daemon stops the moment you disconnect.

The `WatchdogSec=30` line enables systemd watchdog monitoring - if the daemon's event loop hangs, systemd automatically restarts it (something a plain `Restart=always` crash restart can't catch). The `RuntimeMaxSec=1d` line causes the daemon to recycle daily, preventing slow resource growth from accumulating. The `bin/install.php` script offers to set both up automatically; if you're using a different process manager, these are optional (the daemon works fine without them).

Before `.env` exists yet (a fresh install), it runs with `config.php`'s defaults (`WS_PORT=8090`, `WS_SECRET=change-me`) - that's fine for the install-time connectivity check, since both the daemon and the installer start fresh.

## Running the upload worker

`bin/upload-worker.php` turns queued video/audio uploads into finished posts. When someone uploads media, the web request stages the files to disk and returns immediately; this worker drains that queue in the background - transcoding each file with ffmpeg, publishing the post when it's ready, and notifying the author over the WebSocket. Uploads that need no transcoding (images) are handled inline in the request and don't touch this queue.

It processes at a bounded concurrency - `UPLOAD_WORKER_CONCURRENCY` in `.env` (default 2) - so a burst of uploads can't spawn unlimited concurrent ffmpeg processes and overwhelm the host; raise it on a box with spare cores. A file whose transcode repeatedly kills the worker is dropped and the post goes live with whatever succeeded; if nothing does, the author is told the upload failed. Without this service running, staged uploads simply queue on disk until it starts.

Recommended: a user-level systemd service, exactly like the WebSocket one:

```ini
# ~/.config/systemd/user/glommer-upload-worker.service
[Unit]
Description=Glommer media upload worker
After=network.target

[Service]
ExecStart=/usr/bin/php /path/to/glommer/bin/upload-worker.php
Restart=always
RestartSec=2
WatchdogSec=30
WorkingDirectory=/path/to/glommer

[Install]
WantedBy=default.target
```

```
systemctl --user daemon-reload
systemctl --user enable --now glommer-upload-worker.service
loginctl enable-linger "$USER"   # same as the WebSocket service
```

`bin/install.php` offers to set this up automatically, the same way it does the WebSocket service.

## Installation

There are two equivalent guided installers - a web setup wizard and an interactive CLI - plus a fully manual path. All three end in the same place: a provisioned database, a least-privilege runtime account, and a populated schema.

### Web setup wizard

1. Clone/copy the project to your web server's document root.
2. Make sure the web server user can write to the project root (e.g. `chmod 777 <project root>` - the success page reminds you to restore this).
3. Start the WebSocket server (see above) - it can run with no `.env` in place yet.
4. Visit the site in a browser. Since there's no `.env` yet, you'll land on a setup page instead of the normal site.
   - If any environment prerequisite is missing (PHP version, extensions, `ffmpeg`, writable directories, outbound network, the WebSocket server, etc.), it's reported here - fix it and reload to retry.
   - Otherwise you'll see a setup form, pre-filled with sensible defaults (the site URL you're visiting by, standard local-MySQL settings), asking for:
     - **Site** - site URL, site title, and the "from" address/name used for outgoing email
     - **Database** - host, port, database name, and credentials for a MySQL account with `CREATE`/`ALTER`/`DROP`/`CREATE USER`/`GRANT OPTION` privileges (e.g. `root`, or any admin account with those roles)
     - **WebSocket TLS (optional)** - certificate/key paths, only needed if automatic generation fails (see step 5)
5. Submit the form. It proves HTTPS is actually being served, that `ServerName`/`UseCanonicalName` genuinely block Host-header spoofing (the same live checks as the CLI), tries to generate a WebSocket TLS certificate with mkcert if needed, and provisions the database.
6. Follow the numbered checklist on the success page: restore the project root's permissions (`chmod 755 <project root>`), restart the WebSocket server (`systemctl --user restart glommer-websocket`), visit the site, and create the first account (which becomes the administrator).

The setup page only ever appears while `.env` doesn't exist. Once it does, a failing database connection shows a maintenance page instead - deliberately, so a database outage on an established site doesn't invite visitors to re-run the installer.

### Interactive CLI

Everything the web wizard does, from a terminal:

```
php bin/install.php
```

- Runs every environment check and reports all of them at once (colored, when the terminal supports it).
- If the WebSocket server is the only thing missing, offers to write the user-level systemd unit itself (correct paths filled in), enable it, start it, and re-check - no manual unit-file editing.
- Sets up the media upload-worker service (`glommer-upload-worker.service`) the same way when it isn't enabled, and keeps every service's unit file in sync with the current template on each run.
- If no backup has ever completed, offers to run one now (proving the mechanism works) and, once it succeeds, set up a nightly systemd timer for it - same idea as the WebSocket unit above.
- Proves HTTPS is actually being served (a real TLS connection to your configured hostname) and that Apache's `ServerName`/`UseCanonicalName` genuinely block Host-header spoofing (a live forged-Host-header request gets refused, not redirected).
- With no `.env` present, walks through the same questions as the web form (with the same defaults), provisions the database/runtime account/schema, and writes `.env`.
- With `.env` present, verifies it, creates any missing tables, and **detects schema drift** - columns, indexes, or foreign keys that `schema.sql` defines but the live tables lack (the situation after upgrading, or if an earlier installer didn't complete fully).
- Admin credentials are prompted for only when there's actual schema work to do; set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` as environment variables to run non-interactively (e.g. from a deploy script).
- Every self-repair offer above has a matching non-interactive opt-in, for the same kind of scripted/CI runs: `SERVERNAME_CONFIRMED=1`, `BACKUP_TIMER_CONFIRMED=1`, and `WEBSOCKET_SERVICE_CONFIRMED=1`.

Re-running it on a healthy install is always safe - it changes nothing unless something is missing, and it's the recommended first step after every upgrade.

### Manual

1. Copy `.env.example` to `.env` and fill in `SITE_URL`, `SITE_TITLE`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` for a least-privilege database account (one with `SELECT`, `INSERT`, `UPDATE`, `DELETE` only).
2. Create the database and load the schema as a MySQL admin account:
   ```
   mysql -u root -p -e "CREATE DATABASE glommer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p glommer < schema.sql
   ```
   Then create the runtime account and grant it `SELECT, INSERT, UPDATE, DELETE` on that database only - it deliberately doesn't get `CREATE`/`ALTER`/`DROP`.
3. Make sure `uploads/` (and its subdirectories) are writable by the web server user - `bin/install.php` (below) creates them for you if missing.
4. Start the WebSocket server and the upload worker (see above) with this `.env` already in place.
5. Verify everything with `php bin/install.php` (see "Interactive CLI" above - on an already-configured install it acts as a pure checker/repairer).
6. Visit the site and sign up - the first account created becomes the site's administrator.

## Administration model

The **first account created on a fresh install is the site's administrator** - this is how the software operates, not a convention: the admin is always `userId` 1, and admin-only actions (appointing/revoking mod status, viewing the reports queue, editing site settings) are restricted to them.

## Upgrading

The codebase carries a version (`GLOMMER_VERSION` in `src/init.php`) and the database records the version it was last installed or upgraded to. After pulling new code, **run `php bin/install.php`** to apply any schema changes and record the new version.

If the only thing pending is DML maintenance (no missing tables, schema drift, or index migrations - the common case for most releases), the site upgrades itself silently on the first request after a deploy. Visitors see no maintenance page, and the upgrade is applied in the background with `ignore_user_abort()` in effect so a dropped connection can't interrupt it mid-migration.

## Backups

`bin/backup.php` backs up everything a restore needs that git doesn't hold: a gzipped `mysqldump` of the database and a tarball of `uploads/` (originals included). Each run writes a timestamped directory and can prune older backups automatically.

```
php bin/backup.php                 # defaults: ../glommer-backups, keep 7 days
BACKUP_DIR=/mnt/backups/glommer BACKUP_KEEP_DAYS=14 php bin/backup.php
```

The backup root must be **outside the project root** (the script refuses otherwise - a web-servable database dump would be a full data breach). Run it nightly with a systemd user timer:

(`bin/install.php` offers to run the first backup and set up this timer - service, timer, enable, and linger - for you, the same way it does for the WebSocket service above; the steps below are for manual setup.)

```ini
# ~/.config/systemd/user/glommer-backup.service
[Unit]
Description=Glommer backup

[Service]
Type=oneshot
ExecStart=/usr/bin/php /path/to/glommer/bin/backup.php
```

```ini
# ~/.config/systemd/user/glommer-backup.timer
[Unit]
Description=Nightly Glommer backup

[Timer]
OnCalendar=*-*-* 04:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

```
systemctl --user daemon-reload
systemctl --user enable --now glommer-backup.timer
```

(`loginctl enable-linger "$USER"` if you haven't already - same as the WebSocket service.) To restore: create the database, `gunzip -c database.sql.gz | mysql glommer`, untar `uploads.tar.gz` into the project root, and visit the site.

## Email deliverability

Out of the box, mail goes through PHP's `mail()` - the local sendmail. On a typical VPS that mail has no sending reputation and lands in spam folders (or nowhere). For real deliverability, do one of the following:

1. **Use an SMTP relay** - set the `SMTP_*` keys in `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION=tls|ssl|none`) and Glommer speaks SMTP to it directly (Stateless, no queue - a failed send is reported immediately).
2. **Publish SPF/DKIM/DMARC DNS records** for the domain in `MAIL_FROM_ADDRESS`, matching what your relay documents (they hand you the exact records to add). Without them, receiving servers have no reason to trust your mail, and many are configured to reject it outright.

Test with a signup to a mailbox you control before launch. If sending fails outright, Glommer degrades deliberately: a signup whose verification email can't be sent is **verified automatically** instead of leaving the account stuck unverifiable.

### If you keep the native `mail()` path

Using PHP's `mail()` (no `SMTP_HOST` set) means *your own server* is the sending mail server, so you have to earn its reputation yourself with DNS - a relay normally does all of this for you. Getting it right takes work:

1. **A working local MTA.** `mail()` just hands off to the local sendmail binary - something has to actually accept and relay that outbound. Install and configure Postfix (or the distro's `sendmail` / `exim` / `ssmtp` equivalent) to relay outbound.
2. **SPF** - a TXT record on the sending domain authorizing your server's public IP, e.g. `v=spf1 ip4:YOUR.SERVER.IP -all`. Without it, receivers can't tell your server is allowed to send for the domain.
3. **DKIM** - a signing key the receiver checks against a published public key. `mail()` does *not* sign anything itself; you need a milter like **OpenDKIM** wired into Postfix to sign outbound mail.
4. **DMARC** - a `_dmarc` TXT record (e.g. `v=DMARC1; p=quarantine; rua=mailto:you@yourdomain`) tying SPF/DKIM together and telling receivers what to do with mail that fails both.

Two more that aren't DNS records you publish but matter just as much for a self-hosted sender:

- **PTR / reverse DNS** - the PTR record for your server's IP must resolve back to a hostname on your domain (and forward-confirm). You usually set this at your **hosting/IP provider**, not your domain registrar.
- **Port 25 egress** - many providers block outbound port 25 by default on new accounts; direct-to-MX `mail()` sending is dead in the water until you get it unblocked or relay through a smarthost on an allowed port.

None of this is enforced or checked by Glommer - it's DNS/MTA setup on your infrastructure, outside the app. If that list reads as a lot of moving parts to get right, that's exactly why the **SMTP relay approach is recommended** - a relay provider handles all of it for you.

## HTTPS (required)

Glommer requires HTTPS - it will not serve over plain HTTP. Both installers refuse an `http://` site URL, and on an installed site (once `.env` exists) **every** plain-HTTP request - pages, API calls, everything - gets a 301 redirect to the `https://` version of the URL.

The CLI installer doesn't just trust `SITE_URL`'s `https://` prefix - it opens a real TLS connection to your configured hostname (never `127.0.0.1`, since VirtualHost/SNI routing means loopback may not reach this site at all), verifies the certificate handshake succeeds, and uses the same test to verify Apache's `ServerName`/`UseCanonicalName` setup blocks Host-header spoofing.

Apache also needs `ServerName <your-host>[:<port>]` and `UseCanonicalName On` set (at `httpd.conf`'s top level if you're not using a `<VirtualHost>`, or inside the relevant `<VirtualHost>` block if you are). Without both, the HTTPS redirect can be spoofed via a forged Host header, redirecting victims to a domain attacker controls.

So getting a certificate is part of installing. The certificate itself lives in your web server, not in Glommer:

- **A real domain**: Let's Encrypt via certbot is the usual path:

  ```
  sudo dnf install certbot python3-certbot-apache   # or apt equivalent
  sudo certbot --apache -d your.domain
  ```

- **localhost / development**: public CAs can't issue for localhost, so use a locally-trusted certificate. `mkcert` is the smoothest (no browser warnings, and WebSocket-over-TLS works without fuss):

  ```
  sudo dnf install mkcert nss-tools
  mkcert -install
  mkcert localhost
  ```

  Point Apache's `SSLCertificateFile`/`SSLCertificateKeyFile` at the generated pair. (Fedora alternative: `dnf install mod_ssl` auto-generates a self-signed certificate - functional, but the browser shows a warning.)

Since pages are https, browsers connect to the WebSocket daemon with `wss://` (WebSocket-Secure). Give the daemon the same certificate via `WS_TLS_CERT`/`WS_TLS_KEY` in `.env` and restart it. If you're using a real domain with a certificate from a public CA, you can reuse that certificate for both web and WebSocket. For localhost development with mkcert, point both at the locally-trusted pair mkcert generated.

## Monitoring

`/health` returns `{"ok": true}` (HTTP 200) only when PHP is serving and a real database query succeeds, and an error with HTTP 503 otherwise - point your uptime monitor at it. It deliberately bypasses the version gate, so it works even during an upgrade.

## Design notes

- **Foreign keys**: the content tables (`Posts`, `FeedItems`, `Likes`, `Friendships`, `Blocks`, `Messages`, `Timelines`, `RememberTokens`) carry real foreign keys with `ON DELETE CASCADE`. The system relies on these to enforce data consistency - a user deletion cascades to their posts, which cascade to replies nested under them, which cascade to likes on those replies, etc. all in one atomic transaction.
- **Static assets revalidate on every load** (`Cache-Control: no-cache` = cache but revalidate). This is intentional: avatars and the custom favicon are overwritten in place under stable URLs, so every request checks the server for a fresher version rather than trusting an old cached copy.
- **Media uploads** are transcoded out-of-band by the upload-worker service draining a disk-backed queue at a bounded concurrency, so an upload burst can't overwhelm the host. Each ffmpeg run is sandboxed - restricted to the local-file protocol (no SSRF), gated by a container-format allowlist, and capped on wall-clock, CPU, and memory - and source metadata is stripped from the output. A post is assembled from whatever files transcode successfully.
- **Passwords** are hashed with bcrypt (PHP's `password_hash`), each with its own random salt stored inside the hash; on login, a hash made with an older algorithm or cost is transparently re-hashed to the current default.
