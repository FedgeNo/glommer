<?php

declare(strict_types=1);

/**
 * Processes a verified incoming ActivityPub activity (the caller - the
 * inbox endpoint - has already checked the HTTP Signature before this ever
 * runs). Scope is deliberately narrow: Accept (our own outbound Follow),
 * Create/Update of a Note, and Delete. Anything else is silently ignored,
 * not an error - a Fediverse server can send activity types this app has no
 * use for, and that's expected, not a bug to report.
 */
class ActivityPubInbox
{
    public static function process(array $activity, string $signed_actor_uri): void
    {
        match ($activity['type'] ?? null) {
            'Accept' => self::handleAccept($activity, $signed_actor_uri),
            'Reject' => self::handleReject($activity, $signed_actor_uri),
            'Create' => self::handleCreate($activity, $signed_actor_uri),
            'Update' => self::handleUpdate($activity, $signed_actor_uri),
            'Delete' => self::handleDelete($activity, $signed_actor_uri),
            'Follow' => self::handleFollow($activity, $signed_actor_uri),
            'Undo' => self::handleUndo($activity, $signed_actor_uri),
            'Like' => self::handleLike($activity, $signed_actor_uri),
            'Announce' => self::handleAnnounce($activity, $signed_actor_uri),
            'Flag' => self::handleFlag($activity, $signed_actor_uri),
            'Block' => self::handleBlock($activity, $signed_actor_uri),
            'Move' => self::handleMove($activity, $signed_actor_uri),
            'Add' => self::handlePinChange($activity, $signed_actor_uri, true),
            'Remove' => self::handlePinChange($activity, $signed_actor_uri, false),
            default => null,
        };
    }

    /**
     * Somebody elsewhere pinned or unpinned one of their own posts.
     *
     * The target has to be a collection on the sender's own server, which is
     * as precise as this can be: the actor document's featured collection URI
     * isn't stored here, so there is nothing to compare a specific one
     * against. It is enough in practice - the implementations that send these
     * use them for pins, and the two things that could go wrong are both shut
     * by other checks. An Add naming someone else's collection fails the host
     * test; an Add of anything that isn't a post we hold (a featured hashtag,
     * say) resolves to no post and stops there.
     *
     * Only a post already held gets pinned: a pin is not a reason to go and
     * fetch something. One naming a post that arrives later simply doesn't
     * show until the next Add.
     */
    private static function handlePinChange(array $activity, string $actor_uri, bool $pinning): void
    {
        $actor = User::byRemoteActorURI($actor_uri);
        $object_uri = self::objectURI($activity['object'] ?? null);
        $target = $activity['target'] ?? null;

        if ($actor === null || $object_uri === null || !is_string($target)) {
            return;
        }

        if (!RemoteActor::sameHost($target, $actor_uri)) {
            return;
        }

        $post_id = self::postIdForRemoteObject($object_uri);

        if ($post_id === null) {
            return;
        }

        // PinnedPost::pin() refuses a post that isn't this actor's, which is
        // also the check that stops one server pinning another's writing to a
        // profile here.
        if ($pinning) {
            PinnedPost::pin((int) $actor -> userId, $post_id);

            return;
        }

        PinnedPost::unpin((int) $actor -> userId, $post_id);
    }

    /** A remote account blocking a member here. */
    private static function handleBlock(array $activity, string $actor_uri): void
    {
        $target_uri = self::objectURI($activity['object'] ?? null);
        $blocker = User::byRemoteActorURI($actor_uri);

        if ($target_uri === null || $blocker === null) {
            return;
        }

        ActivityPubBlock::received($target_uri, $blocker);
    }

    /** Somebody a member here follows has changed servers. */
    private static function handleMove(array $activity, string $actor_uri): void
    {
        $mover = User::byRemoteActorURI($actor_uri);

        if ($mover !== null) {
            ActivityPubMove::received($activity, $mover);
        }
    }

    /** An abuse report from another server about something here. */
    private static function handleFlag(array $activity, string $actor_uri): void
    {
        $reporter = User::byRemoteActorURI($actor_uri);

        if ($reporter === null) {
            return;
        }

        ActivityPubFlag::received($activity, $reporter);
    }

