import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Choosing which language to read the site in - from the offer made to
 * somebody whose browser is set to one this site speaks, and from the selector
 * in their settings.
 *
 * Both do the same thing, because they are the same decision: tell the server,
 * then load the page again so it arrives in the language just chosen. A page
 * already built cannot be re-translated in place - every string on it came
 * from the server - so the reload is the point rather than a shortcut.
 *
 * Declining is an answer too. It is recorded the same way, so the offer is
 * made once and not on every page after.
 */
export class LanguagePrompt {
    static init() {
        document.addEventListener('click', async (event) => {
            const accept = event.target.closest('.LanguagePromptAccept');
            const decline = event.target.closest('.LanguagePromptDecline');

            if (!accept && !decline) {
                return;
            }

            const prompt = event.target.closest('.LanguagePrompt');

            if (accept) {
                await LanguagePrompt.choose(prompt.dataset.locale);

                return;
            }

            // Declining keeps the language they are already reading, which is
            // still a choice - it is what stops the asking. Nothing on the page
            // changes but the offer, so the offer is what goes: fetching the
            // same page again to remove one card is a round trip for nothing.
            if (await LanguagePrompt.remember(document.documentElement.lang)) {
                prompt.remove();
            }
        });

        document.addEventListener('change', async (event) => {
            const select = event.target.closest('.LanguageSelect');

            if (select) {
                await LanguagePrompt.choose(select.value);
            }
        });
    }

    /** Tells the server, and says whether it took. */
    static async remember(locale) {
        if (!locale) {
            return false;
        }

        return await Api.post('/api/set-language', { locale }) !== null;
    }

    static async choose(locale) {
        if (await LanguagePrompt.remember(locale)) {
            window.location.reload();
        }
    }
}

ReadyHandler.add(LanguagePrompt.init);
