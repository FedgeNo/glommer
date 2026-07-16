# Glommer

Glommer is a self-hosted social publishing platform - posts, replies, friends,
messaging, live notifications, hashtags, trending topics, and moderation -
built as a plain PHP 8 + MySQL application with **no frontend framework and no
Composer dependencies**. Everything, down to the SMTP client, the WebSocket
daemon, and the HTML renderer, is hand-rolled.

This README is organized into numbered sections. Several messages in the
installer (`bin/install.php`) point here by section number when a step needs
manual follow-up - §6 for TLS, §7 for the background services, §8 for the
trending NER environment, §9 for backups.

**Contents**

1. [What Glommer is](#1-what-glommer-is)
2. [What it does](#2-what-it-does)
3. [Architecture](#3-architecture)
4. [Requirements](#4-requirements)
5. [Installation](#5-installation)
6. [HTTPS & TLS certificates](#6-https--tls-certificates)
7. [Background services](#7-background-services)
8. [The trending NER environment](#8-the-trending-ner-environment)
9. [Backups](#9-backups)
10. [Email deliverability](#10-email-deliverability)
11. [Administration](#11-administration)
12. [Upgrading](#12-upgrading)
13. [Monitoring](#13-monitoring)

---

## 1. What Glommer is

A small, single-server social network you run yourself. One instance is one
community: the first person to sign up becomes its administrator (§11), and
everyone else joins from there. There is no multi-tenancy, no cloud service,
and no external runtime dependencies to install with a package manager beyond
PHP, a database, `ffmpeg` (for media), and - optionally - Python/spaCy for the
trending topic extractor (§8).

It is deliberately a "boring stack, built carefully" project: procedural PHP
page scripts, a thin class hierarchy that renders HTML through DOM (never
string concatenation), prepared statements for every query, and two small
long-running PHP daemons for the things a per-request model can't do (holding
a WebSocket open, transcoding video out of band).

## 2. What it does

- **Posts** - a title, body, an optional link (with an auto-fetched
  title/description/image preview), or attached images/video/audio, all
  composed in a rich-text editor (Quill) with **hashtags**, **@mentions**, and
  math formulas (KaTeX).
- **Replies** - threaded conversations under any post.
- **Likes** and **bookmarks** (bookmarks are private, and never notify).
- **Friends** - requests (send/accept/deny/cancel), a friends-only feed, and
  friend-of-friend suggestions ranked by mutual friends. Friend lists are
  public at `/users/{username}/friends`.
- **@mentions** - tag someone in a post and they're notified. Capped: a post
  mentioning more than 10 distinct people notifies none of them and is
  auto-flagged, since you don't have to be friends to mention someone.
- **Hashtags** - `#tags` are extracted from posts (the first 10 per post are
  indexed), with public tag pages at `/tags/` and a tag graph.
- **Trending topics** - a materialized, decay-scored ranking of what people
  are talking about, at `/trending-topics`. Entities are extracted both from
  hashtags and, when the NER environment (§8) is installed, from post text via
  a spaCy model (people, orgs, places, ...). Moderators can ban an entity from
  trending.
- **Search** - full-text post search and user search.
- **Messaging** - direct conversations, updating **live over WebSocket** when
  the other person replies.
- **Notifications** - live via WebSocket (toast + unseen dot) for likes,
  replies, mentions, friend activity, messages, and media-processing results.
- **Accounts** - signup with email verification; login with "Remember me";
  forgot/reset password; email change (with a revert link mailed to the old
  address); account deletion; a **"Remembered devices"** view in Settings that
  lists each persistent login and lets you revoke one.
- **Two-factor authentication** - opt-in, email-based: when enabled, login
  emails a short-lived code that must be entered to finish signing in.
- **Google Sign-In** - optional OAuth, admin-configured.
- **Moderation** - block users; report a post/message/user; an admin/mod
  reports queue with content snapshots taken at report time.
- **Site settings** (admin) - Cloudflare Turnstile CAPTCHA, SMTP relay, mail
  "from" address, custom favicon, editable Terms of Service and Privacy Policy,
  Google Sign-In credentials, and live status for the background services.
- **Themes** - light, dark, sepia, midnight, and sunset, plus a mobile
  hamburger navigation.
- **RSS** - a site feed at `/feed.xml` and per-user feeds.
- **Everything AJAX** - all updates go over JSON endpoints and update the DOM
  in place; full-page reloads are rare. Every `/api/` endpoint is POST-only and
  CSRF-protected (the one exception is the moderator media-preview stream,
  which must be a GET resource).

## 3. Architecture

- **Web tier** - procedural PHP page scripts at the project root (`index.php`,
  `login.php`, ...) and JSON endpoints under `api/`, routed by `.htaccess`.
  Every "thing" on the site (a post, a report, a banned device) is an
  `HTMLObject` subclass that builds its own DOM via `toDOM()`; the client
  mirrors each one in JavaScript and rebuilds it from the JSON payload, so the
  server never ships HTML fragments over AJAX.
- **Database** - MySQL/MariaDB via `mysqli`, prepared statements only. The app
  runs as a least-privilege account (`SELECT/INSERT/UPDATE/DELETE` only);
  schema changes are done by a separate admin account, only when needed.
- **WebSocket daemon** (`bin/websocket-server.php`) - a hand-rolled RFC 6455
  server (no libraries) that powers live notifications and messaging. Holds no
  database connection.
- **Upload worker** (`bin/upload-worker.php`) - drains a disk-backed queue of
  staged video/audio uploads, transcoding each with `ffmpeg` in an OS-sandboxed
  subprocess, then publishing the post and notifying the author.
- **Trending recompute** (`bin/compute-trending.php`) - periodically rescores
  the trending table; runs on a systemd timer (§7) with a read-path self-heal
  as a fallback.
- **NER extractor** (`bin/ner-extract.py`) - an optional spaCy process the
  trending pipeline shells into for named-entity extraction (§8).
- **Installer** (`bin/install.php`) - see §5.

**Key design choices**

- **Foreign keys** with `ON DELETE CASCADE` enforce consistency: deleting a
  user cascades to their posts, replies, likes, tokens, etc. in one atomic step.
- **Prepared statements everywhere**, with every literal value bound - even
  hardcoded ones - for defense in depth.
- **No HTML over AJAX, no `innerHTML`**: the server renders through DOM and
  endpoints return JSON; the client rebuilds each object with real DOM methods.
- **Media** is transcoded out of band at bounded concurrency; each `ffmpeg` run
  is restricted to the local-file protocol (no SSRF), format-allowlisted, and
  capped on wall-clock/CPU/memory, with source metadata stripped.
- **Passwords** use bcrypt (`password_hash`), transparently re-hashed to the
  current cost on login.
- **Static assets revalidate every load** (`Cache-Control: no-cache`) since
  avatars/favicon are overwritten in place under stable URLs.

## 4. Requirements

- **PHP 8.1+** with `mysqli`, `gd`, `curl`, `dom`, `libxml`, `fileinfo`, and
  `mbstring`. The daemons also use `pcntl` and `sockets`, and the installer's
  lingering fallback uses `posix`.
- **MySQL or MariaDB.**
- **A web server** (Apache with `mod_rewrite` is the tested path) pointed at
  the project root, serving the included `.htaccess`, over **HTTPS** (§6).
- **For video/audio uploads**: `ffmpeg`, `ffprobe`, `timeout` (coreutils), and
  `bash` on `PATH`, with `exec()`/`shell_exec()` enabled. Each transcode runs
  sandboxed under wall-clock, CPU, and memory limits. If `exec()`/`shell_exec()`
  are disabled for the web SAPI, either re-enable them (remove them from the
  pool's `disable_functions`) or provision media handling by hand.
- **Outbound HTTPS** (for link-preview fetching).
- **Optional, for smarter trending**: Python 3 (with `pip`/`venv`/dev headers)
  and a C++ compiler, so the installer can build the spaCy environment (§8).
- **Two background daemons plus a timer**, all separate from the web server and
  all set up for you by the installer (§7).

## 5. Installation

There are two equivalent guided installers - a web setup wizard and an
interactive CLI - plus a fully manual path. All three end in the same place: a
provisioned database, a least-privilege runtime account, and a populated
schema.

**Run the installer as root (via `sudo`) when you can.** As root it installs
real *system* systemd services (no lingering needed), auto-installs missing
prerequisite packages, builds the trending NER environment (§8), and relocates
TLS certs to a readable location. Without root it falls back to user-level
services and prints manual steps.

### Web setup wizard

1. Copy the project to your web root and make it writable by the web-server
   user (the success page reminds you to restore permissions afterward).
2. Start the WebSocket server (§7) - it runs fine with no `.env` yet.
3. Visit the site. With no `.env`, you get a setup page: it reports any missing
   prerequisite (fix and reload), then a form for the site URL/title/mail-from,
   database admin credentials, and optional WebSocket TLS paths.
4. Submit. It proves HTTPS is live, that `ServerName`/`UseCanonicalName` block
   Host-header spoofing, generates a WebSocket TLS cert with mkcert if needed,
   and provisions the database.
5. Follow the success checklist: restore permissions, restart the WebSocket
   server, and sign up - the first account becomes the administrator (§11).

The setup page only appears while `.env` is absent; afterward a DB outage shows
a maintenance page instead, so it never invites re-installation.

### Interactive CLI

```
sudo php bin/install.php
```

Runs every environment check at once; offers to set up each background service
and the backup timer when they're missing; proves HTTPS and the anti-spoofing
config live; on a fresh box walks the same questions as the web form and writes
`.env`; on an existing box verifies it, creates missing tables, and detects
**schema drift** (columns/indexes/foreign keys `schema.sql` defines that the
live tables lack). Admin DB credentials are only prompted when there's actual
schema work; set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` to run
non-interactively. Re-running on a healthy install changes nothing, and is the
recommended first step after every upgrade (§12).

### Manual

1. Copy `.env.example` to `.env` and fill in `SITE_URL`, `SITE_TITLE`,
   `MAIL_FROM_ADDRESS`, and the least-privilege `DB_*` credentials.
2. As a DB admin account, create the database (`utf8mb4`/`utf8mb4_unicode_ci`),
   load `schema.sql`, then create the runtime account with only
   `SELECT, INSERT, UPDATE, DELETE` on it.
3. Ensure `uploads/` is writable by the web-server user.
4. Start the WebSocket server and upload worker (§7).
5. `php bin/install.php` to verify/repair, then sign up.

## 6. HTTPS & TLS certificates

**Glommer requires HTTPS and will not serve over plain HTTP.** Both installers
refuse an `http://` site URL, and on an installed site every plain-HTTP request
is 301-redirected to `https://`. The CLI proves this with a real TLS connection
to your configured hostname (never `127.0.0.1` - VirtualHost/SNI routing means
loopback may not reach this site).

**Apache anti-spoofing config (required).** Set `ServerName <your-host>[:port]`
and `UseCanonicalName On` - at `httpd.conf`'s top level if you aren't using a
`<VirtualHost>`, or inside the relevant `<VirtualHost>` if you are. Without
both, the HTTPS redirect can be pointed at an attacker's host via a forged
`Host` header. The installer sends a forged-Host request and refuses to
continue until this is genuinely in place (set `SERVERNAME_CONFIRMED=1` to
assert it in a non-interactive run).

**Getting a certificate.** The cert lives in your web server, not in Glommer.

- **Real domain** - Let's Encrypt via certbot:
  ```
  sudo dnf install certbot python3-certbot-apache   # or the apt equivalent
  sudo certbot --apache -d your.domain
  ```
  When run as root and it can identify Apache/nginx, the installer will obtain
  and install a cert for you - **scoped to the `<VirtualHost>`/`server` block
  whose `ServerName`/`server_name` matches your host**, so other sites on a
  multi-site box are never touched.
- **localhost / development** - public CAs can't issue for localhost; use a
  locally-trusted cert. `mkcert` is smoothest:
  ```
  sudo dnf install mkcert nss-tools
  mkcert -install
  mkcert localhost
  ```
  Point Apache's `SSLCertificateFile`/`SSLCertificateKeyFile` at the pair.
  (Fedora alternative: `dnf install mod_ssl` for a self-signed cert - works,
  but the browser warns.)

**WebSocket over TLS.** Because pages are HTTPS, browsers open the daemon with
`wss://`. Give it a cert via `WS_TLS_CERT`/`WS_TLS_KEY` in `.env` and restart
it. Reuse your public-CA cert for a real domain; for mkcert, point both at the
locally-trusted pair. As root the installer relocates the cert to
`/etc/glommer` (readable by the daemon's own account) and, on a real domain,
installs a renewal hook that re-copies the cert and restarts the daemon after
each certbot renewal. If that hook can't be written, recopy the cert by hand
after each renewal.

## 7. Background services

Glommer needs three scheduled/long-running jobs, all **separate from the web
server**. As root, `bin/install.php` installs them as **system** systemd units
(started on boot, run as the web-server/daemon accounts). Without root it
installs **user-level** units and enables lingering so they survive logout.

| Service | What it does | Unit |
| --- | --- | --- |
| WebSocket server | live notifications & messaging (§3) | `glommer-websocket.service` |
| Upload worker | transcodes queued media (§3) | `glommer-upload-worker.service` |
| Trending recompute | rescores trending every ~15 min | `glommer-trending.timer` |

The installer offers to create, enable, and health-check each one, and keeps
every unit in sync with its current template on each run. If it reports **"no
usable `systemctl --user` session"** (e.g. under a bare `sudo -u` or a
non-interactive SSH command), either re-run from a real login session, run the
daemon under your own process manager, or run as root for system units.

**Manual user-level setup** (if you're not using the installer). For each
daemon, create `~/.config/systemd/user/<unit>` and enable it:

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
loginctl enable-linger "$USER"   # survive logout / start on boot
```

The upload worker is identical (swap the ExecStart to `bin/upload-worker.php`,
drop `RuntimeMaxSec`). `enable-linger` is **essential on a headless server** -
a user service otherwise stops the moment you disconnect. `WatchdogSec` lets
systemd restart a hung event loop; `RuntimeMaxSec=1d` recycles the WS daemon
daily. Note: the daemons load code into memory at start, so after pulling new
code that touches a daemon (or a class it autoloads) you must
`systemctl restart` it - a code pull alone does not reload them.

## 8. The trending NER environment

Trending (§2) always works from hashtags. For richer topics (people, orgs,
places, ...) extracted from post text, Glommer shells into a
[spaCy](https://spacy.io) model. This is optional: without it, trending simply
uses hashtags, and `EntityExtractor` fails closed to that.

Run as root, the installer builds an isolated virtualenv at
**`/opt/glommer-ner`** - installing `python3`/`pip`/`venv`/dev-headers and a
C++ compiler, then `spacy` + `click` + the `en_core_web_sm` model - and labels
it for SELinux where applicable. The web-server user execs into it directly.

If the installer can't do it automatically (**unknown package manager**), set
it up by hand:

```
sudo dnf install python3 python3-pip python3-devel gcc-c++   # or your equivalent
sudo python3 -m venv /opt/glommer-ner
sudo /opt/glommer-ner/bin/pip install -U pip wheel spacy click
sudo /opt/glommer-ner/bin/python -m spacy download en_core_web_sm
# make it world-readable/executable so the web-server user can exec it:
sudo chmod -R a+rX /opt/glommer-ner
```

`en_core_web_sm` and spaCy are MIT-licensed. On an SELinux-enforcing host the
venv needs `httpd_sys_content_t`, and its compiled `.so` files need
`textrel_shlib_t` (they use text relocation) - the installer applies both.

## 9. Backups

`bin/backup.php` writes what a restore needs that git doesn't hold: a gzipped
`mysqldump` and a tarball of `uploads/`, into a timestamped directory, pruning
older runs.

```
php bin/backup.php                 # defaults: ../glommer-backups, keep 7 days
BACKUP_DIR=/mnt/backups/glommer BACKUP_KEEP_DAYS=14 php bin/backup.php
```

The backup root **must be outside the project root** (the script refuses
otherwise - a web-servable DB dump is a full breach). The installer offers to
run the first backup and schedule a nightly timer (`glommer-backup.timer`); if
it can't (**no `systemctl --user` session**, or you declined), schedule
`php bin/backup.php` yourself with cron or a manual systemd timer:

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
loginctl enable-linger "$USER"
```

**Restore**: create the database, `gunzip -c database.sql.gz | mysql glommer`,
untar `uploads.tar.gz` into the project root, visit the site.

## 10. Email deliverability

Out of the box mail goes through PHP's `mail()`, which on a typical VPS has no
sending reputation and lands in spam. For real deliverability:

1. **Use an SMTP relay** - set host/port/username/password/encryption in the
   admin Site Settings → Mail section (live, no restart). Glommer speaks SMTP
   directly; a failed send is reported immediately.
2. **Publish SPF/DKIM/DMARC** for the `MAIL_FROM_ADDRESS` domain, matching what
   your relay documents.

If sending fails outright, Glommer degrades deliberately: a signup whose
verification email can't be sent is verified automatically rather than being
stranded, and the admin is notified the mailer is down. If you insist on the
native `mail()` path, you own the full self-hosted-sender checklist (a working
local MTA, SPF, DKIM via OpenDKIM, DMARC, PTR/reverse DNS, and port-25 egress) -
which is exactly why the relay approach is recommended.

## 11. Administration

The **first account created on a fresh install is the administrator** - this is
structural, not a convention: the admin is always `userId` 1. Admin-only
actions (appointing/revoking moderators, editing site settings, Google/Turnstile
config) are theirs alone; general moderators can work the reports queue, ban
users, and ban trending entities.

## 12. Upgrading

The codebase carries `GLOMMER_VERSION` (in `src/init.php`) and the database
records the version it was last installed/upgraded to. After pulling new code,
**run `php bin/install.php`** to apply schema changes and record the version.
If the only pending work is DML maintenance (the common case), the site
upgrades itself silently on the first request, protected by `ignore_user_abort()`.
Remember to `systemctl restart` the daemons after any pull that touches their
code (§7).

## 13. Monitoring

`/health` returns `{"ok": true}` (200) only when PHP is serving, a real DB
query succeeds, and the WebSocket server and upload worker are not confirmed
down; it returns 503 otherwise. It bypasses the version gate, so it works even
mid-upgrade. Point an uptime monitor at it.
