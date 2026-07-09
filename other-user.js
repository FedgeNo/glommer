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
    element = null;

    static fromData(data) {
        const user = new OtherUser();
        Object.assign(user, data);
        return user;
    }

    toElement() {
        const div = document.createElement('div');
        div.className = 'User Card OtherUser MountIn';
        div.dataset.username = this.username;

        div.appendChild(avatar_element(Boolean(this.image), this.image, this.displayName || this.username, this.userId, false));

        const info = document.createElement('div');

        const name_heading = document.createElement('h2');
        const name_link = document.createElement('a');
        name_link.href = window.siteURL + '/users/' + this.username + '/';
        name_link.textContent = this.displayName || this.username;
        name_heading.appendChild(name_link);
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

        div.appendChild(info);

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

            const report_button = document.createElement('button');
            report_button.type = 'button';
            report_button.className = 'Btn ReportButton';
            report_button.dataset.targetType = 'user';
            report_button.dataset.targetId = this.userId;
            report_button.textContent = 'Report';

            actions.appendChild(message_link);
            actions.appendChild(block_button);
            actions.appendChild(report_button);
            div.appendChild(actions);
        }

        this.element = div;

        return div;
    }
}
