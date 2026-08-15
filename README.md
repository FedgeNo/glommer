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
string concatenation), prepared statements for every query, and three small
long-running PHP daemons for the things a per-request model can't do (holding
a WebSocket open, and transcoding video and delivering federation out of
band).

## 2. What it does

- **Posts** - a title, body, an optional link (with an auto-fetched
  title/description/image preview), or attached images/video/audio, all
  composed in a rich-text editor (Quill) with **hashtags**, **@mentions**, and
  math formulas (KaTeX).
- **Replies** - threaded conversations under any post. A reply appears in the
  friends, profile and tag feeds too, headed by what it answers and, in a
  longer thread, a link back to the post that began it - so a conversation is
  followable without opening every post it hangs from. The signed-out main feed
  carries only posts that start something, since a reply's context may be a
  post on another server that a visitor is not shown.
- **Likes** and **bookmarks** (bookmarks are private, and never notify).
- **Polls** - attach up to four options to a post, single- or multiple-choice,
  with a fixed run time. Votes are final, results replace the ballot once
  you've answered or it closes, and polls federate in both directions as
  ActivityPub `Question`s.
- **Reposts** - pass someone's post to your friends and Fediverse followers;
  it appears on your profile and in their feeds, attributed and sorted by when
  you passed it on. Remote boosts of local posts count in the same tally.
- **Pinned posts** - pin up to five of your own posts to the top of your
  profile; pins federate as the standard `featured` collection.
- **Sensitive media** - authors (or moderators) can classify a post's media as
  sensitive; it renders behind a no-JS `<details>` cover, travels both ways
  over ActivityPub, and every member can opt out of the cover in Settings.
- **Friends** - requests (send/accept/deny/cancel), a friends-only feed, and
  friend-of-friend suggestions ranked by mutual friends. Friend lists are
  public at `/users/{username}/friends`.
- **@mentions** - tag someone in a post and they're notified. Capped: a post
  mentioning more than 10 distinct people notifies none of them and is
  auto-flagged, since you don't have to be friends to mention someone.
- **Hashtags** - `#tags` are extracted from posts (the first 10 per post are
  indexed), with public tag pages at `/tags/` and a tag graph.
- **Trending topics** - a materialized, decay-scored ranking of what people
  are talking about, at `/topics/` - and nested under it by kind, so
  `/topics/person/` is the people and `/topics/person/ada-lovelace` is one of
  them with the posts mentioning it. Entities are extracted both from
  hashtags and, when the NER environment (§8) is installed, from post text via
  a spaCy model (people, orgs, places, ...). It reads everything the server can
  hear, posts arriving from other servers included, so a server carrying a
  relay ranks the wider conversation rather than only what was written locally.
  An entity needs several distinct authors before it can appear at all, and
  moderators can ban one from trending outright.
