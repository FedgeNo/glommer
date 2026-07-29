import { TestCase } from './TestCase.js';
import { InfiniteScroller } from '../../scripts/InfiniteScroller.js';

export default {
    suite: 'InfiniteScroller',
    tests: {
        'create() with overrides returns an instance'() {
            const ul = document.createElement('ul');
            const scroller = InfiniteScroller.create(ul, {
                endpoint: '/api/test',
                renderItem: (data) => document.createElement('li'),
                countOffset: () => 0,
            });
            TestCase.assertNotNull(scroller);
            TestCase.assertTrue(scroller instanceof InfiniteScroller);
            scroller.destroy();
        },
        // Smoke tests: these methods can't be deeply tested without a live scrollport,
        // but at least verify they don't throw.
        'setActive() does not throw'() {
            const ul = document.createElement('ul');
            const scroller = InfiniteScroller.create(ul, {
                endpoint: '/api/test',
                renderItem: (data) => document.createElement('li'),
                countOffset: () => 0,
                active: false,
            });
            scroller.setActive(true);
            TestCase.assertTrue(true);
            scroller.destroy();
        },
        'register() stores a type without error'() {
            InfiniteScroller.register('Test', () => {}, () => 0);
            TestCase.assertTrue(true);
        },
    }
};