    /** A favourite from elsewhere, which is the same row a member here would make. */
    private static function handleLike(array $activity, string $actor_uri): void
    {
        $object_uri = self::objectURI($activity['object'] ?? null);
        $actor = User::byRemoteActorURI($actor_uri);

        if ($object_uri === null || $actor === null) {
            return;
        }

        ActivityPubReaction::liked($object_uri, $actor);
    }

    /** A boost of a post here - or, from a relay, the firehose arriving. */
    private static function handleAnnounce(array $activity, string $actor_uri): void
    {
        $object_uri = self::objectURI($activity['object'] ?? null);

        if ($object_uri === null) {
            return;
        }

        // A relay announces other servers' posts at us rather than boosting
        // ours, so what it names is something to go and read, not something to
        // credit an account here with passing on.
        if (Relay::isSubscribed($actor_uri)) {
            self::relayedPost($object_uri, $actor_uri);

            return;
        }

        $actor = User::byRemoteActorURI($actor_uri);
        $activity_uri = $activity['id'] ?? null;

        if ($actor === null || !is_string($activity_uri) || $activity_uri === '') {
            return;
        }

        ActivityPubReaction::announced($object_uri, $actor, $activity_uri);
    }

    /**
     * A post a relay has named - noted for later, not read now.
     *
     * Reading it means fetching it from the server that wrote it, and doing
     * that here would hold a PHP worker for as long as that server takes to
     * answer, while the relay waits on us. At the rate the inbox already
     * allows, that is enough held workers to exhaust the pool and stop the
     * site answering. So this does database work only and returns; the reading
     * happens in bin/federation-worker.php (see RelayFetch).
     */
    private static function relayedPost(string $object_uri, string $relay_actor_uri): void
    {
        $relay = Relay::byActorURI($relay_actor_uri);

        if ($relay === null || !self::isStorableObjectURI($object_uri) || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        // Already held - through a follow, or through another relay naming the
        // same post. Recorded against this relay too, so it shows in the
        // firehose feed rather than only wherever it first landed.
        $existing = self::postIdForRemoteObject($object_uri);

        if ($existing !== null) {
            Relay::recordPost($existing, (int) $relay -> relayId);

            return;
        }

        if (RemoteServer::isBlockedURL($object_uri)) {
            return;
        }

        RelayFetch::enqueue($object_uri, (int) $relay -> relayId);
    }

    /**
     * Reads a post a relay named and stores it, called by the federation
     * worker rather than by an inbox request - see relayedPost() above.
     *
     * The fetch is what authenticates it: a relay is a stranger passing on a
     * claim about somebody else's writing, so nothing it says is taken on
     * trust and the post is read from its own server, signed, which works
     * against an instance in secure mode.
     *
     * @return bool false only when the read itself failed and is worth one
     *              more try. Everything else - stored, blocked, not a post we
     *              take - is finished with, however it turned out.
     */
    public static function fetchRelayedPost(string $object_uri, int $relay_id): bool
    {
        // Re-checked rather than assumed: this was queued some time ago, and
        // the post may have arrived through a follow in the meantime, or its
        // server may have been blocked since.
        if (RemoteObjectTombstone::isTombstoned($object_uri) || RemoteServer::isBlockedURL($object_uri)) {
            return true;
        }

        $existing = self::postIdForRemoteObject($object_uri);

        if ($existing !== null) {
            Relay::recordPost($existing, $relay_id);

            return true;
        }

        $object = ActivityPubFetch::object($object_uri);

        // Unreachable, too slow, or refused - the one case worth asking again
        // about, since the server may simply have been busy.
        if ($object === null) {
            return false;
        }

        if (!in_array($object['type'] ?? null, ['Note', 'Question'], true)) {
            return true;
        }

        // The document has to be the one that was asked for, and has to be
        // attributed to somebody on its own host: a relay could otherwise name
        // a URI whose server hands back a post claiming to be by an account
        // somewhere else entirely.
        $id = is_string($object['id'] ?? null) ? $object['id'] : '';
        $attributed_to = $object['attributedTo'] ?? null;

        if ($id !== $object_uri || !is_string($attributed_to) || !RemoteActor::sameHost($attributed_to, $object_uri)) {
            return true;
        }

        // A reply read out of context has nothing here to hang from, the same
        // rule the follow path applies - and a relay carries a great many of
        // them.
        if (isset($object['inReplyTo']) && $object['inReplyTo'] !== null && $object['inReplyTo'] !== '') {
            return true;
        }

        $author = RemoteActor::ensureKnown($attributed_to);

        if ($author === null || $author -> banned === 1) {
            return true;
        }

        $post_id = self::storeNote($object, $object_uri, $author);

        if ($post_id === null) {
            return true;
        }

        Relay::recordPost($post_id, $relay_id);

        // Somebody here may follow this author anyway - the relay just got the
        // post here first. Fanning out is what puts it in their feed as well as
        // in the firehose; with no follower it writes nothing.
        Timeline::fanOutRemotePost($attributed_to, $post_id);

        return true;
    }

    /** An object reference is either the URI itself or a document carrying its id. */
    private static function objectURI(mixed $object): ?string
    {
        if (is_string($object) && $object !== '') {
            return $object;
        }

        if (is_array($object) && is_string($object['id'] ?? null) && $object['id'] !== '') {
            return $object['id'];
        }

        return null;
    }

    /**
     * Someone out on the Fediverse following a member here.
     *
     * Accepted without asking, because everything on a Glommer server is
     * public: there is nothing for approval to protect, and holding a request
     * that will always be granted only makes people wait.
     *
     * The Accept is signed by the member being followed rather than by the
     * instance - the Follow was addressed to them, and the far side matches the
     * answer against the actor it asked.
     */
    private static function handleFollow(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;
        $target_uri = is_string($object) ? $object : (is_array($object) ? ($object['id'] ?? null) : null);
        $follow_activity_id = $activity['id'] ?? null;

        if (!is_string($target_uri) || !is_string($follow_activity_id) || $follow_activity_id === '') {
            return;
        }

        $target = ActivityPubActor::localUserFromURI($target_uri);

        if ($target === null || $target -> userId === null) {
            return;
        }

        // The signature already proved who this is, so the row is on file with
        // their inbox on it - which is where the Accept goes and where their
        // copies of this member's posts will go from now on.
        $follower = User::byRemoteActorURI($actor_uri);

        if ($follower === null || !is_string($follower -> remoteActorInboxURL) || $follower -> remoteActorInboxURL === '') {
            return;
        }

        FediverseFollower::add(
            (int) $target -> userId,
            $actor_uri,
            $follower -> remoteActorInboxURL,
            is_string($follower -> remoteActorSharedInboxURL) ? $follower -> remoteActorSharedInboxURL : null,
            $follow_activity_id
        );

        ActivityPubDelivery::postAs($target, $follower -> remoteActorInboxURL, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => ActivityPubActor::uriFor($target) . '#accepts/' . bin2hex(random_bytes(8)),
            'type' => 'Accept',
            'actor' => ActivityPubActor::uriFor($target),
            'object' => [
                'id' => $follow_activity_id,
                'type' => 'Follow',
                'actor' => $actor_uri,
                'object' => ActivityPubActor::uriFor($target),
            ],
        ]);
    }

