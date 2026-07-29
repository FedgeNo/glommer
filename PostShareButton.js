import { ReadyHandler } from '/ReadyHandler.js';
import { Toast } from '/Toast.js';

export class PostShareButton {
    static init() {
        const buttons = document.querySelectorAll('.PostShareButton');
        if (!buttons.length) {
            return;
        }

        buttons.forEach((button) => {
            button.addEventListener('click', async (event) => {
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
                    Toast.show('Link copied to clipboard');
                } catch {
                    Toast.show('Could not copy link');
                }
            });
        });
    }
}

ReadyHandler.add(PostShareButton.init);

