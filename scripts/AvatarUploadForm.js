import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class AvatarUploadForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.AvatarUploadForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/upload-avatar', new FormData(form), { form });

                if (!data) return;

                const avatar = document.createElement('img');
                avatar.className = 'Avatar';
                avatar.alt = 'Your avatar';
                avatar.src = data.image;
                form.closest('.User').querySelector('.UserLink .Avatar').replaceWith(avatar);
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(AvatarUploadForm.init);
