import { Api } from '/scripts/Api.js';
import { Strings } from '/scripts/Strings.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { Working } from '/scripts/Working.js';
import { parse_server_date } from '/scripts/utils.js';

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

        const talliedWords = Strings.for('PollTally', {
            voters: { one: '1 person voted ', other: '{count} people voted ' },
        });
        tally.appendChild(document.createTextNode(Strings.plural(talliedWords.voters, this.voterCount)));

        const deadline = document.createElement('span');
        deadline.className = 'PollDeadline';

        if (this.closed) {
            deadline.textContent = Strings.for('PollDeadline', { final: 'Final result' }).final;
        } else {
            Poll.appendClosesSentence(deadline, this.endsAt);
        }

        tally.appendChild(deadline);

        return tally;
    }

    /**
     * Mirrors PollDeadline::toDOM()'s non-closed branch: the two text nodes
     * either side of a <time> that carries only the remaining time, so a
     * translation can put the deadline anywhere in the sentence.
     */
    static appendClosesSentence(into, endsAt) {
        const words = Strings.for('PollDeadline', { closes: { before: 'Closes ', after: '' } });

        const remaining = document.createElement('time');
        remaining.dateTime = parse_server_date(endsAt).toISOString();
        remaining.textContent = Poll.remaining(endsAt);

        into.append(words.closes.before || '', remaining, words.closes.after || '');
    }

    /** Mirrors PollDeadline::remaining() - the largest unit that still says something useful. */
    static remaining(endsAt) {
        // The house parser and the corrected clock, the same pair every
        // timestamp on the page counts by: a bare "date time" string read by
        // new Date() is local time in some browsers, and the machine in front
        // of the reader is not promised to be set right.
        const seconds = Math.max(0, Math.floor((parse_server_date(endsAt).getTime() - RelativeTime.now()) / 1000));
        const words = Strings.for('PollDeadline', {
            days: { one: 'in {count} day', other: 'in {count} days' },
            hours: { one: 'in {count} hour', other: 'in {count} hours' },
            minutes: { one: 'in {count} minute', other: 'in {count} minutes' },
            underMinute: 'in under a minute',
        });

        for (const [size, key] of [[86400, 'days'], [3600, 'hours'], [60, 'minutes']]) {
            if (seconds >= size) {
                return Strings.plural(words[key], Math.floor(seconds / size));
            }
        }

        return words.underMinute;
    }
}

ReadyHandler.add(Poll.init);
