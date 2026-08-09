import { ClientConfig } from '/scripts/ClientConfig.js';
import { User } from '/scripts/User.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { list_item } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class OtherUser extends User {
    userId = null;
    slug = null;
    title = null;
    description = null;
    image = null;
    createdAt = null;
    blockedByViewer = false;
    blockedByOther = false;
    friendshipStatus = null;
    friendshipSentByViewer = null;
    isMod = false;
    // A Fediverse account, and whether the viewer follows it - what the card
    // offers instead of friendship. See toElement().
    remote = false;
    following = false;
    friendshipId = null;
    element = null;

    beforeActions() {
        return [];
    }

    toElement() {
        const div = super.toElement();
        div.classList.add('OtherUser', 'MountIn');

        if (this.friendshipId) {
            div.dataset.friendshipId = this.friendshipId;
        }

        const is_self = ClientConfig.get('currentUserId') !== null && Number(ClientConfig.get('currentUserId')) === Number(this.userId);

        if (ClientConfig.get('currentUserId') === null || is_self) {
            this.element = div;
            return div;
        }

        if (this.blockedByViewer) {
            const unblock_button = document.createElement('button');
            unblock_button.type = 'button';
            unblock_button.className = 'Button UserUnblockButton ms-auto';
            unblock_button.dataset.userId = this.userId;
            unblock_button.textContent = 'Unblock';
            div.appendWithSpace(unblock_button);
        } else if (!this.blockedByOther) {
            const sent_by_viewer = this.friendshipStatus === 'pending' && this.friendshipSentByViewer;

            const actions = document.createElement('div');
            actions.className = 'd-flex flex-column gap-2 ms-auto';

            this.beforeActions().forEach((button) => actions.appendWithSpace(button));

            // Mirrors OtherUser.php: a Fediverse account can't hold up its end
            // of a friendship - there is no person on this side of it - so the
            // mutual action is replaced by the one-way one that does mean
            // something. Messaging stays, which is the whole point of holding
            // a shadow account for them.
            if (this.remote) {
                const follow_button = document.createElement('button');
                follow_button.type = 'button';
                follow_button.className = this.following
                    ? 'Button UserFollowButton Removing'
                    : 'Button UserFollowButton';
                follow_button.dataset.userId = this.userId;
                follow_button.dataset.following = this.following ? '1' : '0';
                follow_button.textContent = this.following ? 'Unfollow' : 'Follow';
                actions.appendWithSpace(follow_button);
            } else if (this.friendshipStatus === null || sent_by_viewer) {
                const friend_button = document.createElement('button');
                friend_button.type = 'button';
                friend_button.className = sent_by_viewer
                    ? 'Button FriendRequestButton Removing'
                    : 'Button FriendRequestButton';
                friend_button.dataset.userId = this.userId;
                friend_button.dataset.sent = sent_by_viewer ? '1' : '0';
                friend_button.textContent = sent_by_viewer ? 'Cancel Request' : 'Add Friend';
                actions.appendWithSpace(friend_button);
            }

            const message_link = document.createElement('a');
            message_link.className = 'Button';
            message_link.href = ClientConfig.siteURL() + '/messages/' + this.slug;
            message_link.textContent = 'Message';

            const block_button = document.createElement('button');
            block_button.type = 'button';
            block_button.className = 'Button UserBlockButton';
            block_button.dataset.userId = this.userId;
            block_button.textContent = 'Block';

            let report_or_ban_button = null;

            if (Number(this.userId) !== 1) {
                if (ClientConfig.get('currentUserCanModerate')) {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Button UserBanButton';
                    report_or_ban_button.dataset.userId = this.userId;
                    report_or_ban_button.textContent = 'Ban';
                } else {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Button ReportButton';
                    report_or_ban_button.dataset.targetType = 'user';
                    report_or_ban_button.dataset.targetId = this.userId;
                    report_or_ban_button.textContent = 'Report';
                }
            }

            actions.appendWithSpace(message_link);

            // Mirrors OtherUser.php: friendship happens here, between two
            // people who both signed up, so a Fediverse account has none on
            // this site to look at.
            if (!this.remote) {
                const friends_link = document.createElement('a');
                friends_link.className = 'Button';
                friends_link.href = ClientConfig.siteURL() + '/users/' + this.slug + '/friends';
                friends_link.textContent = 'View Friends';
                actions.appendWithSpace(friends_link);
            }

            if (this.friendshipStatus === 'accepted') {
                const remove_friend_button = document.createElement('button');
                remove_friend_button.type = 'button';
                remove_friend_button.className = 'Button FriendRemoveButton';
                remove_friend_button.dataset.userId = this.userId;
                remove_friend_button.textContent = 'Remove Friend';
                actions.appendWithSpace(remove_friend_button);
            }

            // Members only: moderating happens by signing in here, which
            // nobody on another server can do.
            if (Number(ClientConfig.get('currentUserId')) === 1 && !this.remote) {
                const mod_button = document.createElement('button');
                mod_button.type = 'button';
                mod_button.className = 'Button UserModButton';
                mod_button.dataset.userId = this.userId;
                mod_button.dataset.isMod = this.isMod ? '1' : '0';
                mod_button.textContent = this.isMod ? 'Remove Mod' : 'Make Mod';
                actions.appendWithSpace(mod_button);
            }

            actions.appendWithSpace(block_button);

            if (report_or_ban_button !== null) {
                actions.appendWithSpace(report_or_ban_button);
            }

            div.appendWithSpace(actions);
        }

        this.element = div;
        return div;
    }

    // ----------------------------------------------------------------
    // Static action handlers
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', async (event) => {
            const friendBtn = event.target.closest('.FriendRequestButton');
            if (friendBtn) {
                OtherUser.#sendFriendRequest(friendBtn);
                return;
            }

            const followBtn = event.target.closest('.UserFollowButton');
            if (followBtn) {
                OtherUser.#toggleFollow(followBtn);
                return;
            }

            const blockBtn = event.target.closest('.UserBlockButton');
            if (blockBtn) {
                OtherUser.#block(blockBtn);
                return;
            }

            const removeFriendBtn = event.target.closest('.FriendRemoveButton');
            if (removeFriendBtn) {
                OtherUser.#removeFriend(removeFriendBtn);
                return;
            }

            const modBtn = event.target.closest('.UserModButton');
            if (modBtn) {
                OtherUser.#toggleMod(modBtn);
                return;
            }

            const unblockBtn = event.target.closest('.UserUnblockButton');
            if (unblockBtn) {
                OtherUser.#unblock(unblockBtn);
                return;
            }

            const acceptBtn = event.target.closest('.FriendRequestAcceptButton');
            if (acceptBtn) {
                OtherUser.#acceptFriendRequest(acceptBtn);
                return;
            }

            const denyBtn = event.target.closest('.FriendRequestDenyButton');
            if (denyBtn) {
                OtherUser.#denyFriendRequest(denyBtn);
                return;
            }

            const banBtn = event.target.closest('.UserBanButton');
            if (banBtn) {
                OtherUser.#ban(banBtn);
            }
        });
    }

    static async #sendFriendRequest(button) {
        Working.start(button);
        try {
            const result = await Api.post('/api/friend-request', { userId: button.dataset.userId });
            if (!result) return;
            button.dataset.sent = result.sent ? '1' : '0';
            button.textContent = result.sent ? 'Cancel Request' : 'Add Friend';
            button.classList.toggle('Removing', result.sent);
        } finally {
            Working.stop(button);
        }
    }

    static async #toggleFollow(button) {
        const id = button.dataset.userId;
        const following = button.dataset.following === '1';
        Working.start(button);
        try {
            const result = await Api.post(following ? '/api/unfollow-remote' : '/api/follow-user', { userId: id });
            if (!result) return;
            button.dataset.following = result.following ? '1' : '0';
            button.textContent = result.following ? 'Unfollow' : 'Follow';
            button.classList.toggle('Removing', result.following);
        } finally {
            Working.stop(button);
        }
    }

    static async #block(button) {
        if (!await Dialog.confirm('Block this user? This will remove any existing friendship.')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/block', { userId: button.dataset.userId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.OtherUser'));
        } finally {
            Working.stop(button);
        }
    }

    static async #removeFriend(button) {
        if (!await Dialog.confirm('Remove this friend?')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/remove-friend', { userId: button.dataset.userId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.OtherUser'));
        } finally {
            Working.stop(button);
        }
    }

    static async #toggleMod(button) {
        const id = button.dataset.userId;
        const isMod = button.dataset.isMod === '1';
        Working.start(button);
        try {
            const result = await Api.post('/api/set-mod', { userId: id, isMod: !isMod });
            if (!result) return;
            button.dataset.isMod = result.isMod ? '1' : '0';
            button.textContent = result.isMod ? 'Remove Mod' : 'Make Mod';
        } finally {
            Working.stop(button);
        }
    }

    static async #unblock(button) {
        const id = button.dataset.userId;
        const card = button.closest('.OtherUser');
        Working.start(button);
        try {
            const result = await Api.post('/api/unblock', { userId: id });
            if (!result) return;
            card.replaceWith(OtherUser.fromData(result).toElement());
        } finally {
            Working.stop(button);
        }
    }

    static async #acceptFriendRequest(button) {
        const friendshipId = button.dataset.friendshipId;
        Working.start(button);
        const result = await Api.post('/api/accept-friend', { friendshipId });
        if (!result) {
            Working.stop(button);
            return;
        }
        const card = button.closest('.OtherUser');
        if (card && result.userId) {
            const newCard = OtherUser.fromData(result).toElement();
            const pendingList = card.closest('.UserList[data-list-type="incoming"]');
            if (pendingList) {
                const friendsList = document.querySelector('.UserList[data-list-type="friends"]');
                if (friendsList) {
                    friendsList.prepend(list_item(newCard));
                }
                DOMUtils.slideOut(card);
                if (pendingList.querySelectorAll('li:not(.SlidingOut) .OtherUser').length === 0) {
                    DOMUtils.slideOut(pendingList.closest('.UserSection') || pendingList);
                }
            } else {
                card.replaceWith(newCard);
            }
        }
    }

    static async #denyFriendRequest(button) {
        Working.start(button);
        const result = await Api.post('/api/deny-friend', { friendshipId: button.dataset.friendshipId });
        if (!result) {
            Working.stop(button);
            return;
        }
        DOMUtils.slideOut(button.closest('.OtherUser'));
    }

    static async #ban(button) {
        const reason = await Dialog.prompt(
            'Ban this user? This hides all their content and blocks their login. They\'ll see this reason on the login form.',
            { confirmLabel: 'Ban', placeholder: 'Reason for ban (required)' }
        );
        if (reason === null) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/ban', { userId: button.dataset.userId, reason });
            if (!result) return;
            button.textContent = 'Banned';
        } finally {
            Working.stop(button);
        }
    }
}


ReadyHandler.add(OtherUser.init);

