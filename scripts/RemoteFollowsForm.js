// RemoteFollowsForm.js
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class RemoteFollowsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.RemoteFollowsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const data = await Api.post('/api/follow-remote', {
                handles: form.querySelector('[name="handles"]').value,
            });
            submit_button.disabled = false;
            if (!data) return;
            const results = data.results || [];
            const unprocessed = data.unprocessed || [];
            const succeeded = results.filter(r => r.ok).length;
            const failed = results.filter(r => !r.ok);
            const parts = [`Followed ${succeeded} account${succeeded === 1 ? '' : 's'}.`];
            if (failed.length > 0) {
                const shown = failed.slice(0, 3).map(r => `${r.handle} (${r.error})`).join(', ');
                parts.push(`${failed.length} failed: ${shown}${failed.length > 3 ? ', ...' : ''}`);
            }
            if (unprocessed.length > 0) {
                parts.push(`${unprocessed.length} not attempted yet - submit again to continue.`);
            }
            Toast.show(parts.join(' '));
            if (failed.length === 0 && unprocessed.length === 0) window.location.reload();
        });
    }
}

ReadyHandler.add(RemoteFollowsForm.init);
