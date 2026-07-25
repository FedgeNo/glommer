class Notification {
    notificationId = null;
    userId = null;
    type = null;
    postId = null;
    createdAt = null;
    actor = null;
    element = null;

    static fromData(data) {
        const notification = new Notification();
        Object.assign(notification, data);
        return notification;
    }

    actorName() {
        return this.actor.title || this.actor.slug;
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
            case 'mailFromNotConfigured':
                return 'No mail "from" address is configured, so emails can\'t be sent. Set one in Site Settings (Mail section) or via bin/install.php.';
            case 'systemError':
                return 'A server error occurred. Check the error log for details.';
            case 'passwordRemovedGoogle':
                return 'Your password was removed when you signed in with Google. Use "Forgot password" if you want to set a new one.';
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
            case 'mention':
                return name + ' mentioned you in a post';
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
                return window.siteURL + '/users/' + this.actor.slug + '/';
            case 'message':
                return window.siteURL + '/messages/' + this.actor.slug;
            // Unlike 'like'/'reply' (the recipient's OWN post), a mentioned
            // post belongs to the ACTOR (whoever wrote the post that mentions
            // you) - same reasoning as 'friendAccepted'/'message' above using
            // actor.slug, not currentUserUsername.
            case 'mention':
                return window.siteURL + '/users/' + this.actor.slug + '/' + this.postId;
            case 'passwordRemovedGoogle':
                return window.siteURL + '/forgot-password';
            default:
                return null;
        }
    }

    toElement() {
        const div = document.createElement('div');
        div.className = 'Notification MountIn';
        div.dataset.notificationId = this.notificationId;

        // A notification links to its subject when it has one; a targetless one
        // (a system error, say) is a plain block, never a link to nowhere.
        const target = this.targetURL();
        const container = document.createElement(target === null ? 'div' : 'a');
        container.className = 'd-flex align-items-center gap-3';
        if (target !== null) {
            container.href = target;
        }

        container.appendChild(Avatar.forUser(this.actor));

        const info = document.createElement('div');

        const text = document.createElement('div');
        text.textContent = this.text();
        info.appendChild(text);

        const created_at = parse_server_date(this.createdAt);

        const meta = document.createElement('time');
        meta.className = 'muted text-sm RelativeTime';
        meta.dateTime = created_at.toISOString();
        meta.textContent = format_relative_time(created_at.toISOString());
        info.appendChild(meta);

        container.appendChild(info);
        div.appendChild(container);

        this.element = div;

        return div;
    }
}
