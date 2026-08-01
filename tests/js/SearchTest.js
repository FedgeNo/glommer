import { TestCase } from './TestCase.js';
import { Search } from '../../scripts/Search.js';

// The search page as Search.init() expects to find it, plus a stand-in for the
// network so a test can see what was actually searched for.
let realFetch = null;

function setUpSearchPage(query) {
    const searched = [];

    const box = document.createElement('div');
    box.className = 'SearchBox PostSearch';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'SearchInput PostSearchInput';
    box.appendChild(input);

    const section = document.createElement('section');
    section.className = 'SearchFeedSection';

    const list = document.createElement('ul');
    list.className = 'SearchFeedList';
    section.appendChild(list);

    document.body.append(box, section);

    if (query === null) {
        window.history.replaceState({}, '', '/search');
    } else {
        window.history.replaceState({}, '', '/search?q=' + encodeURIComponent(query));
    }

    realFetch = globalThis.fetch;
    globalThis.fetch = async (url, options) => {
        searched.push(JSON.parse(options.body).q);

        return new Response(JSON.stringify({ response: { posts: [], hasMore: false } }), { status: 200 });
    };

    return { input, box, list, searched };
}

function tearDownSearchPage() {
    globalThis.fetch = realFetch;
    document.body.replaceChildren();
    window.history.replaceState({}, '', '/');
}

export default {
    suite: 'Search',
    tests: {
        'constructor creates an input listener'() {
            const input = document.createElement('input');
            input.type = 'text';
            document.body.appendChild(input);
            const results = document.createElement('div');
            const search = new Search(input, {
                endpoint: '/api/search-test',
                buildRequest: () => ({ q: '' }),
                resultsContainer: results,
                renderItem: (data) => document.createElement('div'),
            });
            TestCase.assertNotNull(search);
            TestCase.assertTrue(search instanceof Search);
            search.destroy();
            document.body.removeChild(input);
        },
        // Arriving with ?q= has to actually search. It used to fill the box and
        // dispatch an input event before the listener existed, so a linked
        // search landed on an empty page with the term sitting in the input.
        'a search page arrived at with a query runs that search'() {
            const { input, box, searched } = setUpSearchPage('kittens');

            Search.init();

            TestCase.assertEquals('kittens', input.value);
            TestCase.assertTrue(searched.length > 0, 'the query should have been searched for');
            TestCase.assertEquals('kittens', searched[0]);
            TestCase.assertTrue(box.classList.contains('HasQuery'), 'the clear button has to be reachable');

            tearDownSearchPage();
        },

        'an empty query searches for nothing at all'() {
            // Otherwise the default feed is hidden behind an empty result list.
            const { searched, box } = setUpSearchPage('   ');

            Search.init();

            TestCase.assertEquals(0, searched.length);
            TestCase.assertFalse(box.classList.contains('HasQuery'));

            tearDownSearchPage();
        },

        'no query at all leaves the page alone'() {
            const { searched } = setUpSearchPage(null);

            Search.init();

            TestCase.assertEquals(0, searched.length);

            tearDownSearchPage();
        },

        'constructor enables infinite scroll when requested'() {
            const input = document.createElement('input');
            input.type = 'text';
            document.body.appendChild(input);
            const results = document.createElement('div');
            const search = new Search(input, {
                endpoint: '/api/search-test',
                buildRequest: () => ({ q: '' }),
                resultsContainer: results,
                renderItem: (data) => document.createElement('div'),
                enableInfiniteScroll: true,
                countOffset: () => 0,
            });
            TestCase.assertNotNull(search.scroller);
            search.destroy();
            document.body.removeChild(input);
        },
    }
};
