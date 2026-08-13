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

            // Declining keeps the language they are already reading, which is
            // still a choice - it is what stops the asking.
            await LanguagePrompt.choose(accept ? prompt.dataset.locale : document.documentElement.lang);
        });

        document.addEventListener('change', async (event) => {
            const select = event.target.closest('.LanguageSelect');

            if (select) {
                await LanguagePrompt.choose(select.value);
            }
        });
    }

    static async choose(locale) {
        if (!locale) {
            return;
        }

        const result = await Api.post('/api/set-language', { locale });

        if (result) {
            window.location.reload();
        }
    }
}

ReadyHandler.add(LanguagePrompt.init);
