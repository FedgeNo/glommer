import { ClientConfig } from '/scripts/ClientConfig.js';
import { csrf_headers } from '/scripts/utils.js';

/**
 * Help search - mirrors the user search in main.js, but scoped to the Help
 * pages (loaded only when Page::create is told needsHelp). Typing in the box
 * fetches ranked matches from /api/help-search and swaps them into the results
 * area; clearing the box restores the browse view (every article grouped under
 * its category), so the client-side render matches the server-rendered one in
 * HelpSearch/HelpCategory/HelpArticleSummary.
 */

// Mirror of HelpArticleSummary::toDOM() - the whole card is a link.
export function help_article_summary_element(article) {
    const card = document.createElement('a');
    card.className = 'HelpArticleSummary Card';
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
export function help_category_element(name, articles) {
    const section = document.createElement('section');
    section.className = 'HelpCategory';

    const heading = document.createElement('h2');
    heading.textContent = name;
    section.appendWithSpace(heading);

    const list = document.createElement('div');
    list.className = 'HelpArticleList';
    articles.forEach((article) => list.appendWithSpace(help_article_summary_element(article)));
    section.appendWithSpace(list);

    return section;
}

function render_help_browse(results, articles) {
    let current_category = null;
    let current_list = null;

    articles.forEach((article) => {
        if (article.category !== current_category) {
            current_category = article.category;
            const section = help_category_element(current_category, []);
            current_list = section.querySelector('.HelpArticleList');
            results.appendWithSpace(section);
        }

        current_list.appendWithSpace(help_article_summary_element(article));
    });
}

function render_help_results(results, articles) {
    const list = document.createElement('div');
    list.className = 'HelpArticleList';
    articles.forEach((article) => list.appendWithSpace(help_article_summary_element(article)));
    results.appendWithSpace(list);
}

document.addEventListener('input', (event) => {
    const input = event.target.closest('.HelpSearchInput');

    if (!input) {
        return;
    }

    clearTimeout(input.dataset.debounceId);

    const debounce_id = setTimeout(async () => {
        const query = input.value.trim();
        const results = input.closest('.HelpSearch').querySelector('.HelpSearchResults');

        input.searchAbortController?.abort();
        const controller = new AbortController();
        input.searchAbortController = controller;

        let data;

        try {
            const response = await fetch(ClientConfig.siteURL() + '/api/help-search', {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ q: query }),
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            return;
        }

        results.replaceChildren();

        if (data.response.articles.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = 'No help articles matched your search.';
            results.appendWithSpace(empty);
            return;
        }

        if (data.response.grouped) {
            render_help_browse(results, data.response.articles);
        } else {
            render_help_results(results, data.response.articles);
        }
    }, 300);

    input.dataset.debounceId = debounce_id;
});
