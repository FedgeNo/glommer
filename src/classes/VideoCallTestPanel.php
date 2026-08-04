<?php

declare(strict_types=1);

/**
 * A live check of whether a video call can actually be set up from this
 * browser, on this network. It sits in a member's own settings because the
 * answer is about their machine and their network rather than the server's:
 * when calls fail for one person and work for everyone else, this is the thing
 * that says which part of their setup is the reason.
 *
 * The unit tests cover what the code agrees on; this covers what only the real
 * machinery can answer - whether the browser's WebRTC stack completes a
 * negotiation, whether STUN is reachable from here, and whether the signalling
 * path answers an authenticated request.
 *
 * Each step reports what specifically failed rather than a bare pass/fail,
 * because the useful part is which link in the chain broke: a blocked STUN port
 * and a browser without WebRTC produce the same symptom for a caller (no call
 * button) and want completely different fixes.
 *
 * The steps are then read together into one verdict on whether a call would
 * work from this machine, since which step failed implies the answer but does
 * not say it - a failed STUN check means calls still work on the local network,
 * while a failed WebRTC check means none of them ever will.
 *
 * The last mile - two people actually connecting - needs a second person by
 * definition, so this says so rather than pretending to test it.
 */
class VideoCallTestPanel extends Div
{
    public ?string $class = 'VideoCallTestPanel';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new Paragraph('Runs the parts of call setup that can be checked from one browser. Everything up to an actual peer-to-peer connection is testable here; connecting to another person needs that person.');

        $this -> contents[] = new VideoCallTestButton();

        // Filled in by VideoCallTestPanel.js, a step at a time as each finishes,
        // so a step that hangs is visibly the one that hung.
        $results = new VideoCallTestResults();
        $this -> contents[] = $results;

        // The verdict the steps add up to, written here once they have all run.
        // Empty until then, and styled to take no room while it is.
        $verdict = new VideoCallTestVerdict();
        $this -> contents[] = $verdict;

        return parent::toDOM();
    }
}
