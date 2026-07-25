/** Mirrors User.php: the identity card and the byline header, shared by every
 * user-shaped thing (OtherUser, FriendRequest, BannedUser, a report's user
 * target, a message sender). */
class User {
    static fromData(data) {
        // `new this()` (not `new User()`) so subclasses get an instance of
        // themselves.
        const user = new this();
        Object.assign(user, data);
        return user;
    }

    name() {
        return this.title || this.slug;
    }

    /**
     * Mirrors User::header(): the avatar + display name + username block used
     * wherever a message, post, or similar item needs to show who it's from -
     * one clickable link to their profile.
     */
    header() {
        const header = document.createElement('a');
        header.href = window.siteURL + '/users/' + this.slug + '/';
        header.className = 'UserHeader d-flex align-items-center gap-3';

        header.appendChild(Avatar.forUser(this));

        const info = document.createElement('div');
        info.className = 'UserHeaderInfo';

        const name_line = document.createElement('div');
        name_line.className = 'fw-semibold UserHeaderName';
        name_line.textContent = this.name();
        info.appendChild(name_line);

        const username_line = document.createElement('div');
        username_line.className = 'muted text-sm';
        username_line.textContent = '@' + this.slug;
        info.appendChild(username_line);

        header.appendChild(info);

        return header;
    }

    /**
     * Mirrors User::toDOM(): the full identity card - avatar, name, @username,
     * joined date, and bio, the identity all one link to the profile - wrapped
     * in a .User.Card.
     */
    toElement() {
        const div = document.createElement('div');
        div.className = 'User Card';

        if (this.slug) {
            div.dataset.username = this.slug;
        }

        // The identity block and the bio stack in a growing left column
        // (UserMain), so the bio runs the full width beneath the avatar/name up
        // to whatever sits on the card's right (the action buttons).
        const main = document.createElement('div');
        main.className = 'UserMain';

        const link = document.createElement('a');
        link.className = 'UserLink';
        link.href = window.siteURL + '/users/' + this.slug + '/';

        link.appendChild(Avatar.forUser(this));

        const info = document.createElement('div');
        info.className = 'UserIdentity';

        const name_heading = document.createElement('h2');
        name_heading.className = 'DisplayName';
        name_heading.textContent = this.name();
        info.appendChild(name_heading);

        const username_line = document.createElement('div');
        username_line.className = 'muted text-sm';
        username_line.textContent = '@' + this.slug;
        info.appendChild(username_line);

        if (this.createdAt) {
            const joined = document.createElement('div');
            joined.className = 'muted text-sm';
            joined.textContent = 'Joined ' + parse_server_date(this.createdAt).toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            });
            info.appendChild(joined);
        }

        link.appendChild(info);
        main.appendChild(link);

        if (this.description && this.description.trim() !== '') {
            main.appendChild(new UserBio(this).toElement());
        }

        div.appendChild(main);

        return div;
    }
}
