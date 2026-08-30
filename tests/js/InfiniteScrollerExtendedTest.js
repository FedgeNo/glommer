import { TestCase } from './TestCase.js';
import { InfiniteScroller } from '../../scripts/Controllers.js';

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

            const requests = capture_requests();
            const scroller = new InfiniteScroller(list);

            window.dispatchEvent(new window.Event('scroll'));

            // Past the settle the scroller waits out before it acts on where
            // the view has come to rest - a smooth scroll is a stream of these
            // events and only the last position is worth a request.
            await new Promise((resolve) => setTimeout(resolve, 200));

            requests.restore();
            scroller.destroy();
            list.remove();

            const sent = requests.forFeedType('user');
            TestCase.assertNotNull(sent, 'the profile feed never sent a request');
            TestCase.assertEquals(7, Number(sent.userId), 'the profile being paged was left out of the request');
            TestCase.assertEquals(2, sent.offset, 'the offset should be the number of posts already shown');
        },
        async 'a keyset feed sends the last row cursor'() {
            const list = document.createElement('ul');
            list.dataset.infiniteScroll = JSON.stringify({
                endpoint: '/api/feed',
                itemType: 'Post',
                feedType: 'global',
                cursor: { postId: 42 },
            });
            list.appendWithSpace(post_element());
            document.body.appendWithSpace(list);

            const requests = capture_requests();
            const scroller = new InfiniteScroller(list);

            window.dispatchEvent(new window.Event('scroll'));
            await new Promise((resolve) => setTimeout(resolve, 200));

            requests.restore();
            scroller.destroy();
            list.remove();

            const sent = requests.forFeedType('global');
            TestCase.assertNotNull(sent, 'the global feed never sent a request');
            TestCase.assertEquals(42, Number(sent.cursor?.postId), 'the keyset cursor was left out');
        },
        // A list that does not fill the window never gets a scroll event, so
        // without this it would sit at one page however much more there is.
        async 'a list too short to scroll asks for the next page unprompted'() {
            const list = document.createElement('ul');
            list.dataset.infiniteScroll = JSON.stringify({
                endpoint: '/api/feed',
                itemType: 'Post',
                feedType: 'short',
            });
            document.body.appendWithSpace(list);

            const requests = capture_requests();
            const scroller = new InfiniteScroller(list);

            await new Promise((resolve) => setTimeout(resolve, 50));

            requests.restore();
            scroller.destroy();
            list.remove();

            TestCase.assertNotNull(requests.forFeedType('short'), 'the short list never asked for more');
        },
        // A window that grows can leave a feed no longer reaching the bottom
        // of it - a phone turned on its side - and from then on no scroll
        // event fires and nothing more ever loads.
        async 'a window that grows past the feed starts it loading again'() {
            InfiniteScroller.register('Grown',
                () => document.createElement('div'),
                (list) => list.querySelectorAll('.Grown').length
            );

            const list = document.createElement('ul');
            list.dataset.infiniteScroll = JSON.stringify({
                endpoint: '/api/grown',
                itemType: 'Grown',
                feedType: 'grown',
            });
            document.body.appendWithSpace(list);

            // A feed with more to give, so the scroller is still willing when
            // the window changes - one that has ended has nothing to load and
            // is right not to.
            const original_fetch = globalThis.fetch;
            let asked = 0;

            globalThis.fetch = async () => {
                asked++;

                return new Response(
                    JSON.stringify({ response: { items: [], hasMore: true } }),
                    { status: 200 }
                );
            };

            const scroller = new InfiniteScroller(list);
            await new Promise((resolve) => setTimeout(resolve, 50));

            const before = asked;
            window.dispatchEvent(new window.Event('resize'));
            await new Promise((resolve) => setTimeout(resolve, 50));

            const after = asked;

            globalThis.fetch = original_fetch;
            scroller.destroy();
            list.remove();

            TestCase.assertTrue(after > before, 'the feed stayed stranded after the window changed size');
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
