/**
 * The client render of Help results - the twin of HelpCategory and
 * HelpArticleSummary, rebuilding the same cards from /api/help-search's JSON.
 * The searching itself is driven by Search.js, the same machinery as every
 * other search box on the site; this module only knows how the results look.
 */
export class HelpSearch {
    /** The browse view: every article grouped under its category heading. */
    static renderBrowse(results, articles) {
        let current_category = null;
        let current_list = null;

        articles.forEach((article) => {
            if (article.category !== current_category) {
                current_category = article.category;
                const section = HelpSearch.categoryElement(current_category);
                current_list = section.querySelector('.HelpArticleList');
                results.appendWithSpace(section);
            }

            current_list.appendWithSpace(HelpSearch.articleSummaryElement(article));
        });
    }

    /** A flat ranked list, for a typed query. */
    static renderResults(results, articles) {
        const list = document.createElement('div');
        list.className = 'HelpArticleList';
        articles.forEach((article) => list.appendWithSpace(HelpSearch.articleSummaryElement(article)));
        results.appendWithSpace(list);
    }

    // Mirror of HelpArticleSummary::toDOM() - the whole card is a link.
    static articleSummaryElement(article) {
        const card = document.createElement('a');
        card.className = 'HelpArticleSummary';
        card.href = article.url;

        const title = document.createElement('h3');
        title.textContent = article.title;
        card.appendWithSpace(title);

        const summary = document.createElement('p');
        summary.className = 'HelpArticleSummaryText';
        summary.textContent = article.summary;
        card.appendWithSpace(summary);

        return card;
    }

    // Mirror of HelpCategory::toDOM().
    static categoryElement(name) {
        const section = document.createElement('section');
        section.className = 'HelpCategory';

        const heading = document.createElement('h2');
        heading.textContent = name;
        section.appendWithSpace(heading);

        const list = document.createElement('div');
        list.className = 'HelpArticleList';
        section.appendWithSpace(list);

        return section;
    }
}
