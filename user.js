/**
 * Client-side mirrors of the PHP user-identity classes - Avatar, UserBio, and
 * the User base card. Each builds the same element its PHP counterpart renders,
 * from the same property names the database row carries (slug, title,
 * description), so a user row travels through JSON without transcoding.
 */

/** Mirrors Avatar.php: an <img> when the user has one, otherwise a pure
 * CSS/text fallback circle in a color derived from their userId, showing the
 * first letter of their name. */
class Avatar {
    static forUser(user) {
        if (!user) {
            return Avatar.create(false, null, null, 0);
        }

        return Avatar.create(Boolean(user.image), user.image, user.title || user.slug, user.userId);
    }

    static create(has_image, image_url, name, user_id) {
        if (has_image && image_url) {
            const image = document.createElement('img');
            image.className = 'Avatar';
            image.src = image_url;
            image.alt = (name || '') + '\'s avatar';
            return image;
        }

        const fallback = document.createElement('div');
        fallback.className = 'Avatar AvatarInitial';
        fallback.setAttribute('aria-hidden', 'true');
        fallback.style.setProperty('--avatar-hue', ((Number(user_id) * 137) % 360) + 'deg');

        // Array.from splits on code points, not UTF-16 units - .charAt(0) on a
        // name starting with an emoji or other astral character would produce a
        // lone surrogate half instead of the character.
        const first_char = Array.from(name || '')[0];
        fallback.textContent = first_char ? first_char.toUpperCase() : '?';

        return fallback;
    }
}

/** Mirrors UserBio.php: a user's plain-text bio, linkified the same way the
 * server renders it (delta.js's shared linkifier), so a saved bio round-trips
 * identically. Newlines are preserved by the .UserBio white-space rule. */
class UserBio {
    constructor(user) {
        this.description = user.description || '';
    }

    toElement() {
        const bio = document.createElement('div');
        bio.className = 'UserBio';

        for (const segment of linkify_tokenize(this.description)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                bio.appendChild(linked_node(segment.text, inner));
            } else if (segment.type === 'hashtag') {
                bio.appendChild(hashtag_node(segment.tag, inner));
            } else if (segment.type === 'mention') {
                bio.appendChild(mention_node(segment.username, inner));
            } else {
                bio.appendChild(inner);
            }
        }

        return bio;
    }
}

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
