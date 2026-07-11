/**
 * Client-side mirror of the PHP BannedUser class - one entry on the admin
 * Banned Users page (identity plus an Unban button), used when entries arrive
 * as JSON via infinite scroll or the banned-user search.
 */
class BannedUser {
    userId = null;
    username = null;
    displayName = null;
    image = null;

    static fromData(data) {
        const banned_user = new BannedUser();
        Object.assign(banned_user, data);
        return banned_user;
    }

    name() {
        return this.displayName || this.username;
    }

    toElement() {
        const div = document.createElement('div');
        div.className = 'User Card BannedUser MountIn';
        div.dataset.userId = this.userId;

        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-3';

        const header = document.createElement('a');
        header.className = 'd-flex align-items-center gap-3';
        header.href = window.siteURL + '/users/' + this.username + '/';

        header.appendChild(avatar_element(Boolean(this.image), this.image, this.name(), this.userId));

        const info = document.createElement('div');

        const name_line = document.createElement('div');
        name_line.className = 'fw-semibold';
        name_line.textContent = this.name();
        info.appendChild(name_line);

        const username_line = document.createElement('div');
        username_line.className = 'Muted text-sm';
        username_line.textContent = '@' + this.username;
        info.appendChild(username_line);

        header.appendChild(info);
        row.appendChild(header);

        const unban = document.createElement('button');
        unban.className = 'ms-auto Btn UnbanButton';
        unban.dataset.userId = this.userId;
        unban.textContent = 'Unban';
        row.appendChild(unban);

        div.appendChild(row);

        return div;
    }
}
