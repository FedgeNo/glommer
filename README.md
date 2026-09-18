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
community: the operator claims the administrator account during setup (§11), and
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
  cached, but a one-time notice explains that readable text is sent to this
  server and to Google or another translation provider. This includes the
  plaintext of an end-to-end encrypted message already open in the browser.
  The original message remains unchanged.
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
  emails a short-lived code that must be entered to finish signing in. Enabling
  it revokes remembered-device credentials issued before the second factor.
  Repeated logins reuse the current code without extending its ten-minute
  lifetime or resetting its five-guess budget. Emails are limited per account
  to one per minute and three per rolling 15 minutes, including failed sends.
  Recovery codes remain available when mail is unavailable or limited.
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

### Games

`/games/` is the games room; `/games/roulette` is playable European single-zero
roulette. Each game has its own PHP entry point, browser module, and stylesheet
under `games/`. `casino.js` and `casino.css` provide the shared wallet and room.
The roulette scene uses pinned Three.js 0.180.0 from jsDelivr, with modeled wood,
brass, pockets, ball, procedural wood texture, and shadows over the shared green
table finish. WebGL failure leaves betting and
text results usable; reduced-motion preferences skip the spin animation.

Guests receive 1,000 play-only chips on their first visit. An eligible visible
visit grants another 1,000 after 3,600 seconds from the previous actual grant.
Returning after several hours still grants only 1,000 and starts the next hour;
unclaimed grants never accumulate. Existing chips and winnings carry forward.
Signing up or signing in transfers the guest wallet into the account once,
including pending spin receipts and the latest grant time. Guest wallets use an
HTTP-only cookie: losing that cookie loses access to an unclaimed guest balance.
Chips cannot be purchased, cashed out, transferred, or exchanged for prizes.

`GameWallet` owns server-side balances, locked grants, account claims, and atomic
play receipts. A retried play ID returns the original outcome without another
charge or draw. The browser retains an unconfirmed spin in session storage and
offers **Recover spin** after a network interruption. `Roulette` owns the bet
catalogue and payouts; each result uses `random_int(0, 36)`, independently of
stakes, player history, or animation. Standard European payouts return the
winning stake as well as profit; zero loses all outside bets. The table accepts
whole-chip bets with a total limit of 10,000 per spin. There is no la partage or
en prison. Fixed-cost arcade games can use `GameWallet::spend()` with a price
chosen by the server.

`/games/vlt` is **Nova Vault**, a five-reel, three-row video lottery terminal
with ten fixed paylines. Total stakes are 10, 20, 50, 100, 250, or 500 chips,
divided evenly across the lines. Three or more matching symbols from the left
pay the longest match on each line. `VLT` owns the six-symbol paytable and five
32-stop strips; each reel stop is drawn independently with `random_int`.
An independent Supernova multiplier is 1× on 90% of spins, 2× on 8%, and 5× on
2%. The exact theoretical return is approximately 95.7022%, including those
multipliers. The game exposes symbol frequencies, payouts, and all payline paths
in its rules. There are no wild symbols, scatter awards, or progressive pools.

VLT spins settle through the shared wallet receipt transaction. Recovery reuses
the pending request key and stake; animations cannot change the result. The
browser uses procedural Three.js rings, gems, stars and coin bursts, SVG reel
symbols, and optional synthesized audio. Reduced motion reveals the reels
immediately, and failed WebGL leaves the cabinet and game usable. A payout below
the stake is shown as a net loss, without a win celebration.

`/games/singularity` is a 5 × 5 cluster-and-cascade game. Groups of at least five
identical symbols connected horizontally or vertically pay anywhere on the board.
All winning groups are removed together; survivors fall and new symbols fill the
gaps. Every draw independently selects one of six equally likely symbols. Each
successive winning cascade increases Overdrive from 1× through 12×; the cycle
ends on a nonwinning board or after paying cascade 12 without another refill.
The page discloses the size-tier paytable. A million-cycle simulation estimates
95.6% return (approximate 95% sampling interval 95.1–96.2%); this is not an exact
theoretical return. Reproduce with PHP's `mt_srand(20260917)` and one million
`Singularity::round(10, static fn(): int => mt_rand(0, 5))` calls, summing payouts
and dividing by total stake. Live settlement uses `random_int`, not this seeded
simulation generator. Singularity owns its settlement and
board animation, sharing wallet/recovery controls with VLT, with its own receipt
identity and pending-spin storage key. Nova Vault recovery cannot be consumed
by Singularity or vice versa. Its visual layer adds a procedural shader vortex,
counter-rotating reactor machinery, plasma tendrils, expanding shockwaves, and
screen-wide shards on profitable results. Effects are bounded, pause with hidden
pages, and respect reduced motion; the ordinary board works without WebGL.

