import { TestCase } from './TestCase.js';
import { LanguagePrompt } from '../../scripts/LanguagePrompt.js';

/** Runs `body` with fetch replaced, collecting what was posted where. */
async function withFetch(body) {
    const real_fetch = globalThis.fetch;
    const posted = [];

    globalThis.fetch = async (url, options) => {
        posted.push({ url: String(url), body: JSON.parse(options.body) });

        return { ok: true, status: 200, json: async () => ({ response: { locale: 'es' } }) };
    };

    try {
        await body(posted);
    } finally {
        globalThis.fetch = real_fetch;
    }
}

function promptFor(offered) {
    const prompt = document.createElement('div');
    prompt.className = 'LanguagePrompt';
    prompt.dataset.locale = offered;

    const decline = document.createElement('button');
    decline.className = 'LanguagePromptDecline';
    prompt.appendChild(decline);

    document.body.appendChild(prompt);

    return { prompt, decline };
}

export default {
    suite: 'LanguagePrompt',
    tests: {
        /**
         * Declining is an answer, and it has to reach the server - an offer
         * that records nothing is an offer made again on the next page.
         */
        async 'declining records the language already being read and takes the offer away'() {
            document.documentElement.lang = 'en';

            await withFetch(async (posted) => {
                const { prompt, decline } = promptFor('es');

                decline.click();
                await new Promise(resolve => setTimeout(resolve, 0));

                TestCase.assertEquals(1, posted.length);
                TestCase.assertEquals('en', posted[0].body.locale);
                TestCase.assertNull(prompt.parentNode, 'the offer is gone without fetching the page again');
            });
        },

        /** Nothing is sent when there is no language to send. */
        async 'a page that declares no language asks the server for nothing'() {
            document.documentElement.lang = '';

            await withFetch(async (posted) => {
                TestCase.assertFalse(await LanguagePrompt.remember(''));
                TestCase.assertEquals(0, posted.length);
            });
        },
    }
};
