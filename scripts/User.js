import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Avatar } from '/scripts/Avatar.js';
import { UserBio } from '/scripts/UserBio.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { Toast } from '/scripts/Toast.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { RelativeTime } from '/scripts/RelativeTime.js';

/** Mirrors User.php: the identity card and the byline header, shared by every
 * user-shaped thing (OtherUser, FriendRequest, BannedUser, a report's user
 * target, a message sender). */
export class User {
    static fromData(data) {
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
        header.href = ClientConfig.siteURL() + '/users/' + this.slug + '/';
        header.className = 'UserHeader d-flex align-items-center gap-3';

        header.appendWithSpace(Avatar.forUser(this));

        const info = document.createElement('div');
        info.className = 'UserHeaderInfo';

        const name_line = document.createElement('div');
        name_line.className = 'fw-semibold UserHeaderName';
        name_line.textContent = this.name();
        info.appendWithSpace(name_line);

        const username_line = document.createElement('div');
        username_line.className = 'muted text-sm';
        username_line.textContent = '@' + this.slug;
        info.appendWithSpace(username_line);

        header.appendWithSpace(info);

        return header;
    }

    /**
     * Mirrors User::toDOM(): the full identity card - avatar, name, @username,
     * joined date, and bio, the identity all one link to the profile - wrapped
     * in a .User.Card.
     */
    toElement() {
        const div = document.createElement('div');
        div.className = 'User';

        if (this.slug) {
            div.dataset.username = this.slug;
        }

        const main = document.createElement('div');
        main.className = 'UserMain';

        const link = document.createElement('a');
        link.className = 'UserLink';
        link.href = ClientConfig.siteURL() + '/users/' + this.slug + '/';

        link.appendWithSpace(Avatar.forUser(this));

        const info = document.createElement('div');
        info.className = 'UserIdentity';

        const name_heading = document.createElement('h2');
        name_heading.className = 'DisplayName';
        name_heading.textContent = this.name();
        info.appendWithSpace(name_heading);

        const username_line = document.createElement('div');
        username_line.className = 'muted text-sm';
        username_line.textContent = '@' + this.slug;
        info.appendWithSpace(username_line);

        if (this.createdAt) {
            const joined = document.createElement('div');
            joined.className = 'muted text-sm';
            joined.textContent = 'Joined ' + RelativeTime.date(this.createdAt);
            info.appendWithSpace(joined);
        }

        link.appendWithSpace(info);
        main.appendWithSpace(link);

        if (this.description && this.description.trim() !== '') {
            main.appendWithSpace(new UserBio(this).toElement());
        }

        div.appendWithSpace(main);

        return div;
    }

    // ----------------------------------------------------------------
    // Static action handlers (profile editing, Google delete, resend verification, revoke session)
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', (event) => {
            // Start profile editing
            const editTrigger = event.target.closest('.User.CurrentUser .DisplayName, .User.CurrentUser .UserBio, .User.CurrentUser .EditProfileButton');
            if (editTrigger && !editTrigger.closest('a')) {
                const card = editTrigger.closest('.User.CurrentUser');
                if (card && !card.classList.contains('Editing')) {
                    User.#startEdit(card);
                }
                return;
            }

            // Save profile
            const saveBtn = event.target.closest('.ProfileSaveButton');
            if (saveBtn) {
                User.#save(saveBtn.closest('.User.CurrentUser'));
                return;
            }

            // Google delete
            const googleDelBtn = event.target.closest('.GoogleDeleteButton');
            if (googleDelBtn) {
                User.#confirmGoogleDelete(googleDelBtn);
                return;
            }

            // Resend verification email
            const resendBtn = event.target.closest('.ResendVerificationButton');
            if (resendBtn) {
                User.#resendVerification(resendBtn);
                return;
            }

            // Revoke session
            const revokeBtn = event.target.closest('.RevokeSessionButton');
            if (revokeBtn) {
                User.#revokeSession(revokeBtn);
            }
        });
    }

    static #startEdit(card) {
        card.classList.add('Editing');

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'DisplayNameInput';
        nameInput.maxLength = 50;
        nameInput.value = card.dataset.title;
        nameInput.placeholder = 'Display name';
        card.querySelector('.DisplayName').replaceWith(nameInput);

        const bioInput = document.createElement('textarea');
        bioInput.className = 'UserBioInput';
        bioInput.maxLength = 500;
        bioInput.value = card.dataset.description;
        bioInput.placeholder = 'Add a bio…';
        const bio = card.querySelector('.UserBio');
        bio.replaceWith(bioInput);

        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'Button ProfileSaveButton';
        save.textContent = 'Save';
        bioInput.after(save);

        nameInput.focus();
    }

    static async #save(card) {
        const nameInput = card.querySelector('.DisplayNameInput');
        const bioInput = card.querySelector('.UserBioInput');
        const save = card.querySelector('.ProfileSaveButton');
        save.disabled = true;

        const data = await Api.post('/api/update-profile', {
            title: nameInput.value,
            description: bioInput.value,
        });

        if (!data) {
            save.disabled = false;
            return;
        }

        card.dataset.title = data.title || '';
        card.dataset.description = data.description || '';

        const heading = document.createElement('h2');
        heading.className = 'DisplayName';
        heading.textContent = data.title || card.dataset.username;
        nameInput.replaceWith(heading);

        bioInput.replaceWith(new UserBio(data).toElement());

        save.remove();
        card.classList.remove('Editing');
        Toast.show('Profile saved.');
    }

    static async #confirmGoogleDelete(button) {
        if (!await Dialog.confirm("Delete your account? Your posts, replies, and messages are gone permanently - this can't be undone. You'll confirm by signing in with Google.")) {
            return;
        }
        window.location = ClientConfig.siteURL() + '/auth-google?intent=delete';
    }

    static async #resendVerification(button) {
        button.disabled = true;
        const result = await Api.post('/api/resend-verification');
        if (!result) {
            button.disabled = false;
            return;
        }
        button.textContent = 'Sent!';
    }

    static async #revokeSession(button) {
        if (!await Dialog.confirm('Revoke this device? It will be signed out and have to log in again.')) return;
        button.disabled = true;
        try {
            const result = await Api.post('/api/revoke-session', { tokenId: button.dataset.tokenId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.RememberedDevice'));
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(User.init);

