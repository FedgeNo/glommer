# Fediverse work

What is built, what is next, and why each item matters. Ordered roughly by
value; correctness gaps first, then capability, then reach.

Each item is meant to be picked up on its own. Anything needing a schema change
says so, because that means bumping `GLOMMER_VERSION` and running
`bin/install.php` on deploy.

## Done

- **Per-member actors.** Every member is `@user@host`, followable from anywhere,
  with their own keypair. The actor URI is the profile URL, content-negotiated:
  JSON for the network, HTML for a browser.
- **WebFinger** for every member, plus the instance's own Application actor
  (handle derived from the configured site title).
- **Serialization.** Posts go out as `Note`, or `Article` when titled, rendered
  by the same `DeltaRenderer` the page uses. Hashtags as `Hashtag` tags, media
  as `Image`/`Video`/`Audio` attachments with alt text, edits as `updated`.
- **Outbox**, paged, carrying real `Create` activities.
- **Inbound follows**, auto-accepted (everything here is public, so there is
  nothing for approval to protect), signed by the member being followed.
  `Undo` keyed on the signed actor.
- **Publishing.** Create/edit/delete queue to followers; profile edits and
  account deletion federate; a moderator's deletion federates as the author.
  Nothing publishes for a banned member.
- **Delivery queue and worker.** `bin/federation-worker.php`, backoff doubling
  to twelve attempts, shared inbox preferred where offered.
- **NodeInfo**, counting only local members and local posts.
- **Outbound follows are the member's own**, not the instance's.
- **No site name in source** - site identity comes from config.
- **Reply and mention fan-out.** A post reaches the people it names - whoever
  wrote the parent, anyone mentioned - and not only the author's followers.
- **Likes and boosts.** Inbound `Like`/`Announce` and their `Undo`s; outbound
  `Like`/`Undo` when a member likes a post that came from elsewhere. A like
  needed no table (Likes is keyed on the shadow user); boosts got `Announces`.
- **Authorized fetch.** Outbound GETs are signed as the instance actor, so
  servers in secure mode are reachable at all. Unsigned when no instance key is
  configured, since that still works everywhere secure mode is off.
- **Retired usernames.** A deleted account's name is never reissued - its actor
  URI would otherwise be inherited by a stranger.
- **Federated reports.** An inbound `Flag` raises an ordinary report marked with
  which server sent it; reporting a remote post sends a `Flag` to its home
  server, as the instance so the reporter is never named.
- **Defederation.** `BlockedDomains`, matched including subdomains, enforced on
  the inbox before a key is fetched and on every outbound request. No admin page
  and blocking severs existing follows both ways, drops what was queued, and has
  a moderation page under Blocked Servers.
- **Block propagation.** A member blocking a remote account tells their server
  and cuts the follows both ways; an inbound `Block` is honoured here, and both
  `Undo`s lift it.
- **Federated direct messages.** A `Note` addressed to one actor with no public
  audience, in both directions. Remote profiles carry a Message button, the
  thread is marked as less private (stored on two servers, readable by both
  operators), and video calling is not offered there.
- **Pinned posts.** Up to five of a member's own posts, shown above their feed
  and published as the actor's `featured` collection.
- **Reposting**, in both directions. `Timelines` gained a `reposterId` column
  recording why a post is in a feed, so undoing a repost removes only what the
  repost added. A repost here and a boost from elsewhere share one table, so a
  post carries one count rather than two.
- **Collection pagination.** followers, following and the outbox all describe
  themselves and page at 20, the way the network expects. `featured` is capped
  at five, so it stays inline.
- **Video and Audio objects.** A post that is one video or one audio file is
  published as that object rather than as a note carrying an attachment, with
  `url` giving both the page and the file.
- **Emoji shortcodes.** `:smile:` expands to the character at the last step of
  output, in both languages, from one generated table. Never stored, so what an
  author typed is what an edit gives back - and an unknown name survives intact,
  which is the room custom emoji will need.
- **Account migration.** `movedTo` and `alsoKnownAs` on the actor, a settings
  form for both halves, outbound `Move` to followers, and inbound handling that
  moves a member's follow to the new account - only once both accounts claim
  each other.
- **Custom emoji, inbound.** A post's `Emoji` tags are learned and its
  shortcodes render as those images, scoped to the server that declared them -
  the same name on two instances is two different pictures. Outbound needs a
  local custom emoji feature, which does not exist; see below.

## Before 1.0, regardless of the list below

**Interop against a real instance.** Everything here is verified against a
reading of the spec and tests written from that same reading - a closed loop
that stays green even where the reading is wrong. A real Mastodon server
(one runs in a container on the prod box) following a member, receiving a post,
a reply and a like is the only thing that breaks the loop, and it can invalidate
work already done.

**Restore a backup.** The timer runs; nothing has ever been restored from it.
Rehearse on dev.

## Next

### 1. Custom emoji, outbound
Nothing here defines a custom emoji, so there is nothing of ours to publish.
Needs the site feature first - uploading an image against a shortcode, an admin
page to manage the set, and moderation of what gets added - after which
publishing is just an `Emoji` tag per shortcode a post actually uses. The
rendering side already handles them.

### 2. Polls (`Question`)
Both directions, and a local poll feature to federate in the first place.

### 3. Relays

A relay is a shared firehose: subscribe, and every public post from every other
subscribed server arrives, while yours go out to all of them. It is the usual
answer to a new instance seeing nothing, because federation is follow-shaped and
a server nobody follows from receives nothing to discover people by.

Requirements, decided in advance:

- **Off by default.** Subscribing is an explicit act by an admin, never a
  default state.
- **Say plainly that the cost is variable.** The load is whatever the subscribed
  servers happen to publish - quiet one week, thousands of posts an hour the
  next. Storage, the delivery queue and the moderation queue all take that
  weight. The admin page has to say so before the subscription, not after.
- **Follow either the relay actor or `as:Public`, never both.** Implementations
  disagree about which the `Follow` should name, so both forms have to be
  supported - but only one is ever sent per relay. Two would be one
  subscription counted twice, whatever the database does about it.
- **The firehose is its own feed.** Relayed posts have no follower here by
  definition, so they must not reach the friends feed or the main feed. They go
  to a feed of their own, which someone opens deliberately.

Also worth knowing: modern relays forward an `Announce` naming a post's URI
rather than the post itself, so each one has to be fetched from its home server.
That depends on signed outbound fetches, which are already in place.
