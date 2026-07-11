class OtherUser {
    userId = null;
    username = null;
    displayName = null;
    image = null;
    createdAt = null;
    blockedByViewer = false;
    blockedByOther = false;
    friendshipStatus = null;
    friendshipSentByViewer = null;
    isMod = false;
    friendshipId = null;
    element = null;

    static fromData(data) {
        // `new this()` (not `new OtherUser()`) so subclasses like FriendRequest
        // get an instance of themselves.
        const user = new this();
        Object.assign(user, data);
        return user;
    }

    /**
     * Extra action buttons a subclass wants at the top of the action column
     * (mirrors OtherUser::beforeActions in PHP - e.g. Accept/Deny on an
     * incoming friend request).
     */
    beforeActions() {
        return [];
    }

    toElement() {
        const div = document.createElement('div');
        div.className = 'User Card OtherUser MountIn';
        div.dataset.username = this.username;

        if (this.friendshipId) {
            div.dataset.friendshipId = this.friendshipId;
        }

        // The whole identity block - avatar, name, username, joined - is one
        // link to the profile (mirrors User::toDOM).
        const link = document.createElement('a');
        link.className = 'UserLink';
        link.href = window.siteURL + '/users/' + this.username + '/';

        link.appendChild(avatar_element(Boolean(this.image), this.image, this.displayName || this.username, this.userId, false));

        const info = document.createElement('div');

        const name_heading = document.createElement('h2');
        name_heading.textContent = this.displayName || this.username;
        info.appendChild(name_heading);

        const username_line = document.createElement('div');
        username_line.className = 'Muted text-sm';
        username_line.textContent = '@' + this.username;
        info.appendChild(username_line);

        if (this.createdAt) {
            const joined = document.createElement('div');
            joined.className = 'Muted text-sm';
            joined.textContent = 'Joined ' + parse_server_date(this.createdAt).toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            });
            info.appendChild(joined);
        }

        link.appendChild(info);
        div.appendChild(link);

        // Mirror OtherUser::toDOM: no action buttons for a logged-out visitor
        // (public friends pages) or on the viewer's own card - which can turn
        // up in a third party's friends list.
        const is_self = window.currentUserId !== null && Number(window.currentUserId) === Number(this.userId);

        if (window.currentUserId === null || is_self) {
            this.element = div;
            return div;
        }

        if (this.blockedByViewer) {
            const unblock_button = document.createElement('button');
            unblock_button.type = 'button';
            unblock_button.className = 'Btn UnblockUserButton ms-auto';
            unblock_button.dataset.userId = this.userId;
            unblock_button.textContent = 'Unblock';
            div.appendChild(unblock_button);
        } else if (!this.blockedByOther) {
            const sent_by_viewer = this.friendshipStatus === 'pending' && this.friendshipSentByViewer;

            const actions = document.createElement('div');
            actions.className = 'd-flex flex-column gap-2 ms-auto';

            this.beforeActions().forEach((button) => actions.appendChild(button));

            if (this.friendshipStatus === null || sent_by_viewer) {
                const friend_button = document.createElement('button');
                friend_button.type = 'button';
                friend_button.className = 'Btn FriendRequestButton';
                friend_button.dataset.userId = this.userId;
                friend_button.dataset.sent = sent_by_viewer ? '1' : '0';
                friend_button.textContent = sent_by_viewer ? 'Cancel' : 'Add Friend';
                actions.appendChild(friend_button);
            }

            const message_link = document.createElement('a');
            message_link.className = 'Btn';
            message_link.href = window.siteURL + '/messages/' + this.username;
            message_link.textContent = 'Message';

            const block_button = document.createElement('button');
            block_button.type = 'button';
            block_button.className = 'Btn BlockUserButton';
            block_button.dataset.userId = this.userId;
            block_button.textContent = 'Block';

            // The admin (userId 1) can be neither banned nor reported (the
            // API rejects both - nobody could act on the report anyway), so
            // their card gets neither button.
            let report_or_ban_button = null;

            if (Number(this.userId) !== 1) {
                if (window.currentUserCanModerate) {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Btn BanButton';
                    report_or_ban_button.dataset.userId = this.userId;
                    report_or_ban_button.textContent = 'Ban';
                } else {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Btn ReportButton';
                    report_or_ban_button.dataset.targetType = 'user';
                    report_or_ban_button.dataset.targetId = this.userId;
                    report_or_ban_button.textContent = 'Report';
                }
            }

            actions.appendChild(message_link);

            const friends_link = document.createElement('a');
            friends_link.className = 'Btn';
            friends_link.href = window.siteURL + '/users/' + this.username + '/friends';
            friends_link.textContent = 'Friends';
            actions.appendChild(friends_link);

            if (this.friendshipStatus === 'accepted') {
                const remove_friend_button = document.createElement('button');
                remove_friend_button.type = 'button';
                remove_friend_button.className = 'Btn RemoveFriendButton';
                remove_friend_button.dataset.userId = this.userId;
                remove_friend_button.textContent = 'Remove Friend';
                actions.appendChild(remove_friend_button);
            }

            // Only the primary admin can promote/demote moderators - not
            // mods themselves, to avoid a mod-promotes-mod escalation chain.
            if (Number(window.currentUserId) === 1) {
                const mod_button = document.createElement('button');
                mod_button.type = 'button';
                mod_button.className = 'Btn ModButton';
                mod_button.dataset.userId = this.userId;
                mod_button.dataset.isMod = this.isMod ? '1' : '0';
                mod_button.textContent = this.isMod ? 'Remove Mod' : 'Make Mod';
                actions.appendChild(mod_button);
            }

            actions.appendChild(block_button);

            if (report_or_ban_button !== null) {
                actions.appendChild(report_or_ban_button);
            }

            div.appendChild(actions);
        }

        this.element = div;

        return div;
    }
}

// Mirrors the PHP FriendRequest (extends OtherUser, adds Accept/Deny via
// beforeActions and the FriendRequest CSS class) - the client-rendered card
// for an incoming friend request appended on scroll.
class FriendRequest extends OtherUser {
    beforeActions() {
        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'Btn AcceptFriendButton';
        accept.dataset.friendshipId = this.friendshipId;
        accept.textContent = 'Accept';

        const deny = document.createElement('button');
        deny.type = 'button';
        deny.className = 'Btn DenyFriendButton';
        deny.dataset.friendshipId = this.friendshipId;
        deny.textContent = 'Deny';

        return [accept, deny];
    }

    toElement() {
        const div = super.toElement();
        div.classList.add('FriendRequest');
        return div;
    }
}
