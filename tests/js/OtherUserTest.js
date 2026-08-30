import { TestCase, write_client_config } from './TestCase.js';

write_client_config({ currentUserId: 1, siteURL: 'https://example.test' });

const { OtherUser } = await import('../../scripts/HTMLObjects.js');

/**
 * A Fediverse account cannot hold up its end of a friendship - there is nobody
 * on this side of it - so its card offers following instead. The server has
 * always known that; the client rebuilt every account as a local one, so a
 * remote account found through search offered to send a friend request that no
 * server would ever answer.
 */
export default {
    suite: 'OtherUser',
    tests: {
        'a Fediverse account is offered following rather than friendship'() {
            const card = OtherUser.fromData({
                userId: 11,
                slug: 'andrew@ottawa.place',
                title: 'Andrew Dunham',
                remote: true,
                following: false,
            }).toElement();

            TestCase.assertNotNull(card.querySelector('.UserFollowButton'));
            TestCase.assertNull(card.querySelector('.FriendRequestButton'), 'friendship is not on offer with a remote account');
        },

        'an account already followed is offered the way back out'() {
            const card = OtherUser.fromData({
                userId: 11,
                slug: 'andrew@ottawa.place',
                remote: true,
                following: true,
            }).toElement();

            const button = card.querySelector('.UserFollowButton');

            TestCase.assertEquals('Unfollow', button.textContent);
            TestCase.assertEquals('1', button.dataset.following);
        },

        /** The whole point of holding a shadow account for them. */
        'a Fediverse account can still be messaged'() {
            const card = OtherUser.fromData({
                userId: 11,
                slug: 'andrew@ottawa.place',
                remote: true,
            }).toElement();

            const message = Array.from(card.querySelectorAll('a')).find((link) => link.textContent === 'Message');

            TestCase.assertNotNull(message, 'a remote account needs the way in to a thread');
            TestCase.assertEquals('https://example.test/messages/andrew@ottawa.place', message.getAttribute('href'));
        },

        'a local account is still offered friendship'() {
            // Deliberately not userId 1 or 2: ClientConfig caches the first
            // config read in the process, and another suite signs in as one of
            // those - a card for the signed-in account renders no actions at
            // all, which would fail here for the wrong reason.
            const card = OtherUser.fromData({
                userId: 7,
                slug: 'ralf',
                remote: false,
                friendshipStatus: null,
            }).toElement();

            TestCase.assertNotNull(card.querySelector('.FriendRequestButton'));
            TestCase.assertNull(card.querySelector('.UserFollowButton'));
        },
    }
};
