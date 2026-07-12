# Glommer

Glommer is a self-hosted social publishing platform - posts, replies, friends, messaging, and notifications, built as a plain PHP + MySQL app with no frontend framework.

## Features

- **Posts** - text, a title, an optional link (with an automatically-fetched title/description/image preview pulled in at compose time), or attached images/video/audio, posted through a rich-text (Quill) editor with math support (KaTeX) and an emoji picker. Multiple attachments become a swipeable carousel with a Slideshow mode. Links to localhost or private/reserved addresses are rejected.
- **Replies** - threaded replies to any post, shown as a full conversation thread
- **Likes**
- **Friends** - friend requests (send/accept/deny/cancel), remove-friend, and a friends-only feed. Everyone's friends are public at `/users/{username}/friends` (with pending/sent request sections shown only to the owner); a 5000-friend cap is enforced (a maintained `friendCount` column). Each section infinite-scrolls 20 at a time.
- **Users** - find people by username/display name, plus friend-of-friend suggestions ranked by mutual friends
- **Messaging** - direct conversations with other users
- **Notifications** - live-updating over a WebSocket connection (toast pop-ups, unseen-count dot) for likes, replies, friend requests/acceptances, messages, and finished media processing
- **Live messaging** - a conversation you have open updates in real time when the other person replies, over the same WebSocket connection
- **Help** - a public, searchable help section at `/help/` (articles authored in-code, searched in-PHP)
- **Moderation** - blocking users; reporting a specific post, message, or user; an admin/mod reports queue that shows the reported content itself (a reported message's body in a blockquote, a post rendered inline, a user's card) with per-report Dismiss, Delete Content, and Ban Reporter/Reported User actions; a searchable Banned Users page with per-profile Unban; and a database audit log of every moderation action. Moderators are appointed by the primary admin; reports about the admin are rejected outright.
- **Site settings** - an admin panel for the optional Cloudflare Turnstile CAPTCHA (sign-up/sign-in bot protection), a custom favicon, and the editable Terms of Service (`/terms/`) and Privacy Policy (`/privacy/`) pages
- **Accounts** - signup with email verification, login/logout with "Remember me" persistent sessions, forgot/reset password (a password change logs out every other session and device), email change with re-verification, avatar upload (with an initial-letter fallback avatar when none is set), a choice of themes (system/light/dark/sepia/midnight/sunset), and a preferred emoji skin tone
- **RSS** - a site-wide feed at `/feed.xml` and a per-user feed at `/users/{username}/feed.xml`, auto-discoverable from the relevant pages
- **Relative timestamps** ("3m ago") that stay correct against server time, falling back to an absolute date after 7 days
- **Infinite scroll** for feeds, notifications, message history, and the friends/requests lists
- Everything on the site updates via AJAX/JSON - almost nothing triggers a full page reload

## Requirements

- PHP 8.1+ with the `mysqli`, `gd`, `curl`, `dom`, `libxml`, `fileinfo`, and `mbstring` extensions
- MySQL or MariaDB
- `ffmpeg`/`ffprobe` on `PATH` (for video/audio uploads), with `exec()`/`shell_exec()` enabled
- Outbound HTTPS access (for link preview fetching)
- A web server (e.g. Apache with `mod_rewrite`) pointed at the project root, running the included `.htaccess`
- `bin/websocket-server.php` running as a **separate, long-running process** - live notifications and messaging are a primary feature, not optional, and both `bin/install.php` and the web setup wizard refuse to finish unless it's actually reachable. It's a hand-rolled WebSocket server (no external dependencies) that Apache/PHP-FPM can't run itself, since a normal request can't hold a connection open. See "Running the WebSocket server" below.

## Running the WebSocket server

`bin/websocket-server.php` is a stand-alone daemon (no Composer, no external libraries - hand-rolled RFC 6455 handshake/framing over plain PHP streams) that must already be running before either install path below will finish. It listens on two ports (`WS_PORT`, the public one browsers connect to; `WS_PUSH_PORT`, a loopback-only port `api/*.php` scripts use to hand it events to push out) and never touches the database itself.

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
WorkingDirectory=/path/to/glommer

