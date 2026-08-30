import { TestCase } from './TestCase.js';
import { Post } from '../../scripts/HTMLObjects.js';

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
        'a local post always offers Share'() {
            TestCase.assertNotNull(post_element().querySelector('.PostShareButton'));
        },
        'a post from another server offers no Share'() {
            // Sharing is handing someone the permalink, and for one of these
            // the address worth passing on is the original rather than this
            // server's copy. Mirrors PostActionBar.php - see
            // tests/PostShareButtonTest.php for the other half.
            const element = post_element({ remote: true });

            TestCase.assertNull(element.querySelector('.PostShareButton'));
            TestCase.assertNotNull(element.querySelector('.PostActionBar'), 'the bar itself still renders');
        },
        'a media post shows the thumbnail and carries the full image for fullscreen'() {
            const image = post_element().querySelector('.FeedItem img');

            TestCase.assertEquals('https://example.com/uploads/2a/42-thumb.jpg', image.getAttribute('src'));
            TestCase.assertEquals('https://example.com/uploads/2a/42.jpg', image.dataset.fullSrc);
        },
        'classified media sits behind the same cover the server renders'() {
            const element = post_element({ sensitive: true });
            const cover = element.querySelector('details.SensitiveMedia');

            TestCase.assertNotNull(cover);
            TestCase.assertEquals('SUMMARY', cover.firstElementChild.tagName);
            TestCase.assertNotNull(cover.querySelector('.FeedItem'), 'the media belongs inside the cover');
        },
        'unclassified media is just shown'() {
            const element = post_element();

            TestCase.assertNull(element.querySelector('details.SensitiveMedia'));
            TestCase.assertNotNull(element.querySelector('.FeedItem'));
        },
        // Mirrors ContentWarning.php: the warning covers the whole body, not
        // just the pictures, because what it is usually warning about is the
        // words. See tests/ContentWarningTest.php for the server's half.
        'a warning covers the whole body, title and media alike'() {
            const element = post_element({ contentWarning: 'Spoilers for the finale' });
            const gate = element.querySelector('details.ContentWarning');

            TestCase.assertNotNull(gate);
            TestCase.assertEquals('SUMMARY', gate.firstElementChild.tagName);
            TestCase.assertEquals('Spoilers for the finale', gate.firstElementChild.textContent);
            TestCase.assertNotNull(gate.querySelector('.FeedItem'), 'the media belongs inside the gate');
            TestCase.assertNotNull(gate.querySelector('h3'), 'so does the title');
        },
        // Mirrors Post.php: a sender that writes a warning also sets sensitive,
        // so without this the media sits under a cover inside the warning and
        // the reader has to ask for it twice.
        'a warned post does not cover its media a second time'() {
            const element = post_element({ contentWarning: 'Spoilers', sensitive: true });

            TestCase.assertNotNull(element.querySelector('details.ContentWarning'));
            TestCase.assertNull(element.querySelector('details.SensitiveMedia'));
            TestCase.assertNotNull(element.querySelector('.ContentWarning .FeedItem'));
        },
        'the byline stays outside the warning'() {
            // A gate with nothing identifying it is a gate nobody can judge.
            const element = post_element({ contentWarning: 'Spoilers' });

            TestCase.assertNotNull(element.querySelector('.PostByline'));
            TestCase.assertNull(element.querySelector('.ContentWarning .PostByline'));
        },
        'a post nobody warned about is not put behind an empty gate'() {
            TestCase.assertNull(post_element().querySelector('details.ContentWarning'));
            TestCase.assertNull(post_element({ contentWarning: '   ' }).querySelector('details.ContentWarning'));
        },
        'a remote attachment describes itself'() {
            // The server prefers the sender's per-attachment alt text over the
            // post-level fallback; FeedItem.php does the same.
            const element = post_element({
                items: [{ itemType: 'ImageItem', src: '/media-9', image: null, altText: 'a cat asleep on a keyboard' }],
            });

            TestCase.assertEquals('a cat asleep on a keyboard', element.querySelector('.FeedItem img').alt);
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
