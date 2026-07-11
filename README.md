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
- **Moderation** - blocking users; reporting a specific post, message, or user; and an admin/mod reports queue that shows the reported content itself (a reported message's body in a blockquote, a post rendered inline, a user's card) with per-report Dismiss, Delete Content, and Ban Reporter/Reported User actions. Moderators are appointed by the primary admin; reports about the admin are rejected outright.
- **Accounts** - signup with email verification, login/logout, forgot/reset password, avatar upload (with an initial-letter fallback avatar when none is set), a choice of themes (system/light/dark/sepia/midnight/sunset), and a preferred emoji skin tone
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
     - **Site** - site URL, site title, and the "from" address/name used for outgoing email
     - **Database** - host, port, database name, and credentials for a MySQL account with `CREATE`/`ALTER`/`DROP`/`CREATE USER`/`GRANT OPTION` privileges (e.g. `root`, or any admin account with those rights)
5. Submit the form. It creates the database (if it doesn't already exist), generates a random password for a new least-privilege runtime database account, creates the schema, generates a fresh `WS_SECRET`, and writes `.env` with all of this - none of the admin credentials you entered are ever stored.
6. Follow the numbered checklist on the success page: restore the project root's permissions (`chmod 755 <project root>`), restart the WebSocket server (`systemctl --user restart glommer-websocket`) so it picks up the fresh `WS_SECRET`, verify Apache's `LimitRequestBody` isn't set below the upload limits (it defaults to unlimited and can't be checked from PHP, so it's not in the automated checks), then reload and sign up - the first account created becomes the site's administrator.

The setup page only ever appears while `.env` doesn't exist. Once it does, a failing database connection shows a maintenance page instead - deliberately, so a database outage on an established site can't be used by a visitor to reconfigure it.

### Interactive CLI

Everything the web wizard does, from a terminal:

```
php bin/install.php
```

- Runs every environment check and reports all of them at once (colored, when the terminal supports it).
- If the WebSocket server is the only thing missing, offers to write the user-level systemd unit itself (correct paths filled in), enable it, start it, and re-check - no manual unit-file editing.
- With no `.env` present, walks through the same questions as the web form (with the same defaults), provisions the database/runtime account/schema, and writes `.env`.
- With `.env` present, verifies it, creates any missing tables, and **detects schema drift** - columns, indexes, or foreign keys that `schema.sql` defines but the live tables lack (the situation after upgrading to a newer version) - and offers to apply the exact `ALTER` statements needed.
- Admin credentials are prompted for only when there's actual schema work to do; set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` as environment variables to run non-interactively (e.g. from a deploy script - prompts are skipped automatically when stdin isn't a terminal, and the script exits non-zero on anything unresolved).

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