- **Search** - full-text post search and user search.
- **Messaging** - direct conversations, updating **live over WebSocket** when
  the other person replies. Conversations between two members who have turned
  on **end-to-end encryption** (Settings → Encrypted Messages) are encrypted
  in the browser with WebCrypto - the server relays and stores ciphertext, so
  the database, the backups and the wire carry nothing readable. Since the
  server is what introduces the two browsers to each other's public keys, each
  encrypted thread also shows a **safety code** over that pair of keys, worked
  out in the browser: two people who read it to each other over any other
  channel can see that nothing was substituted, and can mark it verified so a
  later change is called out. That leaves only the page itself as something to
  trust - a server serving modified code is outside what any of this can check.
  Each member's private key is wrapped under a passphrase (PBKDF2, one million
  iterations) that never leaves their device; the wrapped blob is stored
  server-side, so the same passphrase unlocks the history from any browser -
  and losing it loses the encrypted history, with no operator recovery. That
  property is also the deliberate limit of the design: because any browser can
  still open old messages, the keys that open them still exist, so there is no
  forward secrecy and a passphrase learned later exposes the whole history.
  Discarding old keys is the only way to buy that, and it would mean a new
  browser starting from an empty conversation - the opposite of what this is
  for. The passphrase is therefore held to a higher bar than a password (12
  characters minimum, and refused outright if it matches the account password,
  which the server does see). Reporting still works via
  **message franking**: the server HMACs each ciphertext as it relays it, and
  a report reveals that one message's key (never the conversation's), which
  the server verifies against its own tag before trusting the plaintext. Each
  tag records which server key made it: if `ACTIVITYPUB_ENCRYPTION_KEY` is ever
  rotated, move the old value to `ACTIVITYPUB_ENCRYPTION_KEY_PREVIOUS` in
  `.env` (comma-separated, oldest last) or every message franked under it
  becomes unreportable.
  Everything else is stored in plain text - private from other members, but
  the operator of a server can technically read their own database, and a
  conversation with a Fediverse account also lives on that account's server,
  where its operator can too (ActivityPub has no message encryption, so
  federated threads can never be end-to-end encrypted). The thread says which
  case applies, whichever it is.
  A received message (never one of your own, and never automatic) can be
  translated with one click - the same button that's on posts. Nothing is
  cached, but a one-time notice says the words are read by the server to do
  it; in an end-to-end encrypted thread specifically, that means the one
  message translated stops being end-to-end encrypted the way the rest of the
  conversation is, since it's the plaintext already open in the browser that
  gets sent.
- **Video calls** - one-to-one, peer-to-peer WebRTC from an open message
  thread. Media never touches the server: STUN only, no TURN relay - if the
  two browsers can't reach each other directly the call simply isn't offered.
  Settings carries a per-browser diagnostic that says which setup step fails.
- **Notifications** - live via WebSocket (toast + unseen dot) for likes,
  replies, mentions, friend activity, messages, and media-processing results.
- **Email digests** - after a week away, one mail saying what you missed, at
  most one a week and never when nothing happened. One-click unsubscribe that
  needs no password (§10).
- **Accounts** - signup with email verification; login with "Remember me";
  forgot/reset password; email change (with a revert link mailed to the old
  address); account deletion; a **"Remembered devices"** view in Settings that
  lists each persistent login and lets you revoke one.
- **Two-factor authentication** - opt-in, email-based: when enabled, login
  emails a short-lived code that must be entered to finish signing in.
- **Google Sign-In** - optional OAuth, admin-configured.
- **Geotagged posts** - optionally attach your location to a post; a site map
  clusters every located post, each card links to the spot, and a **Nearby**
  feed ranks posts by distance from you. Coordinates are stored exactly and
  only when you choose to attach them.
- **Moderation** - block users; report a post/message/user; an admin/mod
  reports queue with content snapshots taken at report time.
- **Admin settings** - Cloudflare Turnstile CAPTCHA, SMTP relay, mail
  "from" address, custom favicon, editable Terms of Service and Privacy Policy,
  Google Sign-In credentials, and live status for the background services.
- **Themes** - a wide set of built-in looks plus Match System, picked in
  Settings, and a mobile hamburger navigation.
- **RSS** - a site feed at `/feed.xml` and per-user feeds.
- **Fediverse federation (ActivityPub)** - every member is an actor at
  `@username@your-host`, followable from Mastodon, Threads, and the rest of
  the network; interoperation is verified live against both. Members follow
  remote accounts from Settings. Posts (with media, polls, and sensitive
  flags), replies, likes, boosts, pins, profile updates, account migrations
  (`Move`, both directions), blocks and abuse reports all federate; deliveries
  are queued and retried by a worker, remote media is proxied per-request
  rather than hotlinked or copied, and signing keys rotate cleanly on both
  sides. Remote accounts and their posts are visible to logged-in members
  only - this server never re-publishes another server's content to the open
  web. Admins and moderators can defederate whole domains (Blocked Servers),
  which severs existing follows both ways.
