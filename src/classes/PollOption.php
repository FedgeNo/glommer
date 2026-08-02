<?php

declare(strict_types=1);

/**
 * One choice on a poll.
 *
 * It is a control while this reader can still pick it and a result once they
 * cannot - voted, or closed. Both carry the same text, so the thing being
 * chosen and the thing being reported are visibly one option.
 *
 * The tally is either ours to count or the origin server's to state, and
 * remoteVoteCount is which: null means the poll is ours and the figure is a
 * count of PollVotes rows, set means it came from elsewhere, where this server
 * holds no votes and their number is the only one there is.
 */
class PollOption extends Div
{
    public ?string $class = 'PollOption';

    // Declared so a row fetched via DB::rows() doesn't set them as deprecated
    // dynamic properties.
    public ?int $pollOptionId = null;
    public ?int $pollId = null;
    public ?int $position = null;
    public ?string $title = null;
    public ?int $remoteVoteCount = null;

    // Hydrated alongside the row by PollOptionList's query rather than fetched
    // per option: a count and a flag each cost a correlated subquery there, and
    // one query per option here.
    public ?int $localVoteCount = null;
    public ?int $chosen = null;

    /** Set by the poll, since an option cannot know these on its own. */
    public bool $showResults = false;
    public bool $multiple = false;
    public int $totalVotes = 0;

    /** Whatever the tally is, from whichever side is entitled to state it. */
    public function voteCount(): int
    {
        return $this -> remoteVoteCount !== null ? (int) $this -> remoteVoteCount : (int) $this -> localVoteCount;
    }

    /**
     * This option's share of the vote, rounded to a whole number. Zero total
     * votes is 0 rather than a division by it - a poll nobody has answered
     * shows empty bars, not an error.
     */
    public function share(): int
    {
        return $this -> totalVotes > 0 ? (int) round(($this -> voteCount() / $this -> totalVotes) * 100) : 0;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'pollOptionId' => (int) $this -> pollOptionId,
            'title' => (string) $this -> title,
            'voteCount' => $this -> voteCount(),
            'share' => $this -> share(),
            'chosen' => (int) $this -> chosen === 1,
        ];
    }

    public function toDOM(): \DOMElement
    {
        $this -> addContent($this -> showResults
            ? new PollOptionResult((string) $this -> title, $this -> share(), $this -> voteCount(), (int) $this -> chosen === 1)
            : new PollOptionControl((int) $this -> pollOptionId, (string) $this -> title, $this -> multiple));

        return parent::toDOM();
    }
}
