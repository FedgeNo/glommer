import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * Client twin of Poll.php - the same DOM from the same payload, class for
 * class, and the voting that turns one into the other.
 *
 * Whether the controls or the answers are shown is not decided here: it depends
 * on who is asking and whether they have voted, which only the server knows, so
 * showResults arrives already settled.
 */
export class Poll {
    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.PollVoteButton');
            if (!button) return;

            const poll = button.closest('.Poll');
            if (!poll) return;

            const chosen = [...poll.querySelectorAll('.PollVoteInput:checked')].map((input) => Number(input.value));

            if (chosen.length === 0) return;

            // Disabled for the round trip rather than after it: a second click
            // while the first is in flight comes back refused as a repeat vote,
            // and the reader would be told their own answer failed.
            Working.start(button);

            const data = await Api.post('/api/poll-vote', {
                pollId: Number(button.dataset.pollId),
                optionIds: chosen,
            });

            if (!data) {
                Working.stop(button);
                return;
            }

            poll.replaceWith(Poll.fromData(data.poll).element());
        });
    }

    pollId = null;
    multiple = false;
    endsAt = null;
    closed = false;
    showResults = false;
    voterCount = 0;
    options = [];

    static fromData(data) {
        const poll = new Poll();
        Object.assign(poll, data);

        return poll;
    }

    element() {
        const poll = document.createElement('section');
        poll.className = 'Poll';

        poll.appendWithSpace(this.optionListElement());

        // The button is what turns a set of ticked boxes into a vote, so it
        // exists only while there is a vote left to cast.
        if (!this.showResults) {
            poll.appendWithSpace(this.voteButtonElement());
        }

        poll.appendWithSpace(this.tallyElement());

        return poll;
    }

    optionListElement() {
        const list = document.createElement('ul');
        list.className = 'PollOptionList';

        for (const option of this.options) {
            const item = document.createElement('li');
            item.appendWithSpace(this.optionElement(option));
            list.appendWithSpace(item);
        }

        return list;
    }

    optionElement(option) {
        const wrapper = document.createElement('div');
        wrapper.className = 'PollOption';
        wrapper.appendWithSpace(this.showResults ? Poll.resultElement(option) : this.controlElement(option));

        return wrapper;
    }

    controlElement(option) {
        const label = document.createElement('label');
        label.className = 'PollOptionControl';

        const input = document.createElement('input');
        input.className = 'PollVoteInput';
        input.type = this.multiple ? 'checkbox' : 'radio';
        // One name across the group, which is what makes a set of radios a
        // single choice rather than several independent ones.
        input.name = 'pollOption';
        input.value = String(option.pollOptionId);

        label.appendWithSpace(input);
        label.appendWithSpace(Poll.titleElement(option.title));

        return label;
    }

    static resultElement(option) {
        const result = document.createElement('div');
        result.className = option.chosen ? 'PollOptionResult Chosen' : 'PollOptionResult';

        result.appendWithSpace(Poll.titleElement(option.title));

        const meter = document.createElement('meter');
        meter.className = 'PollOptionMeter';
        meter.setAttribute('value', String(option.share));
        meter.setAttribute('min', '0');
        meter.setAttribute('max', '100');
        result.appendWithSpace(meter);

        const share = document.createElement('span');
        share.className = 'PollOptionShare';
        // Trailing space, as PollOptionShare.php writes it - the count follows
        // immediately and nothing else separates them.
        share.appendChild(document.createTextNode(option.share + '% '));

        const votes = document.createElement('span');
        votes.className = 'PollOptionVotes';
        votes.textContent = option.voteCount === 1 ? '1 vote' : option.voteCount + ' votes';
        share.appendChild(votes);

        result.appendWithSpace(share);

        return result;
    }

    static titleElement(text) {
        const title = document.createElement('span');
        title.className = 'PollOptionTitle';
        title.textContent = text;

        return title;
    }

    voteButtonElement() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button PollVoteButton';
        button.dataset.pollId = String(this.pollId);
        button.textContent = 'Vote';

        return button;
    }

    tallyElement() {
        const tally = document.createElement('footer');
        tally.className = 'PollTally';
        tally.appendChild(document.createTextNode(
            this.voterCount === 1 ? '1 person voted' : this.voterCount + ' people voted'
        ));

        const deadline = document.createElement('time');
        deadline.className = 'PollDeadline';
        deadline.dateTime = this.endsAt;
        deadline.textContent = this.closed ? 'Final result' : 'Closes ' + Poll.remaining(this.endsAt);

        tally.appendChild(deadline);

        return tally;
    }

    /** Mirrors PollDeadline::remaining() - the largest unit that still says something useful. */
    static remaining(endsAt) {
        const seconds = Math.max(0, Math.floor((new Date(endsAt).getTime() - Date.now()) / 1000));

        for (const [size, unit] of [[86400, 'day'], [3600, 'hour'], [60, 'minute']]) {
            if (seconds >= size) {
                const count = Math.floor(seconds / size);

                return 'in ' + count + ' ' + (count === 1 ? unit : unit + 's');
            }
        }

        return 'in under a minute';
    }
}

ReadyHandler.add(Poll.init);
