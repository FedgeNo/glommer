import { Strings } from '/scripts/Strings.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Toast } from '/scripts/Toast.js';

export class PostShareButton {
    static init() {
        // Delegated on document so share buttons on dynamically added posts
        // (infinite scroll, a just-composed post) work without rebinding.
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.PostShareButton');
            if (!button) {
                return;
            }

            event.preventDefault();
            const url = button.dataset.shareUrl;
            if (!url) {
                return;
            }

            // Use the Web Share API if available (mobile / supported desktop)
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: document.title,
                        url: url,
                    });
                    return;
                } catch {
                    // user cancelled or share failed – fall through to copy
                }
            }

            // Fallback: copy URL to clipboard
            try {
                await navigator.clipboard.writeText(url);
                Toast.show(Strings.for('ClientStatus').linkCopied || '');
            } catch {
                Toast.show(Strings.for('ClientStatus').linkCopyFailed || '');
            }
        });
    }
}

ReadyHandler.add(PostShareButton.init);
