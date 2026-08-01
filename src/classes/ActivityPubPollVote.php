<?php

declare(strict_types=1);

/**
 * Votes crossing servers.
 *
 * A vote is not its own activity type. It travels as a Create carrying a Note
 * that has a name and no content: the name is the option's text, and inReplyTo
 * points at the poll. That is the whole format, and it is why an option's text
 * is its identity - there is no id in it to send.
 *
 * A multiple-choice answer is several of these, one per option chosen, which is
 * what the rest of the network sends and expects.
 *
 * The shape is also a hazard on the way in: a vote looks exactly like a reply to
 * anything that only checks inReplyTo, so ActivityPubInbox has to recognise it
 * before it reaches the reply path or every vote becomes an empty post in the
 * thread. See received().
 */
class ActivityPubPollVote
{
    /**
     * Whether an inbound Note is a vote rather than a reply.
     *
     * The three things together are what say so: a name, which an ordinary
     * reply has no reason to carry; an inReplyTo, naming the poll; and no
     * content, because a vote has nothing to say beyond which option it picked.
     * Content is what separates it from a titled note, which has both.
     */
    public static function isVote(array $object): bool
    {
        return is_string($object['name'] ?? null)
            && $object['name'] !== ''
            && is_string($object['inReplyTo'] ?? null)
            && $object['inReplyTo'] !== ''
            && trim((string) ($object['content'] ?? '')) === '';
    }

    /**
     * Records somebody elsewhere answering a poll of ours.
     *
     * Only a poll of ours: a vote on a poll that itself came from elsewhere
     * belongs to that server's count, and taking it here would be inventing a
     * tally we have no business keeping.
     *
     * A multiple-choice answer arrives as one of these per option chosen, so
     * this adds a single row and says nothing about whether more are coming.
     */
    public static function received(array $object, User $voter): void
    {
        $post_id = ActivityPubNote::localPostIdFor((string) $object['inReplyTo']);

        if ($post_id === null || $voter -> userId === null) {
            return;
        }

        $poll = Poll::forPost($post_id);

        if ($poll === null || $poll -> isClosed()) {
            return;
        }

        // A single-answer poll is answered once. Checked here rather than left
        // to the unique key, which would happily take a second row naming a
        // different option.
        if ((int) $poll -> multiple !== 1 && $poll -> hasVoted((int) $voter -> userId)) {
            return;
        }

        $option = DB::row('
SELECT `pollOptionId`
    FROM `PollOptions`
    WHERE `pollId` = ? AND `title` = ?
', 'PollOption', 'is', (int) $poll -> pollId, (string) $object['name']);

        // A name that matches nothing is a vote for an option this poll does
        // not offer - a stale copy of an edited poll, or simply a mistake.
        if ($option === null) {
            return;
        }

        DB::run('
INSERT IGNORE INTO `PollVotes` (`pollId`, `pollOptionId`, `userId`)
    VALUES (?, ?, ?)
', 'iii', (int) $poll -> pollId, (int) $option -> pollOptionId, (int) $voter -> userId);
    }

    /**
     * Tells a remote poll's server how a member here answered.
     *
     * Nothing is sent for a local poll: its author reads the result off this
     * database, and there is nobody else who needs telling.
     *
     * @param int[] $option_ids
     */
    public static function publish(Poll $poll, User $voter, array $option_ids): void
    {
        $target = self::remotePollTarget((int) $poll -> postId);

        if ($target === null || !ActivityPubActor::isLocal($voter) || $voter -> userId === null) {
            return;
        }

        $voter_uri = ActivityPubActor::uriFor($voter);

        foreach (self::chosenTitles((int) $poll -> pollId, $option_ids) as $option_id => $title) {
            // Derived from the voter and the option rather than random, so a
            // redelivery of the same answer is recognisably the same activity
            // rather than a second vote.
            $object_uri = $voter_uri . '#poll-votes/' . $option_id;

            FediverseDelivery::enqueue((int) $voter -> userId, [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $object_uri . '/create',
                'type' => 'Create',
                'actor' => $voter_uri,
                'to' => [$target['actorURI']],
                'object' => [
                    'id' => $object_uri,
                    'type' => 'Note',
                    'attributedTo' => $voter_uri,
                    // The option's text, exactly as the poll stated it - the
                    // far side matches on this string and nothing else.
                    'name' => $title,
                    'inReplyTo' => $target['objectURI'],
                    'to' => [$target['actorURI']],
                ],
            ], [$target['inboxURL']]);
        }
    }

    /**
     * The chosen options' text, keyed by option id, in the poll's own order.
     * Read back from the database rather than taken from the request, so what
     * is sent is what was actually recorded.
     *
     * @param int[] $option_ids
     * @return array<int, string>
     */
    private static function chosenTitles(int $poll_id, array $option_ids): array
    {
        $wanted = array_values(array_unique(array_map('intval', $option_ids)));

        if ($wanted === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($wanted), '?'));

        $rows = DB::rows('
SELECT `pollOptionId`, `title`
    FROM `PollOptions`
    WHERE `pollId` = ? AND `pollOptionId` IN (' . $placeholders . ')
    ORDER BY `position`
', 'PollOption', 'i' . str_repeat('i', count($wanted)), $poll_id, ...$wanted);

        $titles = [];

        foreach ($rows as $row) {
            $titles[(int) $row -> pollOptionId] = (string) $row -> title;
        }

        return $titles;
    }

    /**
     * Where a poll that came from elsewhere lives, and where to tell its server
     * about an answer to it.
     *
     * @return array{objectURI: string, actorURI: string, inboxURL: string}|null
     */
    private static function remotePollTarget(int $post_id): ?array
    {
        $row = DB::row('
SELECT `Posts`.`remoteObjectURI`, `Users`.`remoteActorURI`, `Users`.`remoteActorInboxURL`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Posts`.`remoteObjectURI` IS NOT NULL
', 'RemoteFlagTargetData', 'i', $post_id);

        if ($row === null
            || !is_string($row -> remoteObjectURI)
            || !is_string($row -> remoteActorURI)
            || !is_string($row -> remoteActorInboxURL)
            || $row -> remoteActorInboxURL === '') {
            return null;
        }

        return [
            'objectURI' => $row -> remoteObjectURI,
            'actorURI' => $row -> remoteActorURI,
            'inboxURL' => $row -> remoteActorInboxURL,
        ];
    }
}
