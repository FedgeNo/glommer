<?php

declare(strict_types=1);

/**
 * A poll attached to a post.
 *
 * There is no separate poll object out on the network - a post carrying one
 * simply becomes a Question rather than a Note - so there is one poll per post
 * and it has no identity of its own to publish. That is why this hangs off
 * postId rather than being addressable itself.
 *
 * A poll always ends. Every implementation that reads these requires an
 * endTime, and a result nobody can ever call final is not a result; the
 * composer offers a set of durations rather than a free date.
 *
 * For a poll that came from elsewhere this server holds no votes at all - the
 * origin counts them - so the tallies are whatever that server last said. The
 * nullable count on each option is what distinguishes the two cases; see
 * PollOption.
 */
class Poll extends Section
{
    public ?string $class = 'Poll';

    /** Enough to ask a real question, few enough to read at a glance. Matches what the rest of the network accepts. */
    public const MAX_OPTIONS = 4;

    /** A poll needs a choice to be a poll. */
    public const MIN_OPTIONS = 2;

    /** PollOptions.title is varchar(255), counted in characters on utf8mb4. */
    public const MAX_OPTION_LENGTH = 255;

    /**
     * How long a poll may run, in minutes, and what the composer offers. Fixed
     * rather than free-form: the choice is one of a handful in practice, and a
     * fixed set is one less thing to validate against a clock.
     *
     * @var array<string, int>
     */
    public const DURATIONS = [
        '5 minutes' => 5,
        '1 hour' => 60,
        '6 hours' => 360,
        '1 day' => 1440,
        '3 days' => 4320,
        '7 days' => 10080,
    ];

    // Declared so a row fetched via DB::row()/DB::rows() doesn't set them as
    // deprecated dynamic properties.
    public ?int $pollId = null;
    public ?int $postId = null;
    public ?int $multiple = null;
    public ?string $endsAt = null;
    public ?int $remoteVotersCount = null;

    /** Who is looking, so the render can show their own choices back to them. */
    public ?int $viewerId = null;

    /** Whether voting has closed. The one question every part of the render turns on. */
    public function isClosed(): bool
    {
        return strtotime((string) $this -> endsAt) <= time();
    }

