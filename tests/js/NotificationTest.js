import { TestCase } from './TestCase.js';
import { Notification } from '../../scripts/Notification.js';

/**
 * The client twin of Notification.php, which builds the same line from the
 * endpoint's JSON. The two drifting is invisible: a notification that arrives
 * live over the socket would name people differently from the identical one
 * already on the page, and only somebody watching both at once would notice.
 *
 * See tests/NotificationNameTest.php for the server's half.
 */
function notification(actor, type = 'like') {
    return Notification.fromData({
        notificationId: 1,
        userId: 2,
        type,
        postId: 7,
        createdAt: '2026-08-09 12:00:00',
        actor,
    });
}

export default {
    suite: 'Notification',
    tests: {
        'an account from another server is named by its handle'() {
            const line = notification({ userId: 3, slug: 'fedgeno@mastodon.social', title: 'fedgeno', image: null });

            TestCase.assertEquals('@fedgeno@mastodon.social liked your post', line.text());
        },
        'a remote account with no display name still gets its handle'() {
            const line = notification({ userId: 3, slug: 'someone@example.social', title: null, image: null });

            TestCase.assertEquals('@someone@example.social liked your post', line.text());
        },
        'a member here keeps their display name'() {
            const line = notification({ userId: 3, slug: 'fedge', title: 'Fedge', image: null });

            TestCase.assertEquals('Fedge liked your post', line.text());
        },
        'a member here with no display name is named by their username'() {
            const line = notification({ userId: 3, slug: 'fedge', title: null, image: null });

            TestCase.assertEquals('fedge liked your post', line.text());
        },
        'the handle carries into every wording, not just likes'() {
            const actor = { userId: 3, slug: 'fedgeno@mastodon.social', title: 'fedgeno', image: null };

            TestCase.assertEquals(
                '@fedgeno@mastodon.social reposted your post',
                notification(actor, 'repost').text()
            );
            TestCase.assertEquals(
                '@fedgeno@mastodon.social followed you from another server',
                notification(actor, 'follow').text()
            );
        },
    }
};