`/games/pachinko` is Neon Pachinko: one chrome ball, twelve independent fair
left/right bounces, and thirteen pockets. Returns range from 0.2× to 50× the
total stake; the two edge jackpots together occur on 1 in 2,048 balls. Exact
theoretical return is 96.8457%. The page lists every pocket's payout and its
probability among the 4,096 equally likely paths. Payouts include returned stake.
`Pachinko` draws and settles the complete path on the server using the atomic
wallet receipt. The browser only animates that path; timing does not affect it.
Pending plays use their own recovery key and cannot be charged again on retry.
The Three.js board uses chrome, reflective glass, neon peg collars, additive
impact rings and trails, and a jackpot particle burst. Reduced motion lands the
ball immediately; a simple SVG board appears only if 3D rendering fails.

`/games/poker` is nine-seat no-limit Texas Hold’em for signed-in members, with
200–1,000-chip human stacks, 1,000-chip bot stacks, 10/20 blinds, and no rake.
Seats use up to 1,000 available wallet chips, with a minimum of 200; the stack
is money in play, not an entry fee. Humans use their @slugs and avatars;
suit-marked bot identities cannot be mistaken for Glommer usernames. A 20-second
queue packs available humans into tables before filling remaining seats with
bots. Hands finish before anyone moves; the result stays visible for at least
10 seconds before the next matching window. Staying seated authorizes another
buy-in of up to 1,000 chips, subject to the available balance. Leaving cancels that next
seat, while the current hand settles normally.

`PokerRound` owns dealing, legal actions, minimum raises, all-ins, side pots,
and showdown. Its viewer projection excludes the deck and other players’ hidden
cards. `PokerHand` compares the best five of seven cards, including kickers,
ace-low straights, split pots, and clockwise odd-chip distribution. `PokerBot`
receives only its own cards, the public board, legal moves, and public betting
context. Named bots have different calling, raising, and bluffing tendencies.
They sample unseen cards and future streets to estimate their share of the pot,
then consider the call price, position, and previous raises. They cannot inspect
the actual deck or opponents’ cards. `Poker` locks
the lobby while assigning seats and persisting transitions; `GameWallet`
records buy-ins and returns in the same transaction. Versioned actions reject
stale tabs and retries rather than betting twice.

The browser polls every 1.5 seconds after completion of the previous request;
there is no extra daemon. Requests also advance overdue tables, including
hands abandoned by every browser. A player has 25 seconds to act, then checks
if possible and otherwise folds. Reservations expire after 45 seconds without
presence. No further hands start for an absent player. An abandoned unfinished
hand returns its chips when a subsequent poker request advances it to completion.
Table chat is plain text, participant-scoped, rate-limited, and filtered for
blocks; avatars appear in chat and at seats. Finished tables and their chat
expire after 24 hours, cleaned up by poker requests. Wallet receipts remain.

The table shows street bets, animated chip collection and payouts, staged card
reveals, and selectable best-five highlights at showdown. Results separate main
and side pots, split awards, uncalled refunds, and the viewer’s net chip change.
Minimum, half-pot, and pot raise presets show both the total bet and additional
chips required. Seat timers count down locally between server updates. Sound is
optional, synthesized locally, and its mute preference is saved in the browser;
reduced-motion preferences suppress dealing and travel effects.

`/games/derby` hosts Bent Metal Derby. Its independently maintained single HTML
file is checked out as the `games/bent-metal-derby` Git submodule; initialize it
with `git submodule update --init games/bent-metal-derby` after cloning Glommer.
`DerbyPage` adds a CSP nonce and the separate `derby.js`/`derby.css` adapter to
that HTML. The source game retains standalone cash, free entry, and local saves.
Its `Economy.transact(action, done)` callback seam covers admission, upgrades,
and rewards; the adapter resumes gameplay only after the server accepts the
transaction. Keep generic game changes in that repository and host-specific
behavior in Glommer. Commit and publish source changes before updating the
submodule reference; update deliberately and rerun both projects' tests.