[Install]
WantedBy=default.target
```

```
systemctl --user daemon-reload
systemctl --user enable --now glommer-websocket.service
loginctl enable-linger "$USER"   # keep it running after logout and start it on boot
```

The `enable-linger` step is essential on a headless server: a user-level service otherwise only runs while that user has an active login session, so without lingering the daemon stops the moment you log out and doesn't come back on reboot. (`bin/install.php` sets this up - unit, enable, and linger - for you when it offers to install the service; you only need to do it by hand for a manual install or if you'd rather run it as a root/system service instead.)

Before `.env` exists yet (a fresh install), it runs with `config.php`'s defaults (`WS_PORT=8090`, `WS_SECRET=change-me`) - that's fine for the install-time connectivity check, since both the daemon and the web process resolve the same defaults with no `.env` in place. Once setup writes a real `.env` (see below), it generates a fresh `WS_SECRET` the already-running daemon doesn't know yet - **restart the service once** after setup completes to pick it up. `bin/install.php` (and the web setup wizard) both perform a real handshake + ping/pong round trip against it, not just a port-open check, and refuse to finish if it isn't reachable.

## Installation

There are two equivalent guided installers - a web setup wizard and an interactive CLI - plus a fully manual path. All three end in the same place: a provisioned database, a least-privilege runtime database account, and a written `.env`.

### Web setup wizard

1. Clone/copy the project to your web server's document root.
2. Make sure the web server user can write to the project root (e.g. `chmod 777 <project root>` - the success page reminds you to restore this).
3. Start the WebSocket server (see above) - it can run with no `.env` in place yet.
4. Visit the site in a browser. Since there's no `.env` yet, you'll land on a setup page instead of the normal site.
   - If any environment prerequisite is missing (PHP version, extensions, `ffmpeg`, writable directories, outbound network, the WebSocket server, etc.), it's reported here - fix it and reload to re-check.
   - Otherwise you'll see a setup form, pre-filled with sensible defaults (the site URL you're visiting by, standard local-MySQL settings), asking for:
     - **Site** - site URL, site title, and the "from" address/name used for outgoing email. A checkbox confirming `ServerName`/`UseCanonicalName` are set is shown too, but only consulted as a fallback - like the CLI, the wizard proves this live with a forged Host header wherever it can (see "HTTPS (required)" below) rather than just trusting the box.
     - **Database** - host, port, database name, and credentials for a MySQL account with `CREATE`/`ALTER`/`DROP`/`CREATE USER`/`GRANT OPTION` privileges (e.g. `root`, or any admin account with those rights)
     - **WebSocket TLS (optional)** - certificate/key paths, only needed if automatic generation fails (see step 5)
5. Submit the form. It proves HTTPS is actually being served and that `ServerName`/`UseCanonicalName` genuinely block Host-header spoofing (the same live checks as the CLI), tries to generate a WebSocket TLS certificate automatically via `mkcert` (falling back to the paths entered above, or an error telling you what to do if neither works), creates the database (if it doesn't already exist), generates a random password for a new least-privilege runtime database account, creates the schema, generates a fresh `WS_SECRET`, and writes `.env` with all of this - none of the admin credentials you entered are ever stored.
6. Follow the numbered checklist on the success page: restore the project root's permissions (`chmod 755 <project root>`), restart the WebSocket server (`systemctl --user restart glommer-websocket`) so it picks up the fresh `WS_SECRET` and TLS certificate, verify Apache's `LimitRequestBody` isn't set below the upload limits (it defaults to unlimited and can't be checked from PHP, so it's not in the automated checks), then reload and sign up - the first account created becomes the site's administrator.

The setup page only ever appears while `.env` doesn't exist. Once it does, a failing database connection shows a maintenance page instead - deliberately, so a database outage on an established site can't be used by a visitor to reconfigure it.

### Interactive CLI

Everything the web wizard does, from a terminal:

```
php bin/install.php
```

- Runs every environment check and reports all of them at once (colored, when the terminal supports it).
- If the WebSocket server is the only thing missing, offers to write the user-level systemd unit itself (correct paths filled in), enable it, start it, and re-check - no manual unit-file editing. If the daemon is reachable but just isn't set up to survive a restart or reboot (not enabled, or lingering isn't set - e.g. it was started manually), offers to fix that specifically too.
- If no backup has ever completed, offers to run one now (proving the mechanism works) and, once it succeeds, set up a nightly systemd timer for it - same idea as the WebSocket unit above. If a backup exists but the timer isn't enabled/lingering isn't set, offers to fix that specifically too.
- Proves HTTPS is actually being served (a real TLS connection to your configured hostname) and that Apache's `ServerName`/`UseCanonicalName` genuinely block Host-header spoofing (a live forged-header test), rather than trusting either from a config string or a blind confirmation - see "HTTPS (required)" below.
- With no `.env` present, walks through the same questions as the web form (with the same defaults), provisions the database/runtime account/schema, and writes `.env`.
- With `.env` present, verifies it, creates any missing tables, and **detects schema drift** - columns, indexes, or foreign keys that `schema.sql` defines but the live tables lack (the situation after upgrading to a newer version) - and offers to apply the exact `ALTER` statements needed.
- Admin credentials are prompted for only when there's actual schema work to do; set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` as environment variables to run non-interactively (e.g. from a deploy script - prompts are skipped automatically when stdin isn't a terminal, and the script exits non-zero on anything unresolved).
- Every self-repair offer above has a matching non-interactive opt-in, for the same kind of scripted/CI runs: `SERVERNAME_CONFIRMED=1`, `BACKUP_TIMER_CONFIRMED=1`, and `WEBSOCKET_SERVICE_CONFIRMED=1`. Each is deliberately named for the one specific thing it attests to rather than being a blanket bypass.

Re-running it on a healthy install is always safe - it changes nothing unless something is missing, and it's the recommended first step after every upgrade.

### Manual

1. Copy `.env.example` to `.env` and fill in `SITE_URL`, `SITE_TITLE`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` for a least-privilege database account, and a real `WS_SECRET` (see `src/config.php` for every key and its default).
2. Create the database and load the schema as a MySQL admin account:
   ```
   mysql -u root -p -e "CREATE DATABASE glommer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p glommer < schema.sql
   ```
   Then create the runtime account and grant it `SELECT, INSERT, UPDATE, DELETE` on that database only - it deliberately doesn't get `CREATE`/`ALTER`/`DROP`.
3. Make sure `uploads/` (and its subdirectories) are writable by the web server user - `bin/install.php` (below) creates them for you if missing.
4. Start the WebSocket server (see above) with this `.env` already in place.
5. Verify everything with `php bin/install.php` (see "Interactive CLI" above - on an already-configured install it acts as a pure checker/repairer).
6. Visit the site and sign up - the first account created becomes the site's administrator.

## Administration model

The **first account created on a fresh install is the site's administrator** - this is how the software operates, not a convention: the admin is always `userId` 1, and admin-only actions (appointing moderators, site settings) check exactly that. The admin can promote any user to **moderator** from their profile; moderators get the reports queue, the Banned Users page, and ban/unban powers, but not site settings or mod appointment. The admin can't be reported or banned.

## Upgrading

The codebase carries a version (`GLOMMER_VERSION` in `src/init.php`) and the database records the version it was last installed or upgraded to. After pulling new code, **run `php bin/install.php`** - it creates any missing tables, applies schema drift, and stamps the new version. Until it runs, a mismatched site locks itself to a maintenance page (a plain "being upgraded" notice for visitors; the admin sees the actual versions and the command to run) rather than serving requests against a schema the code wasn't written for.

If the only thing pending is DML maintenance (no missing tables, schema drift, or index migrations - the common case for most releases), the site upgrades itself silently on the first request after deploy: `Installer::attemptSilentUpgrade()` runs from `init.php`'s version gate using the existing runtime database connection, and the maintenance page never appears at all. If DDL genuinely is pending, it can also apply that automatically - but only when `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` are set as environment variables (the same non-interactive credential `bin/install.php` reads for a scripted upgrade); without them, a pending DDL change still falls back to the maintenance page, since the runtime account deliberately has no `CREATE`/`ALTER`/`DROP` privileges to apply it with. Either way, running `bin/install.php` yourself remains the reliable, visible way to upgrade - the silent path exists so a version bump doesn't need a terminal at all when it doesn't have to.

## Backups

`bin/backup.php` backs up everything a restore needs that git doesn't hold: a gzipped `mysqldump` of the database and a tarball of `uploads/` (originals included). Each run writes a timestamped directory and prunes runs older than the retention window.

```
php bin/backup.php                 # defaults: ../glommer-backups, keep 7 days
BACKUP_DIR=/mnt/backups/glommer BACKUP_KEEP_DAYS=14 php bin/backup.php
```

The backup root must be **outside the project root** (the script refuses otherwise - a web-servable database dump would be a full data breach). Run it nightly with a systemd user timer:

(`bin/install.php` offers to run the first backup and set up this timer - service, timer, enable, and linger - for you, the same way it does for the WebSocket service above; the steps below are for a manual setup or if you'd rather use cron or another scheduler.)

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

(`loginctl enable-linger "$USER"` if you haven't already - same as the WebSocket service.) To restore: create the database, `gunzip -c database.sql.gz | mysql glommer`, untar `uploads.tar.gz` into the project root, and run `php bin/install.php` to verify.

## Email deliverability

Out of the box, mail goes through PHP's `mail()` - the local sendmail. On a typical VPS that mail has no sending reputation and lands in spam folders (or nowhere). For real deliverability, do both of these:

1. **Use an SMTP relay** - set the `SMTP_*` keys in `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION=tls|ssl|none`) and Glommer speaks SMTP to it directly (STARTTLS/implicit TLS and AUTH LOGIN supported, no dependencies). Any transactional provider or your own mail server works.
2. **Publish SPF/DKIM/DMARC DNS records** for the domain in `MAIL_FROM_ADDRESS`, matching what your relay documents (they hand you the exact records to add). Without them, receiving servers have no reason to trust the mail. (If you skip the relay and keep native `mail()`, you own all of this yourself - see below.)

Test with a signup to a mailbox you control before launch. If sending fails outright, Glommer degrades deliberately: a signup whose verification email can't be sent is **verified automatically** (nobody gets stranded behind a gate no email can clear), a password reset is *not* issued without email, and in both cases the **admin gets a "mailer failed" notification** so the outage is visible immediately.

### If you keep the native `mail()` path

Using PHP's `mail()` (no `SMTP_HOST` set) means *your own server* is the sending mail server, so you have to earn its reputation yourself with DNS - a relay normally does all of this for you. Getting to a non-spam inbox from a self-hosted setup means, at minimum, all four of these for the domain in `MAIL_FROM_ADDRESS`:

1. **A working local MTA.** `mail()` just hands off to the local sendmail binary - something has to actually accept and relay that outbound. Install and configure Postfix (or the distro's `sendmail`) to send directly, or to relay through a smarthost. Confirm it works at the shell (`echo test | mail -s test you@elsewhere.com`) before blaming Glommer.
2. **SPF** - a TXT record on the sending domain authorizing your server's public IP, e.g. `v=spf1 ip4:YOUR.SERVER.IP -all`. Without it, receivers can't tell your server is allowed to send for the domain.
3. **DKIM** - a signing key the receiver checks against a published public key. `mail()` does *not* sign anything itself; you need a milter like **OpenDKIM** wired into Postfix to sign outbound mail, plus the matching `..._domainkey` TXT record. This is the step people skip, and it's the one most receivers now require.
4. **DMARC** - a `_dmarc` TXT record (e.g. `v=DMARC1; p=quarantine; rua=mailto:you@yourdomain`) tying SPF/DKIM together and telling receivers what to do with mail that fails both.

Two more that aren't DNS records you publish but matter just as much for a self-hosted sender:

- **PTR / reverse DNS** - the PTR record for your server's IP must resolve back to a hostname on your domain (and forward-confirm). You usually set this at your **hosting/IP provider**, not your DNS host. Many mail servers reject outright on a missing or generic PTR (the default `ec2-…`/`ip-…` style names). A relay owns a warmed IP with correct rDNS already; a fresh VPS does not.
- **Port 25 egress** - many providers block outbound port 25 by default on new accounts; direct-to-MX `mail()` sending is dead in the water until you get it unblocked or relay through a smarthost.

None of this is enforced or checked by Glommer - it's DNS/MTA setup on your infrastructure, outside the app. If that list reads as a lot of moving parts to get right, that's exactly why the **SMTP relay above is the recommended path**: a transactional provider hands you a warmed IP with SPF/DKIM/DMARC/PTR already in place, and you only publish the few records they document.

## HTTPS (required)

Glommer requires HTTPS - it will not serve over plain HTTP. Both installers refuse an `http://` site URL, and on an installed site (once `.env` exists) **every** plain-HTTP request - pages, API calls, and static files alike - is 301-redirected to its https URL (X-Forwarded-Proto is honored behind a TLS-terminating proxy), with HSTS sent on the https side. If `.env` is hand-edited to an `http://` `SITE_URL`, the site refuses to serve and shows a configuration-error page instead. The only thing ever reachable over plain HTTP is the pre-install setup wizard, since TLS may not be configured yet at that point.

The CLI installer doesn't just trust `SITE_URL`'s `https://` prefix - it opens a real TLS connection to your configured hostname (never `127.0.0.1`, since VirtualHost/SNI routing means loopback may not reach the intended site) and only passes if that handshake actually succeeds.

Apache also needs `ServerName <your-host>[:<port>]` and `UseCanonicalName On` set (at `httpd.conf`'s top level if you're not using a `<VirtualHost>`, or inside the relevant `<VirtualHost>` block if you are). Without them, the plain-HTTP-to-HTTPS redirect above can reflect a forged `Host:` header, sending a visitor to an attacker-controlled host instead of your own. The CLI installer proves this live too - it sends a request with a deliberately forged Host header and checks that it isn't reflected back, rather than just asking you to confirm it's set. If that live check is inconclusive (e.g. run from a machine that can't reach the site's own hostname), it falls back to asking you to confirm directly, or accepts `SERVERNAME_CONFIRMED=1` to skip the prompt once you've verified it yourself.

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

  Point Apache's `SSLCertificateFile`/`SSLCertificateKeyFile` at the generated pair. (Fedora alternative: `dnf install mod_ssl` auto-generates a self-signed certificate - functional, but the browser warns, and browsers reject `wss://` to untrusted certificates silently, so prefer mkcert.)

Since pages are https, browsers connect to the WebSocket daemon with `wss://` - give the daemon the same certificate via `WS_TLS_CERT`/`WS_TLS_KEY` in `.env` and restart it.

## Monitoring

`/health` returns `{"ok": true}` (HTTP 200) only when PHP is serving and a real database query succeeds, and an error with HTTP 503 otherwise - point your uptime monitor at it. It deliberately bypasses the normal maintenance/setup pages so a dead database can't masquerade as healthy.

## Design notes

- **Foreign keys**: the content tables (`Posts`, `FeedItems`, `Likes`, `Friendships`, `Blocks`, `Messages`, `Timelines`, `RememberTokens`) carry real foreign keys with `ON DELETE CASCADE`. The system/log tables (`Notifications`, `Reports`, `EmailVerifications`, `PasswordResets`, `RateLimitAttempts`, `ModerationActions`, `Settings`, `LinkPreviews`) deliberately don't - audit history must survive its subjects, and token/attempt rows expire on their own.
- **Static assets revalidate on every load** (`Cache-Control: no-cache` = cache but revalidate). This is intentional: avatars and the custom favicon are overwritten in place under stable URLs, so a browser trusting a stale copy would show the old image indefinitely. The conditional requests are answered 304 and cost almost nothing.
