// WelcomeBanner.js
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Closing the welcome on the home feed.
 *
 * Two different closings behind one button. Ticked, it is gone for good and
 * the server is told so; unticked, it goes away here and comes back next
 * time, which needs telling nobody - somebody who closed it before finishing
 * reading has not decided anything.
 */
export class WelcomeBanner {
    static init() {
        const banner = document.querySelector('.WelcomeBanner');
        if (!banner) return;

        const dismiss = banner.querySelector('.WelcomeBannerDismissButton');
        const forGood = banner.querySelector('[name="welcomeDismissed"]');
        if (!dismiss) return;

        dismiss.addEventListener('click', async () => {
            dismiss.disabled = true;

            if (forGood?.checked) {
                const result = await Api.post('/api/dismiss-welcome', { forGood: true });

                // Left standing when the server would not record it, rather
                // than vanishing on a promise it did not make.
                if (!result) {
                    dismiss.disabled = false;

                    return;
                }
            }

            banner.remove();
        });
    }
}

ReadyHandler.add(WelcomeBanner.init);