In Glommer, one game dollar is one chip and one life is 500 chips. Each new
race, retry, or time trial costs 500; winning a life credits 500. Life counts
come directly from the shared balance, so starting a new career cannot mint
lives. Upgrades are derived from paid receipts and follow guests into accounts.
Reward contributions are deliberately small, with diminishing drift returns
and no total winnings ceiling. A first-place finish pays 750 including its life,
before combat and drift rewards. Time trials have no reward. The guest wallet
continues receiving its ordinary hourly grant during visible play.

`Derby` owns admission prices, upgrade validation, and server reward accounting;
`GameWallet::finish()` locks and settles each admission at most once. Pending
transactions are retained in session storage for recovery. Race statistics are
client-reported: the server recalculates contributions and checks ownership,
elapsed time, plausible counts, and repeat submissions, but does not simulate
the race. This is not cheat-proof verification of finish position or driving.

The room loads one ExoClick iframe over HTTPS: 300 × 250 below 768px, 728 × 90
from 768px through 939px, and 900 × 250 from 940px. The iframe policy permits
`a.magsrv.com` only on game pages. The zone URLs live in `GameRoom` and the Derby
adapter, which displays its advertisement in the start menu. Derby uses the
shorter 728 × 90 slot at every width from 768px upward, and the 300 × 250 slot
below that. Its start menu scrolls on short screens.

### Application services

- **Web tier** - procedural PHP page scripts at the project root (`index.php`,
  `login.php`, ...) and JSON endpoints under `api/`, routed by `.htaccess`.
  Every "thing" on the site (a post, a report, a banned device) is an
  `HTMLObject` subclass that builds its own DOM via `toDOM()`; the client
  mirrors each one in JavaScript and rebuilds it from the JSON payload, so the
  server never ships HTML fragments over AJAX.
- **Browser forms** - `FormForm` in `scripts/HTMLObjects.js` owns delegated
  submission, one pending operation per form, busy indicators, cancellation,
  and restoring controls. Controllers register with `FormForm.attach()`;
  alternate actions such as saving a draft use `FormForm.run()` with the same
  guard. Handlers await all work and pass their cancellation signal to `Api`
  or the composer's progress-reporting XHR. Setup and email confirmations use
  `FormForm.submitPage()` to retain native POST navigation and recover their
  controls when returning with Back. Browser-built forms default to POST and
  include the CSRF token, matching the PHP base. Completion clears or resets
  only unchanged submitted fields. Composers preserve the whole draft when
  newer text, files or other edits arrive while sending; inline editors stay
  open for those newer edits.
- **Database** - MySQL/MariaDB via `mysqli`, prepared statements only. The app
  runs as a least-privilege account (`SELECT/INSERT/UPDATE/DELETE` only);
  schema changes are done by a separate admin account, only when needed.
- **WebSocket daemon** (`bin/websocket-server.php`) - a hand-rolled RFC 6455
  server (no libraries) that powers live notifications and messaging. Holds no
  database connection. PHP issues signed, session-bound 30-second connection
  leases; browsers renew them with completion-scheduled requests every 15
  seconds. Credential revocation sends a separately signed, replay-protected
  disconnect command through the loopback control listener after database
  commit. The daemon enforces expiry even if that command is missed, and
  never accepts control commands from public WebSocket frames or message
  payloads. Ordinary pushes also use a separate signature purpose and replay
  protection; the signing secret is never transmitted over the internal socket.
  It remains server-side in the protected `.env`. Deploy the PHP, browser, and
  daemon changes together and restart the daemon and both background workers;
  old open pages need reloading to use lease renewal.
  Admission defaults to 500 public connections, 20 authenticated connections
  per account, and 50 pending authentications globally with at most 10 per IP.
  A separate pool of 64 internal connections remains available for pushes and
  revocation. Only excess new connections are refused; existing ones are never
  evicted to make room. The `WS_MAX_*` values in `.env.example` configure these
  limits. Browser retries use one completion-scheduled timeout, exponential
  backoff and jitter, up to five minutes between attempts.
