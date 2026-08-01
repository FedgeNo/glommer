import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Client twin of Poll.php - builds the same DOM from the same payload, with the
 * same class names, and handles voting.
 *
 * Whether the controls or the answers are shown is the server's decision, not
 * this file's: showResults arrives on the payload already decided, because it
 * depends on who is asking and only the server knows whether they have voted.
 */
export class Poll {
    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.PollVoteButton');
            if (!button) return;

            const poll = button.closest('.Poll');
            if (!poll) return;

            const chosen = [...poll.querySelectorAll('input[name="pollOption"]:checked')]
                .map((input) => Number(input.value));

            if (chosen.length === 0) return;

            // Disabled for the round trip rather than after it: a second click
            // while the first is in flight would be refused by the server as a
            // repeat vote, and the reader would be told their own answer failed.
            button.disabled = true;

            const data = await Api.post('/api/poll-vote', {
                pollId: Number(button.dataset.pollId),
                optionIds: chosen,
            });

            if (!data) {
                button.disabled = false;
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

        poll.appendChild(this.optionList());

        if (!this.showResults) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'Button PollVoteButton';
            button.dataset.pollId = String(this.pollId);
            button.textContent = 'Vote';
            poll.appendChild(button);
        }

        poll.appendChild(this.tally());

        return poll;
    }

    optionList() {
        const list = document.createElement('ul');
        list.className = 'PollOptionList';

        for (const option of this.options) {
            const item = document.createElement('li');
            item.appendChild(this.showResults ? this.result(option) : this.control(option));
            list.appendChild(item);
        }

        return list;
    }

    control(option) {
        const wrapper = document.createElement('div');
        wrapper.className = 'PollOption';

        const label = document.createElement('label');
        label.className = 'd-flex align-items-center gap-2';

        const input = document.createElement('input');
        input.type = this.multiple ? 'checkbox' : 'radio';
        input.name = 'pollOption';
        input.value = String(option.pollOptionId);

        label.appendChild(input);
        label.appendChild(Poll.title(option.title));
        wrapper.appendChild(label);

        return wrapper;
    }

    result(option) {
        const wrapper = document.createElement('div');
        wrapper.className = 'PollOption';

        const result = document.createElement('div');
        result.className = option.chosen ? 'PollOptionResult Chosen' : 'PollOptionResult';
        result.appendChild(Poll.title(option.title));

        const meter = document.createElement('meter');
        meter.setAttribute('value', String(option.share));
        meter.setAttribute('min', '0');
        meter.setAttribute('max', '100');
        result.appendChild(meter);

        const share = document.createElement('span');
        share.className = 'PollOptionShare';
        share.textContent = option.share + '%';

        const votes = document.createElement('span');
        votes.className = 'PollOptionVotes';
        votes.textContent = option.voteCount === 1 ? '1 vote' : option.voteCount + ' votes';
        share.appendChild(votes);

        result.appendChild(share);
        wrapper.appendChild(result);

        return wrapper;
    }

    static title(text) {
        const title = document.createElement('span');
        title.className = 'PollOptionTitle';
        title.textContent = text;

        return title;
    }

    tally() {
        const footer = document.createElement('footer');
        footer.className = 'PollTally';
        footer.textContent = this.voterCount === 1 ? '1 person voted' : this.voterCount + ' people voted';

        const deadline = document.createElement('time');
        deadline.className = 'PollDeadline';
        deadline.dateTime = this.endsAt;
        deadline.textContent = this.closed ? 'Final result' : 'Closes ' + Poll.remaining(this.endsAt);

        footer.appendChild(deadline);

        return footer;
    }

    /** Mirrors PollTally::remaining() - the largest unit that still says something useful. */
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
