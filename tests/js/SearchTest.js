import { TestCase } from './TestCase.js';
import { Search } from '../../scripts/Search.js';

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
