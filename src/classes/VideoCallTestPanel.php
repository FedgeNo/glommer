<?php

declare(strict_types=1);

/**
 * The admin's live check of whether a video call can actually be set up from
 * this browser, on this network. The unit tests cover what the code agrees on;
 * this covers what only the real machinery can answer - whether the browser's
 * WebRTC stack completes a negotiation, whether STUN is reachable from here, and
 * whether the signalling path answers an authenticated request.
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

        // Composed rather than a ButtonButton subclass: the identity is set at
        // runtime here, which would overwrite the chained one.
        $run = new Button();
        $run -> class = 'VideoCallTestButton';
        $run -> mixins = ['Button'];
        $run -> addContent('Run the check');
        $this -> contents[] = $run;

        // Filled in by VideoCallTestPanel.js, a step at a time as each finishes,
        // so a step that hangs is visibly the one that hung.
        $results = new UnorderedList();
        $results -> class = 'VideoCallTestResults';
        $this -> contents[] = $results;

        // The verdict the steps add up to, written here once they have all run.
        // Empty until then, and styled to take no room while it is.
        $verdict = new Paragraph();
        $verdict -> class = 'VideoCallTestVerdict';
        $this -> contents[] = $verdict;

        return parent::toDOM();
    }
}