- **Relays** - optional, off until an admin subscribes to one. A relay is a
  shared firehose: every public post from every other subscribed server
  arrives, and this server's go out to all of them, which is how a new
  instance finds anyone at all when nobody is following it yet. What arrives
  goes to a **Relay Feed** of its own rather than into the main or friends
  feeds, and the link to it only appears once a relay is actually subscribed.
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
- **Federation worker** (`bin/federation-worker.php`) - drains the outbound
  ActivityPub delivery queue, signing each activity as the member it's from;
  also fetches posts a subscribed relay has named, delivers Web Push
  notifications, and sends the trickle of email digests (§10).
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
- **Outbound HTTPS** (for link-preview fetching, and for the installer to
  download the place directory below).
- **`unzip`**, so the installer can load the place directory: the
  [GeoNames](https://www.geonames.org/) gazetteer (CC BY 4.0), around 10MB
  downloaded once, which is what lets a post's location read as a place name
  instead of raw coordinates - resolved locally, with no
  geocoding service in the request path. If the download fails the installer
  says so and the site runs fine without it (locations show as coordinates);
  re-run `php bin/install.php` to retry.
- **Optional, for smarter trending and for translation**: Python 3 (with
  `pip`/`venv`), so the installer can build the NER environment (§8) and the
  translation environment (§6); only the NER build additionally needs dev
  headers and a C++ compiler.
- **The `intl` extension** (`php-intl`), for any site offering a language that
  does not count the way English does. How a language counts is a fact about
  the language, so it is read from CLDR rather than written down here: `intl`
  answers it on the server and `Intl.PluralRules` answers it in the browser,
  from the same data. Without it, counting falls back to English's two forms -
  the site runs, but Polish and Arabic read wrong for the numbers their extra
  forms exist for. It is also where `bin/translate-strings.php` reads month
  names and the order of a date's parts, neither of which is a thing to ask a
  model for: asked, it answers "Januaro Januaro Januaro".
- **Free disk space: 25GB minimum, 500GB or more recommended.** The minimum is
  what it takes to install and try the thing: most of it is the translation
  environment, which is PyTorch plus a model for every language pair Argos
  publishes (§6, skippable). The recommendation is for a site with members on
  it - uploads are kept at full size alongside their transcodes, and a video
  is the largest thing anybody posts.
- **Three background daemons plus a timer**, all separate from the web server
  and all set up for you by the installer (§7).

## 5. Installation

There are two equivalent guided installers - a web setup wizard and an
interactive CLI - plus a fully manual path. All three end in the same place: a
provisioned database, a least-privilege runtime account, and a populated
schema.

**Run the installer as root (via `sudo`) when you can.** As root it installs
real *system* systemd services (no lingering needed), auto-installs missing
prerequisite packages, builds the trending NER environment (§8) and the
translation environment (§6), and relocates TLS certs to a readable location.
Without root it falls back to user-level services and prints manual steps.

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

1. Copy `.env.example` to `.env` and fill in `SITE_URL`, `SITE_TITLE`, and the
   least-privilege `DB_*` credentials. The mail "from" address and SMTP relay
   are no longer `.env` settings - they live on the Admin Settings page
   instead (step 5 prompts for the "from" address the first time it's unset
   anywhere).
2. As a DB admin account, create the database (`utf8mb4`/`utf8mb4_unicode_ci`),
   load `schema.sql`, then create the runtime account with only
   `SELECT, INSERT, UPDATE, DELETE` on it.
3. Ensure `uploads/` is writable by the web-server user.
4. Start the WebSocket server, upload worker, and federation worker (§7).
5. `php bin/install.php` to verify/repair, then sign up.

### Choose `SITE_URL` once and keep it

Decide before you install whether the site lives at `example.org` or
`www.example.org`, and do not change it afterwards. `SITE_URL` is not a display
setting - it is the root every address on the site is built from.

Changing it invalidates every link that has ever left the server: bookmarks,
search-engine results, the links in mail already sent, and every RSS item. It
also breaks federation permanently rather than temporarily. A member's
ActivityPub actor is `SITE_URL/users/{username}/`, and other servers store that
string when they follow. Move the site and every remote follower is pointing at
an address that no longer answers, with no way to discover where it went - the
only recovery is for every follower on every server to find and follow the new
account by hand.

Adding or dropping `www.` counts as changing it. If both hostnames must work,
serve one and redirect the other to it, so there is still exactly one address
the site calls its own.

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

**Skipping the translation environment.** The installer builds a Python venv
with PyTorch and a model for every language pair Argos publishes - around a
hundred packages, ten gigabytes, and a long download. An installation that will
never offer the translate button can set `SKIP_TRANSLATE=1` and the installer
leaves it alone, saying so in its output; translation stays unavailable there
until it is run again without the flag. Everything else installs as normal.

**Translating the interface.** The same packages write Glommer's own wording.
English lives in `locales/en.json` and every other locale is made from it:

```
php bin/translate-strings.php                 # everything that has changed
php bin/translate-strings.php --force pl      # one locale, from scratch
php bin/add-string.php PostCard.replies "{count} replies"
```

Both are CLI-only and need the translation environment above, so run them where
it is installed and commit the locale files that come back. Only strings whose
English has been edited since the last run are translated, so a rerun with
nothing changed does nothing and a correction made by hand is never overwritten
- `--force` is how to ask for a language again. A locale is written with
whatever translated and left without the rest, since English stands in per key.
Read what comes back before committing it: the models are close enough to be
worth it and wrong often enough to need an eye.

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

Glommer needs four scheduled/long-running jobs, all **separate from the web
server**. As root, `bin/install.php` installs them as **system** systemd units
(started on boot, run as the web-server/daemon accounts). Without root it
installs **user-level** units and enables lingering so they survive logout.

| Service | What it does | Unit |
| --- | --- | --- |
| WebSocket server | live notifications & messaging (§3) | `glommer-websocket.service` |
| Upload worker | transcodes queued media (§3) | `glommer-upload-worker.service` |
| Federation worker | delivers queued ActivityPub activities, with backoff and retry (§3) | `glommer-federation-worker.service` |
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
drop `RuntimeMaxSec`). The federation worker is similar but not identical
(swap the ExecStart to `bin/federation-worker.php`, drop `WatchdogSec` and
`RuntimeMaxSec`, and use `RestartSec=5`) - it pings no watchdog and holds no
state between passes, so a plain restart is its whole recovery story.
`enable-linger` is **essential on a headless server** -
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
C++ compiler, then `spacy` + `click` + `langdetect`, plus one small spaCy
model per supported language (`en_core_web_sm`, `de_core_news_sm`, and so on -
nine in all, one per language `bin/ner-extract.py --models` lists) - and
labels it for SELinux where applicable. The web-server user execs into it
directly.

