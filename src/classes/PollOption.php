<?php

declare(strict_types=1);

/**
 * One choice on a poll.
 *
 * Renders as a control while the poll is open to this reader, and as a result
 * once it isn't - voted, or closed. Both states carry the same text, so the
 * thing being chosen and the thing being reported are visibly one option.
 *
 * The vote count is either ours to count or the origin server's to state, and
 * remoteVoteCount is which: null means this poll is ours and the tally is a
 * count of PollVotes rows, set means the poll came from elsewhere, where we
 * hold no votes and their figure is the only one there is.
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
        if ($this -> showResults) {
            $this -> addContent($this -> result());

            return parent::toDOM();
        }

        $this -> addContent($this -> control());

        return parent::toDOM();
    }

    /**
     * The option as something to pick. A radio for a poll that takes one answer
     * and a checkbox for one that takes several, which is the difference the
     * browser already knows how to enforce - so a single-answer poll cannot
     * submit two without the page being tampered with, and the server checks
     * again besides.
     */
    private function control(): Label
    {
        $label = new Label();
        $label -> mixins = ['d-flex', 'align-items-center', 'gap-2'];

        $input = new Input();
        $input -> attributes['type'] = $this -> multiple ? 'checkbox' : 'radio';
        $input -> attributes['name'] = 'pollOption';
        $input -> attributes['value'] = (string) $this -> pollOptionId;

        $label -> addContent($input);
        $label -> addContent(new PollOptionTitle((string) $this -> title));

        return $label;
    }

    /**
     * The option as an answer. The bar is a <meter> rather than a styled div:
     * a share of a total is exactly what it means, so a reader who cannot see
     * the bar is still told the number by their browser.
     */
    private function result(): Div
    {
        $result = new Div();
        $result -> class = 'PollOptionResult';

        // The reader's own choice is marked, since results replace the controls
        // entirely and there would otherwise be nothing left saying what they
        // picked.
        if ((int) $this -> chosen === 1) {
            $result -> class .= ' Chosen';
        }

        $result -> addContent(new PollOptionTitle((string) $this -> title));

        $meter = new Meter();
        $meter -> attributes['value'] = (string) $this -> share();
        $meter -> attributes['min'] = '0';
        $meter -> attributes['max'] = '100';
        $result -> addContent($meter);

        $result -> addContent(new PollOptionShare($this -> share(), $this -> voteCount()));

        return $result;
    }
}
