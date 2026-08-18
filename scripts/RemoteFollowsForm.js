// RemoteFollowsForm.js
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';
import { Strings } from '/scripts/Strings.js';

export class RemoteFollowsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.RemoteFollowsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/follow-remote', {
                handles: form.querySelector('[name="handles"]').value,
            }, { form });
            Working.stop(submit_button);
            if (!data) return;
            const results = data.results || [];
            const unprocessed = data.unprocessed || [];
            const succeeded = results.filter(r => r.ok).length;
            const failed = results.filter(r => !r.ok);
            const parts = [`Followed ${succeeded} account${succeeded === 1 ? '' : 's'}.`];
            if (failed.length > 0) {
                const shown = failed.slice(0, 3).map(r => `${r.handle} (${r.error})`).join(', ');
                parts.push(`${failed.length} failed: ${shown}${failed.length > 3 ? ', …' : ''}`);
            }
            if (unprocessed.length > 0) {
                parts.push(`${unprocessed.length} not attempted yet - submit again to continue.`);
            }
            Toast.show(parts.join(' '));

            // The new follows join the list in place, pending until their
            // server accepts - the same row the server renders for one.
            const followed = results.filter(r => r.ok);

            if (followed.length > 0) {
                let list = form.querySelector('.RemoteFollowsList');

                if (!list) {
                    list = document.createElement('div');
                    list.className = 'RemoteFollowsList';
                    form.appendWithSpace(list);
                }

                for (const result of followed) {
                    const item = document.createElement('div');
                    item.className = 'RemoteFollowsItem';
                    item.appendWithSpace(document.createTextNode(result.handle));

                    // A follow just submitted is always freshly pending - the
                    // same key RemoteFollowsForm.php reads for the same word,
                    // so a follow accepted before the next reload doesn't
                    // read differently from one the server rendered.
                    const status = document.createElement('span');
                    status.className = 'RemoteFollowsStatus';
                    status.textContent = Strings.for('RemoteFollowsForm', { statusPending: 'pending' }).statusPending;
                    item.appendWithSpace(status);

                    list.appendWithSpace(item);
                }
            }

            if (failed.length === 0 && unprocessed.length === 0) {
                form.querySelector('[name="handles"]').value = '';
            }
        });
    }
}

ReadyHandler.add(RemoteFollowsForm.init);
