/**
 * Help search - mirrors the user search in main.js, but scoped to the Help
 * pages (loaded only when Page::create is told needsHelp). Typing in the box
 * fetches ranked matches from /api/help-search and swaps them into the results
 * area; clearing the box restores the browse view (every article grouped under
 * its category), so the client-side render matches the server-rendered one in
 * HelpSearch/HelpCategory/HelpArticleSummary.
 */

// Mirror of HelpArticleSummary::toDOM() - the whole card is a link.
function help_article_summary_element(article) {
    const card = document.createElement('a');
    card.className = 'HelpArticleSummary Card';
    card.href = article.url;

    const title = document.createElement('h3');
    title.textContent = article.title;
    card.appendChild(title);

    const summary = document.createElement('p');
    summary.className = 'Muted';
    summary.textContent = article.summary;
    card.appendChild(summary);

    return card;
}

// Mirror of HelpCategory::toDOM().
function help_category_element(name, articles) {
    const section = document.createElement('section');
    section.className = 'HelpCategory';

    const heading = document.createElement('h2');
    heading.textContent = name;
    section.appendChild(heading);

    const list = document.createElement('div');
    list.className = 'HelpArticleList';
    articles.forEach((article) => list.appendChild(help_article_summary_element(article)));
    section.appendChild(list);

    return section;
}

function render_help_browse(results, articles) {
    // Group consecutive articles by category, preserving the order the server
    // sent them (already category order).
    let current_category = null;
    let current_list = null;

    articles.forEach((article) => {
        if (article.category !== current_category) {
            current_category = article.category;
            const section = help_category_element(current_category, []);
            current_list = section.querySelector('.HelpArticleList');
            results.appendChild(section);
        }

        current_list.appendChild(help_article_summary_element(article));
    });
}

function render_help_results(results, articles) {
    const list = document.createElement('div');
    list.className = 'HelpArticleList';
    articles.forEach((article) => list.appendChild(help_article_summary_element(article)));
    results.appendChild(list);
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

        // Abort whatever this input's previous search is still waiting on -
        // without this, a slower earlier response can resolve after a faster
        // later one and overwrite fresher results with stale ones.
        input.searchAbortController?.abort();
        const controller = new AbortController();
        input.searchAbortController = controller;

        let response;

        try {
            response = await fetch(window.siteURL + '/api/help-search?q=' + encodeURIComponent(query), { signal: controller.signal });
        } catch (error) {
            return; // aborted by a newer search, or a network failure either way
        }

        const data = await response.json();

        if (!response.ok) {
            return;
        }

        results.innerHTML = '';

        if (data.response.articles.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'Muted';
            empty.textContent = 'No help articles matched your search.';
            results.appendChild(empty);
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
