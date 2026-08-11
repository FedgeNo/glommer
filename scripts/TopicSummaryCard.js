import { Api } from '/scripts/Api.js';
import { Strings } from '/scripts/Strings.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Client twin of TopicSummaryCard.php: asks for a topic's paragraph when the
 * page arrived without one, and puts it in.
 *
 * The timer works down the topics by how much they are being talked about, so
 * the busy ones are written before anybody opens them. Somewhere down that
 * list is a topic nobody has asked about yet, and the person opening its page
 * is the first to want one - so it is written for them while they read, rather
 * than the page saying nothing and staying that way.
 *
 * Quiet on failure. Nobody asked for this paragraph, and a page that simply
 * has no summary is the ordinary state of one - a toast would report a fault
 * to somebody who was not waiting on anything.
 */
export class TopicSummaryCard {
    static async init() {
        const card = document.querySelector('.TopicSummaryCard.Awaited');

        if (!card) return;

        const summary = await Api.post('/api/topic-summary', {
            type: card.dataset.topicType,
            slug: card.dataset.topicSlug,
        }, { quiet: true });

        if (!summary?.summary) return;

        TopicSummaryCard.fill(card, summary.summary);
    }

    /** The same two elements TopicSummaryCard.php builds, in the same order. */
    static fill(card, text) {
        const paragraph = document.createElement('p');
        paragraph.textContent = text;

        const label = document.createElement('p');
        label.className = 'TopicSummaryLabel muted text-sm';
        label.textContent = Strings.for('TopicSummaryCard', { label: 'AI-generated summary' }).label;

        card.appendWithSpace(paragraph);
        card.appendWithSpace(label);
        card.classList.remove('Awaited');
    }
}

ReadyHandler.add(TopicSummaryCard.init);
