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

## Next

### 1. Authorized fetch
Sign our outbound GETs. Some instances refuse unsigned actor and object fetches
outright, so without this we simply cannot see those servers.

### 2. Outbound Announce (reposting)
Nothing here reposts yet, so there is no local action to federate. Inbound
boosts are recorded; sending one needs the site feature first.

### 3. Flag (federated reports), inbound and outbound
An inbound `Flag` should raise a report in the moderation queue; reporting a
remote post should send one to its home server. Moderation across a network only
works if abuse reports cross it too.

### 4. Instance-level blocking / defederation
A moderator tool to refuse a whole domain: no deliveries in, none out, existing
follows dropped. Worth having before it is needed rather than after.
**Schema change.**

### 5. Block propagation
A member blocking a remote account should send `Block`, and an inbound `Block`
should be honoured.

### 6. Federated direct messages
A `Note` addressed to one actor with no public audience. Federated DMs must be
visually distinguished in the thread, because they are meaningfully less private
than local ones: readable by the remote server's operator as well as ours.
Glommer has no end-to-end encryption - a local message is readable by one
operator, a federated one by two. **Schema change** (`remoteObjectURI` on
Messages).

### 7. Account migration (`Move`)
`alsoKnownAs` and `movedTo`, so someone can arrive with their followers intact
or leave without stranding them.

### 8. Pinned posts
Wanted on the site regardless; federates as the `featured` collection.
**Schema change.**

### 9. Custom emoji (`Emoji` tags)
Shortcodes carried as `Emoji` tags with image URLs, so `:shortcode:` from a
remote post renders as the image rather than as literal text, and ours travel
the same way.

### 10. Video and Audio as first-class objects
A post whose only attachment is one video or one audio file is a `Video` or
`Audio` object rather than a `Note` carrying an attachment. That is what
PeerTube and Funkwhale publish, and what players expect to find.

### 11. Collection pagination
`followers` and `following` inline their whole list. Anything that can pass ~20
entries needs paging, same as every other list on the site.

### 12. Polls (`Question`)
Both directions, and a local poll feature to federate in the first place.

### 13. Relays
Subscribing to a relay puts posts in front of servers that do not already follow
anyone here - the usual answer to a small instance seeing nothing.