If the installer can't do it automatically (**unknown package manager**), set
it up by hand:

```
sudo dnf install python3 python3-pip python3-devel gcc-c++   # or your equivalent
sudo python3 -m venv /opt/glommer-ner
sudo /opt/glommer-ner/bin/pip install -U pip wheel spacy click langdetect
sudo /opt/glommer-ner/bin/python -m spacy download en_core_web_sm
# ...and again for each other language bin/ner-extract.py --models lists
# make it world-readable/executable so the web-server user can exec it:
sudo chmod -R a+rX /opt/glommer-ner
```

spaCy and its language models are MIT-licensed. On an SELinux-enforcing host the
venv needs `httpd_sys_content_t`, and its compiled `.so` files need
`textrel_shlib_t` (they use text relocation) - the installer applies both.

## 9. Backups

`bin/backup.php` writes what a restore needs that git doesn't hold: a gzipped
`mysqldump` and a tarball of `uploads/`, into a timestamped directory, pruning
older runs.

```
php bin/backup.php                 # defaults: ../glommer-backups, keep 3 days
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

**Restore**: `bin/restore.php` puts a run back - the database and the uploads
tree together.

```
php bin/restore.php                                   # what it would do, and nothing else
php bin/restore.php 2026-08-08_085356                 # ditto, for a named run
GLOMMER_RESTORE_CONFIRMED=1 sudo php bin/restore.php  # go ahead, newest run
```

Without the confirmation it only reports; nothing is changed. A run is named,
never given as a path, and the name is resolved under this install's own
backup root - so no argument reaches another server's backups. Run it as root
(or set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD`): the dump drops and recreates
every table, which the least-privilege runtime account cannot do.

