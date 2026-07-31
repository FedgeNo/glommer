import { TestCase } from './TestCase.js';
import { Post } from '../../scripts/Post.js';

/**
 * A post the client builds has to come out as the same DOM the server renders
 * for the same row - same tags, same class names, same image URLs. Anything
 * that only holds on one of the two paths shows up as a feed where the posts
 * above the scroll point behave differently from the ones below it.
 */
function post_element(overrides = {}) {
    const post = Post.fromData({
        postId: 12,
        userId: 3,
        parentId: null,
        title: 'Hello',
        description: null,
        descriptionDelta: null,
        descriptionTruncated: false,
        seeMoreURL: null,
        linkURL: null,
        createdAt: '2026-07-30 15:04:00',
        editedAt: null,
        latitude: null,
        longitude: null,
        items: [{
            itemType: 'ImageItem',
            src: 'https://example.com/uploads/2a/42.jpg',
            image: 'https://example.com/uploads/2a/42-thumb.jpg',
        }],
        imageAltText: 'a photo',
        replyCount: 0,
        likeCount: 0,
        liked: false,
        bookmarked: false,
        author: { userId: 3, slug: 'ann', title: 'Ann', image: null },
        ...overrides,
    });

    return post.toElement();
}

export default {
    suite: 'Post',
    tests: {
        // Post extends Article, PostByline is a Header, FeedItem a Figure and
        // PostActionBar a Footer - see the matching PHP classes.
        'it builds the same elements the server renders'() {
            const element = post_element();

            TestCase.assertEquals('ARTICLE', element.tagName);
            TestCase.assertEquals('HEADER', element.querySelector('.PostByline').tagName);
            TestCase.assertEquals('FIGURE', element.querySelector('.FeedItem').tagName);
            TestCase.assertEquals('FOOTER', element.querySelector('.PostActionBar').tagName);
        },
        'a media post shows the thumbnail and carries the full image for fullscreen'() {
            const image = post_element().querySelector('.FeedItem img');

            TestCase.assertEquals('https://example.com/uploads/2a/42-thumb.jpg', image.getAttribute('src'));
            TestCase.assertEquals('https://example.com/uploads/2a/42.jpg', image.dataset.fullSrc);
        },
        'the icon buttons carry the shared Button look'() {
            const fullscreen = post_element().querySelector('.MediaFullscreenButton');

            TestCase.assertTrue(fullscreen.classList.contains('Button'), 'the fullscreen toggle should look like a button');
        },
        'a carousel\'s controls carry the shared Button look'() {
            const element = post_element({
                items: [
                    { itemType: 'ImageItem', src: 'https://example.com/a.jpg', image: 'https://example.com/a-thumb.jpg' },
                    { itemType: 'ImageItem', src: 'https://example.com/b.jpg', image: 'https://example.com/b-thumb.jpg' },
                ],
            });

            for (const selector of ['.CarouselPrevButton', '.CarouselNextButton', '.CarouselAutoplayButton']) {
                TestCase.assertTrue(element.querySelector(selector).classList.contains('Button'), selector + ' should look like a button');
            }
        },
        // Everyone gets a Share button, signed in or not, same as PostActionBar.php.
        'a signed-out reader still gets the share button'() {
            const share = post_element().querySelector('.PostShareButton');

            TestCase.assertNotNull(share, 'the share button should be on every post');
            TestCase.assertTrue(share.dataset.shareUrl.endsWith('/users/ann/12'), 'the share button should carry the post\'s permalink');
        },
    }
};
