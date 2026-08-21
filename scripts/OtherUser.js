import { ClientConfig } from '/scripts/ClientConfig.js';
import { User } from '/scripts/User.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { list_item } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Strings } from '/scripts/Strings.js';
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

    /**
     * Mirrors UserFollowButton and UserModButton. Each is written in two places
     * here - once when the card is built, once when the server answers - so the
     * wording lives in one method rather than being got right twice.
     */
    static followName(following) {
        const words = Strings.for('UserFollowButton', { follow: 'Follow', unfollow: 'Unfollow' });

        return following ? words.unfollow : words.follow;
    }

    static modName(isMod) {
        const words = Strings.for('UserModButton', { make: 'Make Mod', remove: 'Remove Mod' });

        return isMod ? words.remove : words.make;
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
        unblock_button.className = 'Button UserUnblockButton';
            unblock_button.dataset.userId = this.userId;
            unblock_button.textContent = Strings.for('UserUnblockButton', { name: 'Unblock' }).name;
            div.appendWithSpace(unblock_button);
        } else if (!this.blockedByOther) {
            const sent_by_viewer = this.friendshipStatus === 'pending' && this.friendshipSentByViewer;

            const actions = document.createElement('div');
        actions.className = 'OtherUserActions';

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
                follow_button.textContent = OtherUser.followName(this.following);
                actions.appendWithSpace(follow_button);
            } else if (this.friendshipStatus === null || sent_by_viewer) {
                const friend_button = document.createElement('button');
                friend_button.type = 'button';
                friend_button.className = sent_by_viewer
                    ? 'Button FriendRequestButton Removing'
                    : 'Button FriendRequestButton';
                friend_button.dataset.userId = this.userId;
                friend_button.dataset.sent = sent_by_viewer ? '1' : '0';
                const words = Strings.for('OtherUserClient');
                friend_button.textContent = sent_by_viewer ? words.cancelRequest || '' : words.addFriend || '';
                actions.appendWithSpace(friend_button);
            }

            const message_link = document.createElement('a');
            message_link.className = 'Button';
            message_link.href = ClientConfig.siteURL() + '/messages/' + this.slug;
            message_link.textContent = Strings.for('OtherUser', { message: 'Message' }).message;

            const block_button = document.createElement('button');
            block_button.type = 'button';
            block_button.className = 'Button UserBlockButton';
            block_button.dataset.userId = this.userId;
            block_button.textContent = Strings.for('UserBlockButton', { name: 'Block' }).name;

            let report_or_ban_button = null;

            if (Number(this.userId) !== 1) {
                if (ClientConfig.get('currentUserCanModerate')) {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Button UserBanButton';
                    report_or_ban_button.dataset.userId = this.userId;
                    report_or_ban_button.textContent = Strings.for('OtherUserClient').ban || '';
                } else {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Button ReportButton';
                    report_or_ban_button.dataset.targetType = 'user';
                    report_or_ban_button.dataset.targetId = this.userId;
                    report_or_ban_button.textContent = Strings.for('ReportButton', { name: 'Report' }).name;
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
                friends_link.textContent = Strings.for('OtherUserClient').viewFriends || '';
                actions.appendWithSpace(friends_link);
            }

            if (this.friendshipStatus === 'accepted') {
                const remove_friend_button = document.createElement('button');
                remove_friend_button.type = 'button';
                remove_friend_button.className = 'Button FriendRemoveButton';
                remove_friend_button.dataset.userId = this.userId;
                remove_friend_button.textContent = Strings.for('FriendRemoveButton', { name: 'Remove Friend' }).name;
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
                mod_button.textContent = OtherUser.modName(this.isMod);
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
            const words = Strings.for('OtherUserClient');
            button.textContent = result.sent ? words.cancelRequest || '' : words.addFriend || '';
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
            button.textContent = OtherUser.followName(result.following);
            button.classList.toggle('Removing', result.following);
        } finally {
            Working.stop(button);
        }
    }

    static async #block(button) {
        if (!await Dialog.confirm(Strings.for('OtherUserClient').blockConfirm || '')) return;
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
        if (!await Dialog.confirm(Strings.for('OtherUserClient').removeFriendConfirm || '')) return;
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
            button.textContent = OtherUser.modName(result.isMod);
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
            Strings.for('OtherUserClient').banConfirm || '',
            {
                confirmLabel: Strings.for('OtherUserClient').ban || '',
                placeholder: Strings.for('OtherUserClient').banPlaceholder || '',
            }
        );
        if (reason === null) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/ban', { userId: button.dataset.userId, reason });
            if (!result) return;
            button.textContent = Strings.for('OtherUserClient').banned || '';
        } finally {
            Working.stop(button);
        }
    }
}


ReadyHandler.add(OtherUser.init);