The uploads tree is moved to `uploads.before-restore-<timestamp>` rather than
deleted, so a restore onto the wrong install is survivable; remove it once
you're satisfied. If the backup predates the code, the database comes back a
version behind and the site holds a maintenance page until `bin/install.php`
catches it up.

**Rehearse it.** A backup nobody has restored is not known to be a backup.
Restore on a development machine, from that machine's own backup - never
production's database onto anywhere else.

## 10. Email deliverability

Out of the box mail goes through PHP's `mail()`, which on a typical VPS has no
sending reputation and lands in spam. For real deliverability:

1. **Use an SMTP relay** - set host/port/username/password/encryption in
   Admin Settings → Mail section (live, no restart). Glommer speaks SMTP
   directly; a failed send is reported immediately.
2. **Publish SPF/DKIM/DMARC** for your configured mail "from" address's
   domain, matching what your relay documents.

**Email digests** are the one mail Glommer sends that nobody asked for
individually, so they are held to tighter rules. A member gets one only after a
week away with a week since their last, only when something actually happened
while they were gone, and never once they have said no. Every one carries a
one-click unsubscribe link that works with no password and never expires, and
the `List-Unsubscribe` pair (RFC 8058) so Gmail and Apple Mail offer their own
unsubscribe button beside the sender's name - which the large mailbox providers
now expect of anything sending in bulk. On an installation with no
`ACTIVITYPUB_ENCRYPTION_KEY` no such link can be signed, so no digest is sent
at all. Members switch them off under Settings → Email
Digests, and the admin can add a closing paragraph of the server's own under
Admin Settings → Email Digest. Where an OpenRouter key is configured, the mail
opens with a short written summary of what has been posted; without one it is
simply the list of what was missed. They go out a trickle at a time from the
federation worker, so a site full of dormant accounts never turns into a mail
run.

If sending fails outright, Glommer degrades deliberately: a signup whose
verification email can't be sent is verified automatically rather than being
stranded, and the admin is notified the mailer is down. If you insist on the
native `mail()` path, you own the full self-hosted-sender checklist (a working
local MTA, SPF, DKIM via OpenDKIM, DMARC, PTR/reverse DNS, and port-25 egress) -
which is exactly why the relay approach is recommended.

## 11. Administration

The **first account created on a fresh install is the administrator** - this is
structural, not a convention: the admin is always `userId` 1. Admin-only
actions (appointing/revoking moderators, editing admin settings, Google/Turnstile
config) are theirs alone; general moderators can work the reports queue, ban
users, ban trending entities, and defederate whole domains from the
**Blocked Servers** section of Mod Settings - a domain block refuses that
server's deliveries, stops all fetches to it, and severs existing follows in
both directions.

**Relays** (Admin Settings, admin only) subscribe this server to a shared
firehose. Weigh it before subscribing: the volume is whatever the servers on
the other side publish - quiet one week, thousands of posts an hour the next -
and your storage, delivery queue and moderation queue all carry it. Subscribing
sends a Follow signed by the instance and the relay answers it, so a
subscription sits at "waiting" until it does. Relay software disagrees about
whether that Follow should name the relay's own actor or the public stream, so
the form offers both; if one is never accepted, withdraw and try the other.
Unsubscribing stops new posts and leaves the ones already here.

## 12. Upgrading

