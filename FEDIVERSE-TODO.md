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

### 1. Custom emoji (`Emoji` tags)
The shortcode machinery is in place and deliberately leaves an unknown name
alone, so this is now additive: a per-post map of name to image URL, passed into
the same expansion, plus an `Emoji` tag on the way out.

Outbound still needs a custom emoji feature on the site - there is none, so
there is nothing of ours to publish. Inbound is the useful half and is no longer
blocked by the plain-text reduction, since the tag travels beside the content
rather than inside it.

### 2. Polls (`Question`)
Both directions, and a local poll feature to federate in the first place.

### 3. Relays
Subscribing to a relay puts posts in front of servers that do not already follow
anyone here - the usual answer to a small instance seeing nothing.