    /**
     * An Undo withdrawing something previously sent. Only Follow is acted on:
     * it is the one whose absence changes what this server does, since it stops
     * their copies of a member's posts.
     */
    private static function handleUndo(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;

        if (!is_array($object)) {
            return;
        }

        // Lifting a block, which lets the two see each other again.
        if (($object['type'] ?? null) === 'Block') {
            $target = self::objectURI($object['object'] ?? null);
            $blocker = User::byRemoteActorURI($actor_uri);

            if ($target !== null && $blocker !== null) {
                ActivityPubBlock::withdrawn($target, $blocker);
            }

            return;
        }

        // Withdrawing a reaction, which is the row simply going away again.
        if (in_array($object['type'] ?? null, ['Like', 'Announce'], true)) {
            $target = self::objectURI($object['object'] ?? null);
            $actor = User::byRemoteActorURI($actor_uri);

            if ($target !== null && $actor !== null) {
                if ($object['type'] === 'Like') {
                    ActivityPubReaction::unliked($target, $actor);
                } else {
                    ActivityPubReaction::unannounced($target, $actor);
                }
            }

            return;
        }

        if (($object['type'] ?? null) !== 'Follow') {
            return;
        }

        $target_uri = $object['object'] ?? null;
        $target_uri = is_string($target_uri) ? $target_uri : (is_array($target_uri) ? ($target_uri['id'] ?? null) : null);

        if (!is_string($target_uri)) {
            return;
        }

        $target = ActivityPubActor::localUserFromURI($target_uri);

        if ($target === null || $target -> userId === null) {
            return;
        }

        // Keyed on the signed actor, never on whoever the activity claims to be
        // about - otherwise anyone could unfollow on someone else's behalf.
        FediverseFollower::remove((int) $target -> userId, $actor_uri);
    }