The codebase carries `GLOMMER_VERSION` (in `src/init.php`) and the database
records the version it was last installed/upgraded to. After pulling new code,
**run `php bin/install.php`** to apply schema changes and record the version.
The site also attempts this itself while the database lags behind - at most
once a minute, since what stops an upgrade is still true a moment later -
protected by `ignore_user_abort()`: DML maintenance and data backfills always
apply silently; schema changes (missing tables, drift, index migrations) do
too, but only when `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` are set in `.env`
for a non-interactive admin connection. Without those, a pending
schema change falls back to the maintenance page until someone runs
`bin/install.php` by hand. Remember to `systemctl restart` the daemons after
any pull that touches their code (§7).

## 13. Monitoring

`/health` returns `{"ok": true}` (200) only when PHP is serving, a real DB
query succeeds, and the WebSocket server and upload worker are not confirmed
down; it returns 503 otherwise. It bypasses the version gate, so it works even
mid-upgrade. Point an uptime monitor at it.

## 14. What defends the site

Running a server means being answerable for what happens on it. This is what
the software already does, so you know what you are relying on - not a promise
that nothing can go wrong.

**HTTPS is required, not preferred** (§6). Plain HTTP is redirected, HSTS is
sent, and the canonical-host check means a forged `Host` header cannot bounce
your visitors somewhere else.

**Passwords** are hashed with `password_hash()` at PHP's current default, and
re-hashed on the next login whenever that default changes. Changing a password
bumps a session version that invalidates every other session on the account.

**"Remember me" cookies** are a selector plus a validator, and only a SHA-256
of the validator is stored - the database cannot be read for a working cookie.
Each one is single-use and rotates as it is spent, so a cookie arriving with a
known selector and the wrong validator means a copy is in circulation, and
every token on that account is revoked.

**CSRF** is checked in one place, `init.php`, on every POST. Two endpoints are
exempt because they cannot carry a token and were never meant to, and each
proves itself another way instead: the ActivityPub inbox (HTTP signature) and
one-click unsubscribe (the RFC 8058 body token).

**Cross-site scripting** has no route in through content. The browser code
never assigns `innerHTML` - every node is built with `createElement` and
`textContent` - and a post is stored as a Delta sanitized to an allowlist of
formats, with link schemes checked and control characters stripped before the
scheme is read.

**SQL** is prepared statements throughout, with every value bound, including
hardcoded ones. The account the site runs as holds `SELECT`, `INSERT`,
`UPDATE` and `DELETE` and nothing else; schema changes need the separate admin
account (§12).

**Uploads** are accepted on their container format rather than their file
extension. Transcoding runs `ffmpeg` behind a protocol allowlist so it cannot
be talked into reading local files, under a wall-clock timeout, a CPU limit and
an address-space cap. Source dimensions are checked before decode, so a
decode bomb is refused rather than expanded, and metadata is stripped from
what gets published. The original is kept privately, out of the web root, so a
report about deleted media still has something to show a moderator.

**Outbound fetches** - link previews, remote avatars, federation - resolve the
hostname first, refuse every private and reserved address, and pin curl to the
address that was validated, so a name that answers differently between the
check and the fetch cannot reach anything on your network. Each redirect goes
through the same check before it is followed.

**Deliveries from other servers** must carry an HTTP signature covering the
request target, host, date and digest. The digest is checked against the body
actually received, the date against a bounded clock window, and a signature is
accepted once. An object has to belong to the server that signed for it, and
`attributedTo` has to match the signer.

**Encrypted messages** are HMAC-tagged by the server as it relays each one, so
a report can be checked against what was really sent rather than against what
a client claims was sent. The tag records which key made it, so the key can be
rotated without orphaning old messages.

**Rate limits** cover logging in, signing up, resetting a password, posting,
messaging, reporting, translating and federated delivery. Login is limited by
IP and by account at once, so neither a spread-out attempt nor a targeted one
gets a free run.
