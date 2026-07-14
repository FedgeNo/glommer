class Notification {
    notificationId = null;
    userId = null;
    actorId = null;
    type = null;
    postId = null;
    createdAt = null;
    actorUsername = null;
    actorDisplayName = null;
    actorImage = null;
    element = null;

    static fromData(data) {
        const notification = new Notification();
        Object.assign(notification, data);
        return notification;
    }

    actorName() {
        return this.actorDisplayName || this.actorUsername;
    }

    text() {
        switch (this.type) {
            case 'postReady':
                return 'Your media has finished processing and is now live';
            case 'uploadPartlyFailed':
                return 'Your post is live, but one or more of its files couldn\'t be processed';
            case 'uploadFailed':
                return 'One of your uploads failed to process and was not posted';
            case 'mailerFailed':
                return 'Email delivery failed - the mailer may be down. Please check your mail configuration.';
            case 'systemError':
                return 'A server error occurred. Check the error log for details.';
            default:
                return this.actorText();
        }
    }

    actorText() {
        const name = this.actorName();

        switch (this.type) {
            case 'like':
                return name + ' liked your post';
            case 'reply':
                return name + ' replied to your post';
            case 'friendRequest':
                return name + ' sent you a friend request';
            case 'friendAccepted':
                return name + ' accepted your friend request';
            case 'message':
                return name + ' sent you a message';
            default:
                return name + ' did something';
        }
    }

    targetURL() {
        switch (this.type) {
            case 'like':
            case 'reply':
            case 'postReady':
            case 'uploadPartlyFailed':
                return window.siteURL + '/users/' + window.currentUserUsername + '/' + this.postId;
            case 'friendRequest':
                return window.siteURL + '/users/' + window.currentUserUsername + '/friends';
            case 'friendAccepted':
                return window.siteURL + '/users/' + this.actorUsername + '/';
            case 'message':
                return window.siteURL + '/messages/' + this.actorUsername;
            default:
                return '#';
        }
    }

    toElement() {
        const div = document.createElement('div');
        div.className = 'Notification MountIn';
        div.dataset.notificationId = this.notificationId;

        const link = document.createElement('a');
        link.className = 'd-flex align-items-center gap-3';
        link.href = this.targetURL();

        link.appendChild(avatar_element(Boolean(this.actorImage), this.actorImage, this.actorName(), this.actorId));

        const info = document.createElement('div');

        const text = document.createElement('div');
        text.textContent = this.text();
        info.appendChild(text);

        const created_at = parse_server_date(this.createdAt);

        const meta = document.createElement('time');
        meta.className = 'Muted text-sm RelativeTime';
        meta.dateTime = created_at.toISOString();
        meta.textContent = format_relative_time(created_at.toISOString());
        info.appendChild(meta);

        link.appendChild(info);
        div.appendChild(link);

        this.element = div;

        return div;
    }
}
