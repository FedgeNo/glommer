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
        // A feed selected by more than its type has to send that selector with
        // every page or the endpoint rejects the request - so the whole scroll
        // config travels, not just the keys the scroller itself consumes.
        async 'the next-page request carries every field the list is configured with'() {
            const list = document.createElement('ul');
            list.dataset.infiniteScroll = JSON.stringify({
                endpoint: '/api/feed',
                itemType: 'Post',
                feedType: 'user',
                userId: 7,
            });
            list.appendWithSpace(post_element());
            list.appendWithSpace(post_element());
            document.body.appendWithSpace(list);

            const scroller = new InfiniteScroller(list);
            const requests = capture_requests();

            window.dispatchEvent(new window.Event('scroll'));
            await new Promise((resolve) => setTimeout(resolve, 0));

            requests.restore();
            scroller.destroy();
            list.remove();

            const sent = requests.forFeedType('user');
            TestCase.assertNotNull(sent, 'the profile feed never sent a request');
            TestCase.assertEquals(7, Number(sent.userId), 'the profile being paged was left out of the request');
            TestCase.assertEquals(2, sent.offset, 'the offset should be the number of posts already shown');
        },
    }
};

function post_element() {
    const post = document.createElement('div');
    post.className = 'Post';

    return post;
}

/**
 * Swaps in a fetch that records each request body instead of reaching the
 * network. Collects every call rather than just the last, so a scroller left
 * listening by another test can't be mistaken for the one under test.
 */
function capture_requests() {
    const original_fetch = globalThis.fetch;
    const bodies = [];

    globalThis.fetch = async (url, options) => {
        bodies.push(JSON.parse(options.body));

        return new Response(JSON.stringify({ response: { posts: [], hasMore: false } }), { status: 200 });
    };

    return {
        restore: () => { globalThis.fetch = original_fetch; },
        forFeedType: (feed_type) => bodies.find((body) => body.feedType === feed_type) ?? null,
    };
}
