# Glommer v0.9.6 Beta Release Notes

**Released:** July 13, 2026

## What's New

### Features

**Hashtags** — Posts now support #hashtags extracted automatically during composition. Browse all posts tagged with a specific hashtag at `/tags/{tag}`, view trending tags, and search the public tag directory to discover conversations.

**Full-Text Search** — Search all posts on the site at `/search` using the keyword search interface. Posts indexed by title, description, and keywords for fast full-text matching.

**Account Deletion** — Users can now permanently delete their accounts from the Settings page. Deletion cascades safely: all posts, replies, messages, and other user-generated content are removed atomically.

**Report Content Snapshots** — The moderation system now captures a snapshot of reported content (the exact post text or message body) at the time of report, preserving evidence even if the content is later edited or deleted. Admins see the reported state in the reports queue, not what it's been changed to since.

**Honest Link Rendering** — Links in posts now display their actual destination URL instead of deceptive link text, preventing phishing attempts. A link that says "Click here for free money!" won't silently redirect to a malicious domain.

### Reliability & Operations

**WebSocket Watchdog** — The recommended systemd service configuration now includes `WatchdogSec=30`, enabling systemd watchdog monitoring. If the daemon's event loop hangs, systemd automatically restarts it (something a plain crash restart can't catch).

**Daily WebSocket Restart** — Added `RuntimeMaxSec=1d` to automatically recycle the long-running WebSocket daemon daily, preventing slow resource growth from accumulating indefinitely. Clients reconnect transparently.

**Silent Schema Upgrades** — If the only pending upgrade work is DML maintenance (no missing tables, schema drift, or index migrations), the site applies it silently on the first request after a deploy with `ignore_user_abort()` in effect. Visitors see no maintenance page and the upgrade completes in the background.

**Improved Installer** — The CLI and web installers now:
- Automatically reconcile systemd service units to the current template, so daemon settings (watchdog, restart behavior) are picked up on every run
- Offer to set up nightly backups and the WebSocket service themselves (no manual unit-file editing)
- Reconcile backup timer units in the same way, ensuring scheduled backups stay in sync with config changes

### Security

- Full parameterized query coverage (no SQL injection vectors)
- Timing-safe CSRF token validation
- Session security: `httponly`, `samesite=Lax`, `secure` flags; regeneration on login; invalidation on password change
- All foreign keys properly configured with `ON DELETE CASCADE` for data consistency
- HTTPS strictly enforced at boot and on every request
- Error handling never leaks stack traces to users

## Database Schema Changes

**New tables:**
- `Hashtags` — tag name and ID
- `PostHashtags` — many-to-many mapping between posts and tags

**New columns:**
- `Posts.keywords` — space-separated hashtags extracted from post content
- `Posts.descriptionDelta` — rich-text post body using Quill Delta format (replaces HTML)
- `Reports.snapshot` — forensic snapshot of reported content state at report time
- `Users.isMod` — moderator status flag (can also be an admin via userId=1)
- `Users.verified` — email verification gate (unverified users blocked from site)
- `Users.sessionVersion` — cache invalidation on password change
- `Users.friendCount` — denormalized count for fast friends list

**Index improvements:**
- Composite indexes on Friendships for efficient status-filtered queries
- FULLTEXT index on Posts for full-text search

**Foreign key migrations:**
- `Posts.userId` now properly cascades on user deletion (was RESTRICT)
- All ID columns unified to `int(10) unsigned` (doubled range, type safety)

## How to Upgrade

After pulling the code:

```bash
php bin/install.php
```

The installer will:
1. Verify all environment requirements
2. Detect any schema drift and apply missing definitions
3. Reconcile systemd service units (WebSocket and backup timers) to the current template
4. Record the new version in the database

Visitors see no maintenance page unless missing table creation or index migrations are needed (rare). Re-running on a healthy install is always safe.

## Breaking Changes

None. This release is backward compatible with v0.9.5 deployments.

## Known Limitations

- Hashtags are case-sensitive (tag search is case-insensitive, but the canonical form of each tag is preserved from first use)
- Report snapshots are one-time captures; if content is deleted after report, the snapshot remains but the original content cannot be recovered
- WebSocket daemon requires a separate systemd service or equivalent process manager — it does not auto-start with Apache/PHP-FPM

## Installation & Setup

**New installs:**

```bash
# Clone the repository
git clone https://github.com/FedgeNo/glommer.git /var/www/glommer
cd /var/www/glommer

# Start the WebSocket server (systemd or manual)
systemctl --user start glommer-websocket

# Run the web setup wizard or CLI installer
php bin/install.php

# Visit the site and sign up — first account is admin
```

**Existing installs:**

```bash
git pull origin master
php bin/install.php
systemctl --user restart glommer-websocket
```

## Testing

This is a **Beta** release. Please test the following before deploying to production:

- [ ] Hashtag extraction: Create a post with #hashtags and verify they appear in `/tags/`
- [ ] Search: Search for a known post at `/search` and verify results
- [ ] Account deletion: Delete a test user account and verify all content cascades
- [ ] WebSocket: Open the site, verify live notifications and messaging work
- [ ] Moderation: Report a post and verify the snapshot is captured correctly
- [ ] Upgrade: Test the upgrade path from v0.9.5 (if upgrading from an older version)

## Requirements

- **PHP:** 8.1 or later (with `mysqli`, `gd`, `curl`, `dom`, `libxml`, `fileinfo`, `mbstring`)
- **Database:** MySQL 5.7+ or MariaDB 10.2+
- **OS:** Linux/Unix (systemd recommended; works with cron/supervisord/etc as fallback)
- **Media:** `ffmpeg` and `ffprobe` on `PATH` for video/audio uploads
- **Network:** Outbound HTTPS for link preview fetching; inbound HTTPS required (no plain HTTP)

## Support

- Documentation: See README.md for installation, configuration, and operational procedures
- Issues: Report bugs at https://github.com/FedgeNo/glommer/issues
- License: MIT License (see LICENSE.txt)

---

**Changelog highlights:**
- 30+ commits since initial release, including security reviews, feature implementation, and infrastructure improvements
- Zero external dependencies (hand-rolled WebSocket, no Composer)
- Full test coverage of security-critical paths
- Production-ready moderation and reporting system
