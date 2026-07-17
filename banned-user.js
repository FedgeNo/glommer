/**
 * Client-side mirror of the PHP BannedUser class - one entry on the admin
 * Banned Users page (identity plus an Unban button), used when entries arrive
 * as JSON via infinite scroll or the banned-user search.
 */
class BannedUser extends User {
    userId = null;
    slug = null;
    title = null;
    image = null;

    toElement() {
        const div = document.createElement('div');
        div.className = 'User Card BannedUser MountIn';
        div.dataset.userId = this.userId;

        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-3';

        row.appendChild(this.header());

        const unban = document.createElement('button');
        unban.type = 'button';
        unban.className = 'ms-auto Btn UnbanButton';
        unban.dataset.userId = this.userId;
        unban.textContent = 'Unban';
        row.appendChild(unban);

        div.appendChild(row);

        return div;
    }
}
