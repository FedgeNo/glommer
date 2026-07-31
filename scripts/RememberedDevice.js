import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class RememberedDevice {
    static init() {
        const buttons = document.querySelectorAll('.RememberedDeviceRevokeButton');
        if (!buttons.length) {
            return;
        }

        buttons.forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const tokenId = button.dataset.tokenId;
                if (!tokenId) {
                    return;
                }

                button.textContent = 'Revoking…';
                button.disabled = true;

                try {
                    await Api.post('/api/revoke-session', { tokenId });
                    const card = button.closest('.RememberedDevice');
                    if (card) {
                        card.remove();
                    }
                } catch {
                    button.textContent = 'Failed';
                    button.disabled = false;
                }
            });
        });
    }
}

ReadyHandler.add(RememberedDevice.init);