- **Upload worker** (`bin/upload-worker.php`) - drains a disk-backed queue of
  staged video/audio uploads, transcoding each with `ffmpeg` in an OS-sandboxed
  subprocess, then publishing the post and notifying the author.
- **Federation worker** (`bin/federation-worker.php`) - drains the outbound
  ActivityPub delivery queue, signing each activity as the member it's from;
  also fetches posts a subscribed relay has named, completes linked inbound
  Create objects and missing reply context, delivers Web Push
  notifications, retries committed post-media cleanup, and sends the trickle
  of email digests (§10).
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
  forms exist for. It is also where a locale file's calendar block - month
  names and the order of a date's parts - is read from when one is written,
  neither being a thing to ask a model for: asked, it answers "Januaro Januaro
  Januaro".
- **Free disk space: 25GB minimum, 500GB or more recommended.** The minimum is
  what it takes to install and try the thing: most of it is the translation
  environment, which is PyTorch plus a model for every language pair Argos
  publishes (§6, skippable). The recommendation is for a site with members on
  it - uploads are kept at full size alongside their transcodes, and a video
  is the largest thing anybody posts.
- **Three background daemons plus a timer**, all separate from the web server
  and all set up for you by the installer (§7).

## 5. Installation

There are two guided installers - a web setup wizard and an
interactive CLI - plus manual configuration followed by the installer. All end in the same place: a
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
3. Run `sudo php bin/install.php --setup-code` on the server. Visit the site
   over HTTPS and enter the single-use code. With no `.env`, the authorized
   browser gets a setup page: it reports any missing
   prerequisite (fix and reload), then a form for the site URL/title/mail-from,
   database admin credentials, and optional WebSocket TLS paths.
4. Submit. It proves HTTPS is live, that `ServerName`/`UseCanonicalName` block
   Host-header spoofing, generates a WebSocket TLS cert with mkcert if needed,
   and provisions the database.
5. Follow the success checklist: restore permissions, restart the WebSocket
   server, and open `/signup` in that same browser to create the administrator
   (§11). Other browsers cannot register before this claim is complete.

The setup page only appears while `.env` is absent; afterward a DB outage shows
a maintenance page instead, so it never invites re-installation.

Codes expire after 24 hours and are exchanged once for a grant bound to the
claiming browser session, also valid for 24 hours. The protected `.setup-claim`
file contains hashes, never the code or browser grant in plaintext. Completing
administrator registration consumes the grant with the database transaction.
If the browser session is lost, `sudo php bin/install.php --setup-code` issues
a replacement and invalidates all earlier codes and grants. It cannot reopen
setup on a claimed installation. Google signup and remote actor creation also
respect this gate.

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

On an unclaimed installation, the completed installer prints the single-use
code needed at `/signup`. Ordinary upgrades preserve the existing administrator.

### Manual

1. Copy `.env.example` to `.env` and fill in `SITE_URL`, `SITE_TITLE`, and the
   least-privilege `DB_*` credentials. The mail "from" address and SMTP relay
   are no longer `.env` settings - they live on the Admin Settings page
   instead (step 5 prompts for the "from" address the first time it's unset
   anywhere).
2. As a DB admin account, create the database (`utf8mb4`/`utf8mb4_unicode_ci`),
   then create the runtime account with only
   `SELECT, INSERT, UPDATE, DELETE` on it.
3. Ensure `uploads/` is writable by the web-server user.
4. Run `sudo php bin/install.php` to install the schema, migrations and
   services (§7). Schema changes and backfills always go through this installer.
5. Enter its setup code at `/signup` and create the administrator.

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
hundred packages, ten gigabytes, and a long download. Set `SKIP_TRANSLATE=1`
to leave that environment alone. Everything else, including spaCy, installs
as normal.

**Post and message translation.** Google Translate's web component is tried
first; it needs PHP cURL but no API key or local language model. SMaLL-100,
Argos Translate, and the configured OpenRouter provider remain fallbacks in
that order. Google's web interface can change or refuse requests; failures
trigger a five-minute cooldown while the fallback path remains available.
Requests have bounded timeouts and response sizes. Post translations are
cached; message translations are not saved by Glommer. The message notice
discloses external processing before sending readable text.

**Translating the interface.** English lives in `locales/en.json` and every
other locale is made from it, by hand - written and committed like any other
change, since interface wording is not something to leave to a model. English
stands in per key for anything untranslated, so a partial locale is a working
one.

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

