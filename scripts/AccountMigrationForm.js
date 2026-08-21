import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';
import { Working } from '/scripts/Working.js';

/**
 * Declaring an alias, and moving away.
 *
 * The two are saved together but mean different things: an alias is only a
 * permission for another account to move here, while a destination actually
 * sends the move. The endpoint saves the aliases first for that reason, so a
 * refused move does not also lose them.
 */
export class AccountMigrationForm {
    static init() {
        const form = document.querySelector('.AccountMigrationForm');

        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const movedTo = form.querySelector('[name="movedTo"]').value.trim();
            const alsoKnownAs = form.querySelector('[name="alsoKnownAs"]').value;

            if (movedTo !== '' && !await confirmMove(movedTo)) {
                return;
            }

            const submit = form.querySelector('button[type="submit"]');
            Working.start(submit);

            try {
                const result = await Api.post('/api/account-migration', { movedTo, alsoKnownAs });

                if (!result) return;

                const words = Strings.for('ClientStatus');
                Toast.show(result.moved ? words.followersNotified || '' : words.saved || '');
            } finally {
                Working.stop(submit);
            }
        });
    }
}

async function confirmMove(destination) {
    const { Dialog } = await import('/scripts/Dialog.js');

    return Dialog.confirm(
        `Move this account to ${destination}? Your followers will be asked to follow you there, and your posts stay here - they cannot be taken along.`
    );
}

ReadyHandler.add(AccountMigrationForm.init);
