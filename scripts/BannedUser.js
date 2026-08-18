import { User } from '/scripts/User.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Strings } from '/scripts/Strings.js';
import { Working } from '/scripts/Working.js';

/**
 * Client-side mirror of the PHP BannedUser class - one entry on the admin
 * Banned Users page (identity plus an Unban button), used when entries arrive
 * as JSON via infinite scroll or the banned-user search.
 */
export class BannedUser extends User {
    userId = null;
    slug = null;
    title = null;
    image = null;

    toElement() {
        const div = document.createElement('div');
        div.className = 'User BannedUser MountIn';
        div.dataset.userId = this.userId;

        const row = document.createElement('div');
        row.className = 'BannedUserRow';

        row.appendWithSpace(this.header());

        const unban = document.createElement('button');
        unban.type = 'button';
        unban.className = 'Button UserUnbanButton';
        unban.dataset.userId = this.userId;
        unban.textContent = Strings.for('UserUnbanButton', { name: 'Unban' }).name;
        row.appendWithSpace(unban);

        div.appendWithSpace(row);

        return div;
    }

    // ----------------------------------------------------------------
    // Static action handlers (unban)
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', async (event) => {
            const unbanBtn = event.target.closest('.UserUnbanButton');
            if (unbanBtn) {
                BannedUser.#unban(unbanBtn);
            }
        });
    }

    static async #unban(button) {
        if (!await Dialog.confirm('Unban this user? Their content and login work again.')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/unban', { userId: button.dataset.userId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.BannedUser'));
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(BannedUser.init);