Upload publication has a durable batch receipt. Completed transcodes remain
available until the post, media rows, timeline, notification rows, and federation
queue commit together. A worker crash resumes unfinished publication or completes
cleanup for an already published post; it cannot recreate a post subsequently
deleted by its author. Final media files use hard links to the retained output
where possible, so recovery does not require another full media copy. Temporary
database failures retry after a minute without consuming the fatal-crash budget.
Three fatal finalization failures produce one upload-failure notification after
the worker has checked that publication did not commit.

Account deletion removes the local account immediately and queues its ActivityPub
Delete in the same transaction, for both password and Google confirmation. A
separate record retains only the actor identity, public key, encrypted signing
key, and expiry; delivery rows retain the Delete and its destinations. The worker
removes this material when delivery finishes or after seven days. While delivery
is pending, ActivityPub requests can retrieve a minimal inactive actor and public
key so remote servers can verify the signature; the human profile is gone. This
uses the [Mastodon suspended-actor extension](https://docs.joinmastodon.org/spec/activitypub/#suspended-flag).
Expiry is checked before signing or serving that identity; physical pruning runs
when the federation worker runs, including after an outage.

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

`PostEntityExtractions` remembers each post's extracted entities and language,
keyed by its content hash and extractor/model version. The trending job extracts
only missing or changed posts, in bounded batches, then recalculates time decay
and distinct-author scores from the current window. Failed batches retain a
hashtag fallback and retry after ten minutes. Page requests use the cache and
fresh hashtags without launching the model. The installer warms the cache;
keep `glommer-trending.timer` running, or schedule `bin/compute-trending.php`,
to extract new posts and edits.

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

`bin/backup.php` writes `database.sql.gz` and `recovery.json.gpg` into a
timestamped directory, pruning older runs. **Media is not backed up.** The
recovery bundle contains `.env` and its effective configuration values,
including current and retained ActivityPub encryption keys. These keys are
required to recover encrypted federation signing identities and verify old
message-franking records. A completed run requires both nonempty files.

Install GnuPG and the standard `timeout` command on the backup host. Create a
dedicated encryption key on a separate, trusted recovery machine:

```sh
gpg --quick-generate-key 'Glommer backup recovery' rsa4096 encr 0
gpg --armor --output recovery-public.asc --export 'Glommer backup recovery'
gpg --armor --output recovery-private.asc --export-secret-keys 'Glommer backup recovery'
```

Protect the exported private key and its passphrase, and keep an independent
copy off the production server. Install **only `recovery-public.asc`** in the
backup root, readable by the backup service account, or set
`BACKUP_RECIPIENT_FILE` to its absolute path. GnuPG's
[`--recipient-file`](https://www.gnupg.org/documentation/manuals/gnupg26/gpg.1.html)
uses that public key without requiring a private key or a personal keyring on
the server. The script feeds configuration directly into encryption; it never
writes a plaintext configuration backup. Missing/invalid keys fail the run.
Configure the recipient before the installer's first backup. Keep key rotation
and configuration changes outside the backup window.

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

**Restore**: `bin/restore.php` restores the database and leaves existing uploads
in place. It also accepts older database archives after their media archives
have been removed. Lost/deleted media cannot be recovered from these backups.

```
php bin/restore.php                                   # what it would do, and nothing else
php bin/restore.php 2026-08-08_085356                 # ditto, for a named run
sudo env GLOMMER_RESTORE_CONFIRMED=1 php bin/restore.php # go ahead, newest database run
```

Without the confirmation it only reports; nothing is changed. A run is named,
never given as a path, and the name is resolved under this install's own
backup root - so no argument reaches another server's backups. Run it as root
(or set `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD`): the dump drops and recreates
every table, which the least-privilege runtime account cannot do.

Stop application and worker writes before importing. The script validates
decompression before loading SQL, but imports directly into the configured
database: an interrupted/failed SQL import can leave partially replaced tables
and does not roll back automatically. If the backup predates the code, the site
holds a maintenance page until `bin/install.php` brings the database up to date.

If configuration was lost, recover it first on the trusted recovery machine:

```sh
gpg --import recovery-private.asc
umask 077
gpg --output recovery.json --decrypt recovery.json.gpg
```

The JSON's `environmentFile` holds the original `.env`; `effectiveEnvironment`
records values actually used, including overrides and retained encryption keys.
Restore those values to a protected `.env` on the replacement installation,
adjusting host-specific paths/addresses and database access as needed. Preserve
encryption keys exactly; do not replace them with freshly generated values.
Transfer recovered configuration through a secure administrative channel and
remove the decrypted working file afterward. The private recovery key stays
off production. Restoring the database does not overwrite `.env` automatically.

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

The **operator-authorized first account is the administrator**. The installer
code protects this claim; registration assigns it `userId` 1 even if an earlier
failed insert consumed an auto-increment value. Admin-only
actions (appointing/revoking moderators, editing admin settings, Google/Turnstile
config) are theirs alone; general moderators can work the reports queue, ban
users, ban trending entities, and defederate whole domains from the
**Blocked Servers** section of Mod Settings - a domain block refuses that
server's deliveries, stops all fetches to it, and severs existing follows in
both directions.

**Admin Settings** opens with server health (load, memory, disk space, database
activity and queries running at least five seconds) and linked status tiles for
local members and posts, federation deliveries, uploads, live notifications,
trending, backups and moderation reports. Server health and the queue/service
tiles refresh ten seconds after each completed request while the tab is visible;
the full overview refreshes once a minute. Failed readings are shown as
unavailable, and a failed refresh retains the previous values. Backup dates
come from the two nonempty archive files; they do not verify that a restore
will succeed. Detailed service checks and settings remain in the panels below.
The status API uses the same primary-admin restriction and CSRF protection as
the settings page. It reports a count of long queries, never their SQL text.

Deleting reported content commits the content deletion, stored counts, report
resolution, moderation log and any federation Delete queue entries together.
Public media cleanup runs after commit and retries from `MediaDeletions` if
the request is interrupted or a file cannot be removed. Private forensic
originals retain their existing preservation policy.

Browser push allows ten subscriptions per account and ten new registrations
in a rolling 24-hour window. Existing subscriptions can refresh without using
that allowance. Settings lists destinations for explicit removal; none are
automatically evicted for age or inactivity. Removing one does not reset the
daily allowance. Existing subscriptions above the cap are preserved on upgrade,
with up to 100 shown at a time for removal. A shared browser changing accounts
discards notifications queued for the previous owner of that destination.

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
Each one is single-use and rotates as it is spent. The spent selector remains
as a tombstone until its original expiry, so either reuse of that selector or a
known selector with the wrong validator means a copy is in circulation, and
every token on that account is revoked.

**Cookie scope** is enforced with `__Host-` names for the session, remember-me,
CSRF, and client-configuration cookies on HTTPS. Each is `Secure`, uses
`Path=/`, and omits `Domain`; session and remember-me cookies also remain
`HttpOnly`. Upgrading from unprefixed cookie names requires users to sign in
again. The old names are expired and are not accepted on HTTPS. The initial
HTTP requests use unprefixed cookies before installation, but entering a setup
code and configuring the site require HTTPS.

**CSRF** is checked in one place, `init.php`, on every POST. Three endpoints are
exempt because they cannot carry a token and were never meant to: the
ActivityPub inbox proves itself by HTTP signature, one-click unsubscribe by
the RFC 8058 body token, and the bounded CSP-report endpoint is rate-limited.

**Federation key discovery** remains synchronous but is separately bounded
before an incoming signature is trusted. At most four discoveries run at once,
with thirty new lookups per source IP per minute. Simultaneous requests for one
actor are coalesced through a nonblocking lock; failures cool down for one
minute. A short-lived PHP child holds the locks under a 20-second wall-clock
deadline covering DNS, redirects and fetching, shared with any key refresh in
that authentication attempt. Capacity refusals and timeouts return HTTP 503
with `Retry-After: 60`; no activity is accepted before signature verification.

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
what gets published. The original is kept with a fixed inert filename under
the access-denied `uploads/private/` tree; moderators receive safe media types
through a sandboxed viewer and every other type as a download.

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

**Rate limits** cover logging in, signing up, resetting a password (by both
client address and target account), posting,
messaging, reporting, translating and federated delivery. Login is limited by
IP and by account at once, so neither a spread-out attempt nor a targeted one
gets a free run.
