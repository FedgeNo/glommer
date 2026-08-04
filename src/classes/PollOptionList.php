<?php

declare(strict_types=1);

/**
 * The choices on one poll, in the order they were written - an ordinary loader
 * whose page is never full, since a poll holds at most Poll::MAX_OPTIONS.
 *
 * The tally and the reader's own choice ride along on each row as correlated
 * subqueries rather than being fetched per option, so a poll costs one query
 * however many choices it offers.
 */
class PollOptionList extends ItemList
{
    public const PAGE_SIZE = Poll::MAX_OPTIONS;

    public ?string $class = 'PollOptionList';

    public ?int $pollId = null;

    /** Set by the poll, since an option list cannot work these out on its own. */
    public ?int $viewerId = null;
    public bool $showResults = false;
    public bool $multiple = false;
    public int $totalVotes = 0;

    /** @return PollOption[] */
    protected function rows(): array
    {
        // A logged-out reader has no choice to mark, and userId is unsigned, so
        // 0 matches nothing rather than needing a second query shape.
        $viewer_id = (int) $this -> viewerId;

        return DB::rows('
SELECT `o`.*,
        (
            SELECT COUNT(*)
                FROM `PollVotes` `v`
                WHERE `v`.`pollOptionId` = `o`.`pollOptionId`
        ) AS `localVoteCount`,
        (
            SELECT COUNT(*)
                FROM `PollVotes` `v`
                WHERE `v`.`pollOptionId` = `o`.`pollOptionId` AND `v`.`userId` = ?
        ) AS `chosen`
    FROM `PollOptions` `o`
    WHERE `o`.`pollId` = ?
    ORDER BY `o`.`position`
', 'PollOption', 'ii', $viewer_id, (int) $this -> pollId);
    }

    /**
     * Each option renders by what the whole poll knows - whether results are
     * being shown, and out of how many votes - so the rows take that from here
     * on their way through.
     *
     * @param PollOption[] $items
     * @return PollOption[]
     */
    protected function arrange(array $items): array
    {
        foreach ($items as $option) {
            $option -> showResults = $this -> showResults;
            $option -> multiple = $this -> multiple;
            $option -> totalVotes = $this -> totalVotes;
        }

        return $items;
    }

    /**
     * The options as the client rebuilds them. Not the ItemLoader shape: there
     * is no next page to report on a list that is complete by construction.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toJSON(): array
    {
        $this -> markRendered();

        return array_map(static fn (PollOption $option): array => $option -> toPayload(), $this -> items);
    }
}
