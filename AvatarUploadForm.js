// AvatarUploadForm.js
import { ClientConfig } from '/ClientConfig.js';
import { Toast } from '/Toast.js';
import { csrf_headers } from '/utils.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class AvatarUploadForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.AvatarUploadForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/upload-avatar', {
                    method: 'POST',
                    headers: csrf_headers(),
                    body: new FormData(form),
                });
                const data = await response.json();
                if (!response.ok) {
                    Toast.show(data.error || 'Could not upload the image. Please try again.');
                    return;
                }
                const avatar = document.createElement('img');
                avatar.className = 'Avatar';
                avatar.alt = 'Your avatar';
                avatar.src = data.response.image;
                form.closest('.User').querySelector('.UserLink .Avatar').replaceWith(avatar);
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
            } finally {
                submit_button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(AvatarUploadForm.init);
