# Glommer

Glommer is a self-hosted social publishing platform - posts, replies, friends, messaging, and notifications, built as a plain PHP + MySQL app with no frontend framework.

## Features

- **Posts** - text, a title, an optional link (with an automatically-fetched title/description/image preview pulled in at compose time), or attached images/video/audio, posted through a rich-text (Quill) editor with math support (KaTeX) and an emoji picker
- **Replies** - threaded replies to any post, shown as a full conversation thread
- **Likes**
- **Friends** - friend requests (send/accept/deny), a friends list, and a friends-only feed
- **Messaging** - direct conversations between friends
- **Notifications** - live-updating over a WebSocket connection (toast pop-ups, unseen-count dot) for likes, replies, friend requests, and messages
- **Live messaging** - a conversation you have open updates in real time when the other person replies, over the same WebSocket connection
- **Search** - find other users by username/display name
- **Moderation** - blocking other users, reporting posts, an admin reports queue, and banning
- **Accounts** - signup with email verification, login/logout, forgot/reset password, avatar upload (with an initial-letter fallback avatar when none is set), light/dark/system theme, and a preferred emoji skin tone
- **Relative timestamps** ("3m ago") that stay correct against server time, falling back to an absolute date after 7 days
- **Infinite scroll** for feeds, notifications, and message history
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
```

Before `.env` exists yet (a fresh install), it runs with `config.php`'s defaults (`WS_PORT=8090`, `WS_SECRET=change-me`) - that's fine for the install-time connectivity check, since both the daemon and the web process resolve the same defaults with no `.env` in place. Once setup writes a real `.env` (see below), it generates a fresh `WS_SECRET` the already-running daemon doesn't know yet - **restart the service once** after setup completes to pick it up. `bin/install.php` (and the web setup wizard) both perform a real handshake + ping/pong round trip against it, not just a port-open check, and refuse to finish if it isn't reachable.

## Installation

### Automatic (recommended)

1. Clone/copy the project to your web server's document root.
2. Make sure the web server user can write to the project root (e.g. `chmod 777 <project root>` - see step 5).
3. Start the WebSocket server (see above) - it can run with no `.env` in place yet.
4. Visit the site in a browser. Since there's no `.env` yet, you'll land on a setup page instead of the normal site.
   - If any environment prerequisite is missing (PHP version, extensions, `ffmpeg`, writable directories, outbound network, the WebSocket server, etc.), it's reported here - fix it and reload.
   - Otherwise you'll see a setup form asking for:
     - **Site** - site URL, site title, and the "from" address/name used for outgoing email
     - **Database** - host, port, database name, and credentials for a MySQL account with `CREATE`/`ALTER`/`DROP`/`CREATE USER`/`GRANT OPTION` privileges (e.g. `root`, or any admin account with those rights)
5. Submit the form. It creates the database (if it doesn't already exist), generates a random password for a new least-privilege runtime database account, creates the schema, generates a fresh `WS_SECRET`, and writes `.env` with all of this - none of the admin credentials you entered are ever stored.
6. Restart the WebSocket server (`systemctl --user restart glommer-websocket`) so it picks up the fresh `WS_SECRET` the previous step just generated.
7. Run `chmod 755 <project root>` to restore normal permissions (the setup page shows this exact command).
8. Reload the site and sign up - the first account created becomes the site's administrator.

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
5. Optionally verify everything's in place before going live:
   ```
   php bin/install.php
   ```
   This checks PHP version/extensions, `ffmpeg`, writable directories, outbound network access, that the WebSocket server is actually reachable, and that `.env`/the database/schema are all correctly set up - and can also create any missing tables itself if you set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` (an account with `CREATE` privileges) as environment variables first.
6. Visit the site and sign up - the first account created becomes the site's administrator.