    /**
     * Whether this person has already voted. A vote is final - there is no
     * changing it - so this is also what decides whether the options render as
     * controls or as results.
     */
    public function hasVoted(int $user_id): bool
    {
        return DB::row('
SELECT `pollOptionId`
    FROM `PollVotes`
    WHERE `pollId` = ? AND `userId` = ?
', 'PollVoteData', 'ii', (int) $this -> pollId, $user_id) !== null;
    }

    /**
     * How many people voted. Not a sum of the option tallies: on a
     * multiple-choice poll one person contributes to several, so adding them up
     * would report more voters than there were.
     */
    public function voterCount(): int
    {
        if ($this -> remoteVotersCount !== null) {
            return (int) $this -> remoteVotersCount;
        }

        // Counted with the poll when it was loaded for a page. Asked for here
        // only by a poll somebody built rather than selected, or one this
        // request has voted on since - the number arrived with the row and
        // does not know about that.
        if (isset($this -> localVoterCount) && !isset(self::$votedThisRequest[(int) $this -> pollId])) {
            return (int) $this -> localVoterCount;
        }

        $row = DB::row('
SELECT COUNT(DISTINCT `userId`) AS `total`
    FROM `PollVotes`
    WHERE `pollId` = ?
', 'PostCountData', 'i', (int) $this -> pollId);

        return $row === null ? 0 : (int) $row -> total;
    }

    /**
     * Results replace the controls entirely once this reader has answered or
     * the poll has closed, rather than showing both. A vote is final, so a
     * control that is still there to click would be offering something that no
     * longer exists.
     *
     * A logged-out reader is shown results too: they cannot vote, and a poll
     * that hides its answers from them says nothing at all.
     */
    private function showResultsTo(?int $viewer_id): bool
    {
        return $this -> isClosed() || $viewer_id === null || $this -> hasVoted($viewer_id);
    }

    /**
     * The poll as the client rebuilds it. Carries showResults rather than
     * leaving the client to work it out: whether the controls or the answers
     * are shown depends on who is asking, and that is the server's question.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $this -> markRendered();

        $options = new PollOptionList([
            'pollId' => (int) $this -> pollId,
            'viewerId' => $this -> viewerId,
            'showResults' => $this -> showResultsTo($this -> viewerId),
            'multiple' => (int) $this -> multiple === 1,
            'totalVotes' => $this -> voterCount(),
        ]);

        return [
            'pollId' => (int) $this -> pollId,
            'multiple' => (int) $this -> multiple === 1,
            // ISO 8601 rather than the raw column, which is a bare UTC datetime
            // that browsers parse inconsistently - and it is what the rendered
            // <time datetime> carries, so both sides say the same thing.
            'endsAt' => gmdate('c', (int) strtotime((string) $this -> endsAt)),
            'closed' => $this -> isClosed(),
            'showResults' => $this -> showResultsTo($this -> viewerId),
            'voterCount' => $this -> voterCount(),
            'options' => $options -> toJSON(),
        ];
    }

    public function toDOM(): \DOMElement
    {
        $show_results = $this -> showResultsTo($this -> viewerId);

        $options = new PollOptionList([
            'pollId' => (int) $this -> pollId,
            'viewerId' => $this -> viewerId,
            'showResults' => $show_results,
            'multiple' => (int) $this -> multiple === 1,
            'totalVotes' => $this -> voterCount(),
        ]);

        $this -> addContent($options);

        // The button is what turns a set of checked boxes into a vote, so it
        // only exists while there is a vote left to cast.
        if (!$show_results) {
            $this -> addContent(new PollVoteButton((int) $this -> pollId));
        }

        $this -> addContent(new PollTally($this -> voterCount(), (string) $this -> endsAt, $this -> isClosed()));

        return parent::toDOM();
    }

    /**
     * How many people have answered, counted alongside the poll rather than
     * asked for afterwards - see voterCount(), which reads this where it is
     * here. Distinct voters, not votes: a multiple-choice poll takes several
     * rows from one person.
     */
    private const VOTER_COUNT = '(
        SELECT COUNT(DISTINCT `PollVotes`.`userId`)
            FROM `PollVotes`
            WHERE `PollVotes`.`pollId` = `Polls`.`pollId`
    ) AS `localVoterCount`';

    /** The poll on a post, or null when it hasn't got one. */
    public static function forPost(int $post_id): ?Poll
    {
        return DB::row('
SELECT `Polls`.*, ' . self::VOTER_COUNT . '
    FROM `Polls`
    WHERE `postId` = ?
', self::class, 'i', $post_id);
    }

    /**
     * The polls on a page of posts, keyed by post. One query for the page
     * rather than one per post, the same way FeedItem::itemsForPosts batches
     * media - a feed of twenty posts should not cost twenty lookups to discover
     * that most of them are not polls.
     *
     * @param int[] $post_ids
     * @return array<int, Poll>
     */
    public static function forPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $polls = DB::rows('
SELECT `Polls`.*, ' . self::VOTER_COUNT . '
    FROM `Polls`
    WHERE `postId` IN (' . $placeholders . ')
', self::class, str_repeat('i', count($post_ids)), ...$post_ids);

        $by_post = [];

        foreach ($polls as $poll) {
            $by_post[(int) $poll -> postId] = $poll;
        }

        return $by_post;
    }

    /**
     * Attaches a poll to a post that has just been written.
     *
     * The options are stored in the order given, because a poll's choices are a
     * sequence and re-ordering them changes what a result reads as. Duplicates
     * are refused rather than merged: a vote names its option by text, so two
     * options reading the same could not be told apart when one came back.
     *
     * @param string[] $option_titles
     */
    public static function create(int $post_id, array $option_titles, bool $multiple, int $duration_minutes): ?Poll
    {
        $titles = self::cleanOptions($option_titles);

        if (count($titles) < self::MIN_OPTIONS || !in_array($duration_minutes, self::DURATIONS, true)) {
            return null;
        }

        $ends_at = gmdate('Y-m-d H:i:s', time() + ($duration_minutes * 60));

        DB::run('
INSERT INTO `Polls` (`postId`, `multiple`, `endsAt`)
    VALUES (?, ?, ?)
', 'iis', $post_id, $multiple ? 1 : 0, $ends_at);

        $poll_id = (int) mysqli_insert_id(DB::connection());

        foreach (array_values($titles) as $position => $title) {
            DB::run('
INSERT INTO `PollOptions` (`pollId`, `position`, `title`)
    VALUES (?, ?, ?)
', 'iis', $poll_id, $position, $title);
        }

        return self::forPost($post_id);
    }

    /**
     * The poll on an inbound Question, stored against the post it arrived with.
     *
     * Which key carries the choices is the only thing that says whether more
     * than one may be picked - oneOf for a single answer, anyOf for several -
     * so that is what `multiple` is read from rather than any flag.
     *
     * Every tally is recorded as the origin's, because it is: this server holds
     * none of the votes behind them and never will, short of its own members
     * answering. Those answers go out to the origin and come back inside its
     * next set of numbers.
     */
    public static function fromQuestion(int $post_id, array $object): void
    {
        $multiple = is_array($object['anyOf'] ?? null);
        $choices = $multiple ? $object['anyOf'] : ($object['oneOf'] ?? null);
        $end_time = strtotime((string) ($object['endTime'] ?? $object['closed'] ?? ''));

        // A Question with no choices is not a poll, and one with no endTime is
        // not one this server can ever call closed - both are refused rather
        // than stored as something that renders wrong forever.
        if (!is_array($choices) || $choices === [] || $end_time === false) {
            return;
        }

        DB::run('
INSERT INTO `Polls` (`postId`, `multiple`, `endsAt`, `remoteVotersCount`)
    VALUES (?, ?, ?, ?)
', 'iisi', $post_id, $multiple ? 1 : 0, gmdate('Y-m-d H:i:s', $end_time), self::statedVoters($object));

        $poll_id = (int) mysqli_insert_id(DB::connection());
        $position = 0;

        foreach ($choices as $choice) {
            if (!is_array($choice) || !is_string($choice['name'] ?? null)) {
                continue;
            }

            $title = trim(mb_substr(trim($choice['name']), 0, self::MAX_OPTION_LENGTH));

            if ($title === '' || $position >= self::MAX_OPTIONS) {
                continue;
            }

            DB::run('
INSERT IGNORE INTO `PollOptions` (`pollId`, `position`, `title`, `remoteVoteCount`)
    VALUES (?, ?, ?, ?)
', 'iisi', $poll_id, $position, $title, self::statedTally($choice));

            $position++;
        }
    }

    /**
     * Replaces the tallies on a poll that came from elsewhere, from the origin
     * server's own restatement of it.
     *
     * Without this a remote poll's numbers are frozen at whatever they were
     * when it first arrived, which for a poll still running is every number
     * except the final one. Matched on the option's text, since that is the
     * only identity the wire format gives a choice.
     *
     * Only the counts move. The options themselves are left alone: an origin
     * that rewrote them mid-poll would be changing what the votes already cast
     * were cast for.
     */
    public static function updateTallies(Poll $poll, array $object): void
    {
        $choices = $object['anyOf'] ?? $object['oneOf'] ?? null;

        if (!is_array($choices)) {
            return;
        }

        DB::run('
UPDATE `Polls`
    SET `remoteVotersCount` = ?
    WHERE `pollId` = ?
', 'ii', self::statedVoters($object), (int) $poll -> pollId);

        foreach ($choices as $choice) {
            if (!is_array($choice) || !is_string($choice['name'] ?? null)) {
                continue;
            }

            DB::run('
UPDATE `PollOptions`
    SET `remoteVoteCount` = ?
    WHERE `pollId` = ? AND `title` = ?
', 'iis', self::statedTally($choice), (int) $poll -> pollId, trim($choice['name']));
        }
    }

    /** One choice's tally, as the sending server states it. */
    private static function statedTally(array $choice): int
    {
        return max(0, (int) ($choice['replies']['totalItems'] ?? 0));
    }

    /** How many people the sending server says answered. */
    private static function statedVoters(array $object): int
    {
        return max(0, (int) ($object['votersCount'] ?? $object['toot:votersCount'] ?? 0));
    }

    /**
     * Records one person's answer.
     *
     * A vote is final. Nothing here updates an existing one, and hasVoted() is
     * checked first, because a poll whose answers can be changed after seeing
     * the running total is a poll whose result means nothing.
     *
     * Votes are stored for a poll that came from elsewhere too. They do not
     * become its tally - the origin server owns that and will send its own
     * revised numbers - but they are what stops a member here voting twice and
     * what shows them their own choice afterwards.
     *
     * @param int[] $option_ids
     */
    public static function vote(int $poll_id, int $user_id, array $option_ids): bool
    {
        // Serialized per voter per poll, and held across the insert below.
        // PollVotes is keyed on (option, user), so it stops the same option
        // being counted twice but not two different ones: without this, two
        // requests fired at once both read "has not voted" and a single-answer
        // poll takes two answers from one person. Same lock the message
        // throttle uses, for the same check-then-write race.
        $vote_lock_key = 'poll-vote:' . $poll_id . ':' . $user_id;
        RateLimiter::acquireLock($vote_lock_key);

        try {
            $recorded = self::recordVote($poll_id, $user_id, $option_ids);

            if ($recorded) {
                // The count that came with this poll described the moment it
                // was selected, and this request has just moved it. Any object
                // holding that number counts again rather than repeating it.
                self::$votedThisRequest[$poll_id] = true;
            }

            return $recorded;
        } finally {
            RateLimiter::releaseLock($vote_lock_key);
        }
    }

    /** @var array<int, true> polls this request has recorded a vote on */
    private static array $votedThisRequest = [];

    /**
     * @param int[] $option_ids
     */
    private static function recordVote(int $poll_id, int $user_id, array $option_ids): bool
    {
        $poll = DB::row('
SELECT *
    FROM `Polls`
    WHERE `pollId` = ?
', self::class, 'i', $poll_id);

        if ($poll === null || $poll -> isClosed() || $poll -> hasVoted($user_id)) {
            return false;
        }

        // Only options that belong to this poll, so a caller cannot answer one
        // poll with another's options - which would otherwise be a way to move
        // a number on a poll they were never shown.
        $chosen = self::ownOptionIds($poll_id, $option_ids);

        if ($chosen === []) {
            return false;
        }

        // A single-answer poll takes one answer. The markup already enforces it
        // with a radio group; this is the half that holds when the request does
        // not come from the page.
        if ((int) $poll -> multiple !== 1 && count($chosen) !== 1) {
            return false;
        }

        foreach ($chosen as $option_id) {
            // INSERT IGNORE for the same reason Likes uses it: a double-submit
            // is a duplicate row, not an error worth failing the whole vote on.
            DB::run('
INSERT IGNORE INTO `PollVotes` (`pollId`, `pollOptionId`, `userId`)
    VALUES (?, ?, ?)
', 'iii', $poll_id, $option_id, $user_id);
        }

        return true;
    }

    /**
     * Whichever of the given option ids actually belong to this poll, de-duped
     * and capped at the number of options a poll can have.
     *
     * @param int[] $option_ids
     * @return int[]
     */
    private static function ownOptionIds(int $poll_id, array $option_ids): array
    {
        $wanted = array_slice(array_values(array_unique(array_map('intval', $option_ids))), 0, self::MAX_OPTIONS);

        if ($wanted === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($wanted), '?'));

        $rows = DB::rows('
SELECT `pollOptionId`
    FROM `PollOptions`
    WHERE `pollId` = ? AND `pollOptionId` IN (' . $placeholders . ')
', 'PollOption', 'i' . str_repeat('i', count($wanted)), $poll_id, ...$wanted);

        return array_map(static fn (PollOption $row): int => (int) $row -> pollOptionId, $rows);
    }

    /**
     * The options as they can actually be stored: trimmed, blanks dropped,
     * over-long ones cut to the column, and no two reading the same. Returned
     * re-indexed so the caller's positions are contiguous.
     *
     * @param string[] $option_titles
     * @return string[]
     */
    public static function cleanOptions(array $option_titles): array
    {
        $titles = [];

        foreach ($option_titles as $title) {
            if (!is_string($title)) {
                continue;
            }

            $title = trim(mb_substr(trim($title), 0, self::MAX_OPTION_LENGTH));

            // Compared case-insensitively: two options a reader cannot tell
            // apart are a broken poll whatever the bytes say, and the vote that
            // names one by text could not pick between them either.
            if ($title === '' || array_filter($titles, static fn (string $kept): bool => mb_strtolower($kept) === mb_strtolower($title)) !== []) {
                continue;
            }

            $titles[] = $title;

            if (count($titles) === self::MAX_OPTIONS) {
                break;
            }
        }

        return $titles;
    }
}
