import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Help search - the client twin of HelpSearch.php, scoped to the Help pages
 * (loaded only when Page::create is told needsHelp). Typing in the box fetches
 * ranked matches from /api/help-search and swaps them into the results area;
 * clearing the box restores the browse view (every article grouped under its
 * category), so the client-side render matches the server-rendered one in
 * HelpSearch/HelpCategory/HelpArticleSummary.
 */
export class HelpSearch {
    // The pending debounce timer per search box. Keyed on the input rather than
    // stored on it: the handler is delegated on document, so it needs somewhere
    // to keep the timer, and a timer id is this module's own bookkeeping.
    static #debounceIds = new WeakMap();

    static init() {
        document.addEventListener('input', (event) => {
            const input = event.target.closest('.HelpSearchInput');

            if (!input) {
                return;
            }

            clearTimeout(HelpSearch.#debounceIds.get(input));
            HelpSearch.#debounceIds.set(input, setTimeout(() => HelpSearch.#search(input), 300));
        });
    }

    static async #search(input) {
        const query = input.value.trim();
        const results = input.closest('.HelpSearch').querySelector('.HelpSearchResults');

        input.searchAbortController?.abort();
        const controller = new AbortController();
        input.searchAbortController = controller;

        // Quiet: this runs on every keystroke and cancels the one before it,
        // so a failure here is nothing to interrupt somebody's typing over.
        const data = await Api.post('/api/help-search', { q: query }, {
            signal: controller.signal,
            quiet: true,
        });

        if (!data) return;

        results.replaceChildren();

        if (data.articles.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = 'No help articles matched your search.';
            results.appendWithSpace(empty);
            return;
        }

        if (data.grouped) {
            HelpSearch.#renderBrowse(results, data.articles);
        } else {
            HelpSearch.#renderResults(results, data.articles);
        }
    }

    static #renderBrowse(results, articles) {
        let current_category = null;
        let current_list = null;

        articles.forEach((article) => {
            if (article.category !== current_category) {
                current_category = article.category;
                const section = HelpSearch.#categoryElement(current_category, []);
                current_list = section.querySelector('.HelpArticleList');
                results.appendWithSpace(section);
            }

            current_list.appendWithSpace(HelpSearch.#articleSummaryElement(article));
        });
    }

    static #renderResults(results, articles) {
        const list = document.createElement('div');
        list.className = 'HelpArticleList';
        articles.forEach((article) => list.appendWithSpace(HelpSearch.#articleSummaryElement(article)));
        results.appendWithSpace(list);
    }

    // Mirror of HelpArticleSummary::toDOM() - the whole card is a link.
    static #articleSummaryElement(article) {
        const card = document.createElement('a');
        card.className = 'HelpArticleSummary';
        card.href = article.url;

        const title = document.createElement('h3');
        title.textContent = article.title;
        card.appendWithSpace(title);

        const summary = document.createElement('p');
        summary.className = 'muted';
        summary.textContent = article.summary;
        card.appendWithSpace(summary);

        return card;
    }

    // Mirror of HelpCategory::toDOM().
    static #categoryElement(name, articles) {
        const section = document.createElement('section');
        section.className = 'HelpCategory';

        const heading = document.createElement('h2');
        heading.textContent = name;
        section.appendWithSpace(heading);

        const list = document.createElement('div');
        list.className = 'HelpArticleList';
        articles.forEach((article) => list.appendWithSpace(HelpSearch.#articleSummaryElement(article)));
        section.appendWithSpace(list);

        return section;
    }
}

ReadyHandler.add(HelpSearch.init);