    private static function handleAccept(array $activity, string $actor_uri): void
    {
        $relay = self::answeredRelay($activity, $actor_uri);

        if ($relay !== null) {
            Relay::accepted((int) $relay -> relayId);

            return;
        }

        $follow = self::answeredFollow($activity, $actor_uri);

        if ($follow === null) {
            return;
        }

        $accepted_status = 'accepted';

        DB::run('
UPDATE `RemoteFollows`
    SET `status` = ?
    WHERE `remoteFollowId` = ?
', 'si', $accepted_status, $follow -> remoteFollowId);
    }

    /**
     * A refused follow is removed rather than parked: leaving it pending
     * forever would keep showing the person a request that is never coming,
     * and re-submitting the handle is what asking again should mean.
     */
    private static function handleReject(array $activity, string $actor_uri): void
    {
        $relay = self::answeredRelay($activity, $actor_uri);

        if ($relay !== null) {
            Relay::rejected((int) $relay -> relayId);

            return;
        }

        $follow = self::answeredFollow($activity, $actor_uri);

        if ($follow === null) {
            return;
        }

        DB::run('
DELETE
    FROM `RemoteFollows`
    WHERE `remoteFollowId` = ?
', 'i', $follow -> remoteFollowId);
    }

    /**
     * The one Follow an Accept/Reject answers, matched on the activity id
     * recorded when it was sent. Without that, any followed account could flip
     * its own follow to accepted (or drop it) by asserting a response we never
     * asked for.
     *
     * Returning the row rather than a yes/no is what keeps the answer to one
     * member's follow from moving another's. Each member holds their own edge
     * to a remote account, so several rows can share this actor URI, and an
     * Accept naming one of them says nothing about the rest.
     *
     * A server that echoes the Follow back only as a bare URI string, rather
     * than the embedded object, is handled the same way - it's the id either
     * way.
     */
    /**
     * The relay subscription an Accept or Reject answers, matched the same way
     * a member's follow is: on the activity id this server recorded when it
     * sent the Follow. Without that, a server could grant itself a
     * subscription here by asserting an answer to a request nobody made.
     */
    private static function answeredRelay(array $activity, string $actor_uri): ?Relay
    {
        $object = $activity['object'] ?? null;
        $follow_activity_id = is_array($object) ? ($object['id'] ?? null) : $object;

        if (!is_string($follow_activity_id) || $follow_activity_id === '') {
            return null;
        }

        return Relay::answering($actor_uri, $follow_activity_id);
    }

    private static function answeredFollow(array $activity, string $actor_uri): ?RemoteFollow
    {
        $object = $activity['object'] ?? null;
        $follow_activity_id = is_array($object) ? ($object['id'] ?? null) : $object;

        if (!is_string($follow_activity_id) || $follow_activity_id === '') {
            return null;
        }

        return DB::row('
SELECT `remoteFollowId`
    FROM `RemoteFollows`
    WHERE `remoteActorURI` = ? AND `followActivityId` = ?
', 'RemoteFollow', 'ss', $actor_uri, $follow_activity_id);
    }

    private static function handleCreate(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;

        if (!is_array($object)) {
            return;
        }

        // A post carrying a poll arrives as a Question rather than a Note -
        // there is no separate poll object in ActivityPub, the type simply
        // changes - so it is ingested as the post it is.
        if (($object['type'] ?? null) === 'Question') {
            self::ingestNote($object, $actor_uri);

            return;
        }

        if (($object['type'] ?? null) !== 'Note') {
            return;
        }

        // A vote is a Note with a name, no content and an inReplyTo pointing at
        // a poll. Checked before anything else looks at inReplyTo, because to
        // the reply path a vote is indistinguishable from a reply, and taking
        // it as one would file an empty post in the thread for every answer
        // anybody gave.
        if (ActivityPubPollVote::isVote($object)) {
            $voter = User::byRemoteActorURI($actor_uri);

            if ($voter !== null && $voter -> banned !== 1) {
                ActivityPubPollVote::received($object, $voter);
            }

            return;
        }

        // A Note addressed to one member here and to nobody public is a direct
        // message, and belongs in their inbox rather than in a feed. Decided on
        // the absence of a public audience rather than on a mention, since a
        // public post can name someone too.
        if (ActivityPubMessage::isDirect($object, $activity)) {
            $sender = User::byRemoteActorURI($actor_uri);

            if ($sender !== null) {
                ActivityPubMessage::received($object, $activity, $sender);
            }

            return;
        }

        self::ingestNote($object, $actor_uri);
    }

    /** The actor types ActivityPub defines - any of them can carry a signing key. */
    private const ACTOR_TYPES = ['Person', 'Service', 'Application', 'Group', 'Organization'];

    private static function handleUpdate(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;

        if (!is_array($object)) {
            return;
        }

        // Somebody's profile changed on their own server - or, the reason this
        // matters, their signing key did. What this server holds is stale
        // either way, so it goes back and reads the actor again. Only ever for
        // the account that signed the delivery: a server may update its own.
        if (in_array($object['type'] ?? null, self::ACTOR_TYPES, true)) {
            if (($object['id'] ?? null) === $actor_uri) {
                RemoteActor::refresh($actor_uri);
            }

            return;
        }

        // A Question is a post like any other here, and its restatement is also
        // how a poll's running totals arrive - the origin re-sends the whole
        // object every time somebody answers, since ActivityPub has no way to
        // send just a number.
        if (!in_array($object['type'] ?? null, ['Note', 'Question'], true)) {
            return;
        }

        $object_uri = $object['id'] ?? null;

        if (!is_string($object_uri) || $object_uri === '') {
            return;
        }

        $author = User::byRemoteActorURI($actor_uri);

        if ($author === null || $author -> banned === 1) {
            return;
        }

        $post = self::postAuthoredBy($object_uri, (int) $author -> userId);

        if ($post === null || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        // Only the tallies move, and only for a poll we already hold: an Update
        // is not a reason to start holding one, and rewriting the choices would
        // change what the votes already counted were cast for.
        $poll = Poll::forPost((int) $post -> postId);

        if ($poll !== null) {
            Poll::updateTallies($poll, $object);
        }

        // What the sender says its own shortcodes mean. Recorded before the
        // post, so the body renders with them from the first view.
        CustomEmoji::learnFrom(is_array($object['tag'] ?? null) ? $object['tag'] : [], $object_uri);

        [$description, $description_delta] = self::deltaFromContent(is_string($object['content'] ?? null) ? $object['content'] : '');

        DB::run('
UPDATE `Posts`
    SET `description` = ?, `descriptionDelta` = ?, `sensitive` = ?, `editedAt` = current_timestamp()
    WHERE `postId` = ?
', 'ssii', $description, $description_delta, ($object['sensitive'] ?? false) === true ? 1 : 0, $post -> postId);
    }

    private static function handleDelete(array $activity, string $actor_uri): void
    {
        $object = $activity['object'] ?? null;
        $object_uri = is_array($object) ? ($object['id'] ?? null) : $object;

        if (!is_string($object_uri) || $object_uri === '') {
            return;
        }

        $author = User::byRemoteActorURI($actor_uri);

        if ($author === null) {
            return;
        }

        // Scoped to a post this actor actually wrote. Acting on any URI the
        // sender names would let one followed account delete another's posts
        // here - and, because a tombstone is permanent, pre-emptively block a
        // post it names from ever being ingested in the first place.
        $post = self::postAuthoredBy($object_uri, (int) $author -> userId);

        if ($post === null) {
            return;
        }

        // Post::delete() rather than a bare row delete: it also clears
        // notifications pointing at the post and the media files belonging to
        // any local reply underneath it, and records the tombstone that stops
        // the origin server's next redelivery from recreating this.
        Post::delete((int) $post -> postId);
    }

    private static function ingestNote(array $object, string $actor_uri): void
    {
        $object_uri = $object['id'] ?? null;

        if (!is_string($object_uri) || !self::isStorableObjectURI($object_uri) || RemoteObjectTombstone::isTombstoned($object_uri)) {
            return;
        }

        // The note's id has to belong to the server that signed for it. A
        // server is only ever entitled to speak for its own objects, the same
        // rule RemoteActor::fetch applies to an actor's id - and here the
        // consequence of skipping it is permanent, because the URI column is
        // unique: an actor anywhere could claim a URI on someone else's host
        // and block the real note from ever being ingested, while every reply
        // and Like naming that URI resolved to the impostor's copy instead.
        if (!RemoteActor::sameHost($object_uri, $actor_uri)) {
            return;
        }

        if (self::postIdForRemoteObject($object_uri) !== null) {
            return;
        }

        // A note that names a different author than the account that signed
        // for it is refused rather than filed under the signer: accepting it
        // would let one actor claim another's object URI, and since that URI
        // is unique, permanently block the real note from ever arriving.
        // Only enforced when it's actually stated - some servers leave it off
        // the embedded object, and the signer is the right attribution then.
        $attributed_to = $object['attributedTo'] ?? null;

        if (is_string($attributed_to) && $attributed_to !== '' && $attributed_to !== $actor_uri) {
            return;
        }

        $author = User::byRemoteActorURI($actor_uri);

        if ($author === null || $author -> banned === 1) {
            return;
        }

        $parent_id = null;
        $in_reply_to = $object['inReplyTo'] ?? null;

        if (is_string($in_reply_to) && $in_reply_to !== '') {
            $parent_id = self::postIdForRemoteObject($in_reply_to);

            // Not in reply to anything on this site (a post we don't hold) -
            // ignored outright, per the scoping decision: no dangling replies
            // with unresolvable context.
            if ($parent_id === null) {
                return;
            }
        }

        $post_id = self::storeNote($object, $object_uri, $author, $parent_id);

        // Only top-level posts fan out to followers' feeds - a reply is
        // reached through the parent post's own reply list, the same as any
        // other reply, visible to whoever can already see that parent.
        if ($post_id !== null && $parent_id === null) {
            Timeline::fanOutRemotePost($actor_uri, $post_id);
        }
    }

    /**
     * Writes an inbound post and everything hanging off it, once whoever is
     * calling has established that it may be written: that the object belongs
     * to the server vouching for it, and who its author is here.
     *
     * Shared by the two ways a post arrives - delivered by the account that
     * wrote it, or named at us by a relay - so there is one definition of what
     * storing one means. Whose feeds it then reaches is the caller's business,
     * because that is the part the two paths genuinely differ on.
     *
     * @param array<string, mixed> $object
     * @return int|null the new post's id, or null if the insert lost a race
     */
    private static function storeNote(array $object, string $object_uri, User $author, ?int $parent_id = null): ?int
    {
        // What the sender says its own shortcodes mean. Recorded before the
        // post, so the body renders with them from the first view.
        CustomEmoji::learnFrom(is_array($object['tag'] ?? null) ? $object['tag'] : [], $object_uri);

        [$description, $description_delta] = self::deltaFromContent(is_string($object['content'] ?? null) ? $object['content'] : '');

        // The sending server's own classification, taken at its word: it is the
        // only party that knows what it is sending, and the cost of trusting it
        // is a cover over media that didn't need one.
        $sensitive = ($object['sensitive'] ?? false) === true ? 1 : 0;

        // A remote quote post: quoteUrl and _misskey_quote are the spellings
        // most of the network sends. Resolved only against posts already held
        // here - a quote of something this server has never seen renders as
        // plain words, the same as a quote whose target was deleted. Never a
        // fetch: a quote is not an invitation to go crawling.
        $quoted_post_id = null;
        $quoted_uri = $object['quoteUrl'] ?? $object['_misskey_quote'] ?? null;

        if (is_string($quoted_uri) && $quoted_uri !== '') {
            $quoted_post_id = self::localPostIdFor($quoted_uri);
        }

        try {
            DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `description`, `descriptionDelta`, `remoteObjectURI`, `sensitive`, `quotedPostId`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'iisssii', $author -> userId, $parent_id, $description, $description_delta, $object_uri, $sensitive, $quoted_post_id);
        } catch (\mysqli_sql_exception $exception) {
            // 1062 = the unique remoteObjectURI rejected a post already held.
            // Two relays naming the same post at once is an ordinary race, not
            // a failure worth raising.
            if ($exception -> getCode() === 1062) {
                return null;
            }

            throw $exception;
        }

        $post_id = (int) mysqli_insert_id(DB::connection());

        self::storeAttachments($object['attachment'] ?? null, $post_id);

        // A Question carries its choices with it. Stored now rather than
        // fetched later: the post is already here, and a poll that appeared
        // some time after the post it belongs to would read as a different
        // thing arriving.
        if (($object['type'] ?? null) === 'Question') {
            Poll::fromQuestion($post_id, $object);
        }

        return $post_id;
    }

    /**
     * How many attachments one post may bring. Every server sets its own
     * limit, so this is ours: a post claiming hundreds costs a bounded number
     * of rows rather than however many it asked for.
     */
    private const MAX_ATTACHMENTS = 8;

    /**
     * The pictures, video and sound on an inbound post. Only the address of
     * each is kept - the file itself stays on the server that published it and
     * is proxied per request (see RemoteMedia), so nothing here downloads
     * anything or commits this server to hosting it.
     */
    private static function storeAttachments(mixed $attachment, int $post_id): void
    {
        if (!is_array($attachment)) {
            return;
        }

        // A lone attachment sometimes arrives unwrapped rather than as a
        // one-element list.
        if (isset($attachment['url']) || isset($attachment['mediaType'])) {
            $attachment = [$attachment];
        }

        $stored = 0;

        foreach ($attachment as $entry) {
            if ($stored >= self::MAX_ATTACHMENTS) {
                return;
            }

            if (!is_array($entry)) {
                continue;
            }

            // A mediaType we don't serve is a refusal, not a reason to fall
            // back on the object type - the fallback is for the servers that
            // leave mediaType off entirely.
            $media_type = $entry['mediaType'] ?? null;
            $type = is_string($media_type) && $media_type !== ''
                ? RemoteMedia::itemTypeFor($media_type)
                : self::itemTypeForObjectType($entry['type'] ?? null);

            $url = self::attachmentURL($entry['url'] ?? null);

            if ($type === null || $url === null) {
                continue;
            }

            $name = $entry['name'] ?? null;

            FeedItem::createRemote(
                $post_id,
                $type,
                $url,
                is_string($name) && $name !== '' ? mb_substr($name, 0, FeedItem::MAX_ALT_TEXT_LENGTH) : null
            );

            $stored++;
        }
    }

    private static function itemTypeForObjectType(mixed $type): ?string
    {
        return match ($type) {
            'Image' => 'ImageItem',
            'Video' => 'VideoItem',
            'Audio' => 'AudioItem',
            default => null,
        };
    }

    /** ActivityStreams is loose here: a string, a Link object, or a list of either. */
    private static function attachmentURL(mixed $url): ?string
    {
        if (is_string($url)) {
            return self::usableMediaURL($url);
        }

        if (!is_array($url)) {
            return null;
        }

        if (is_string($url['href'] ?? null)) {
            return self::usableMediaURL($url['href']);
        }

        foreach ($url as $entry) {
            if (is_array($entry) && is_string($entry['href'] ?? null)) {
                $usable = self::usableMediaURL($entry['href']);

                if ($usable !== null) {
                    return $usable;
                }
            }
        }

        return null;
    }

    private static function usableMediaURL(string $url): ?string
    {
        // https only - the proxy fetches this later, and a plain-HTTP fetch
        // would put the file on the wire in the clear. Length is checked
        // because a URL too long for the column is one that could be stored
        // truncated and then fetched as something else entirely.
        return str_starts_with($url, 'https://') && strlen($url) <= FeedItem::MAX_REMOTE_URL_LENGTH
            ? $url
            : null;
    }

    /** Posts.description is a TEXT column; MySQL runs strict, so an oversized value errors rather than truncating. */
    private const MAX_DESCRIPTION_BYTES = 65535;

    /** @return array{0: ?string, 1: ?string} [description, descriptionDelta] */
    private static function deltaFromContent(string $content): array
    {
        // Flattened by the shared reducer, then run through the same
        // Delta::sanitize() a locally-typed post goes through - a Delta insert
        // renders as a text node, so the decoded text stays inert.
        $plain = RemoteHTML::toPlainText($content);

        if ($plain === '') {
            return [null, null];
        }

        $ops = Delta::sanitize([['insert' => $plain . "\n"]]);
        $plaintext = Delta::plainText($ops);

        if ($plaintext === '') {
            return [null, null];
        }

        // mb_strcut, not substr: cuts on a byte budget without splitting a
        // multi-byte character in half.
        if (strlen($plaintext) > self::MAX_DESCRIPTION_BYTES) {
            $plaintext = mb_strcut($plaintext, 0, self::MAX_DESCRIPTION_BYTES, 'UTF-8');
            $ops = Delta::sanitize([['insert' => $plaintext . "\n"]]);
        }

        $delta_json = json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($delta_json === false) {
            return [null, null];
        }

        return [$plaintext, $delta_json];
    }

    /** Posts.remoteObjectURI and the tombstone table's key are both varchar(255). */
    private const MAX_OBJECT_URI_LENGTH = 255;

    /**
     * Whether an object URI can actually be stored. A longer-than-column
     * value would abort the insert under strict mode as an uncaught database
     * exception rather than a declined delivery, so it's rejected here where
     * the untrusted value arrives.
     */
    private static function isStorableObjectURI(string $object_uri): bool
    {
        if ($object_uri === '' || strlen($object_uri) > self::MAX_OBJECT_URI_LENGTH) {
            return false;
        }

        return in_array(strtolower((string) parse_url($object_uri, PHP_URL_SCHEME)), ['http', 'https'], true)
            && is_string(parse_url($object_uri, PHP_URL_HOST));
    }

    /**
     * The post a URI names, whichever side it lives on: a remote object URI
     * this server already holds a copy of, or one of this server's own
     * permalinks - the valuable case, since a quote arriving from elsewhere
     * most often quotes the post it was delivered about.
     */
    private static function localPostIdFor(string $uri): ?int
    {
        $known_remote = self::postIdForRemoteObject($uri);

        if ($known_remote !== null) {
            return $known_remote;
        }

        // Our own permalink shape, host-checked: /users/{slug}/{postId}.
        $host = strtolower((string) parse_url($uri, PHP_URL_HOST));

        if ($host !== ServerURL::host()) {
            return null;
        }

        if (preg_match('#/users/([^/]+)/([0-9]+)$#', (string) parse_url($uri, PHP_URL_PATH), $match) !== 1) {
            return null;
        }

        $post = DB::row('
SELECT `Posts`.`postId`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`slug` = ? AND `Posts`.`remoteObjectURI` IS NULL
', 'Post', 'is', (int) $match[2], $match[1]);

        return $post !== null ? (int) $post -> postId : null;
    }

    private static function postIdForRemoteObject(string $remote_object_uri): ?int
    {
        $post = DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $remote_object_uri);

        return $post !== null ? (int) $post -> postId : null;
    }

    /**
     * The post at this object URI, but only when the given shadow account is
     * the one that authored it - the authorization gate for any activity that
     * mutates existing content, so a delivery can only ever act on its own
     * sender's posts.
     */
    private static function postAuthoredBy(string $remote_object_uri, int $author_user_id): ?Post
    {
        return DB::row('
SELECT `postId`, `userId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ? AND `userId` = ?
', 'Post', 'si', $remote_object_uri, $author_user_id);
    }

}
