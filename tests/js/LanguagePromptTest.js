import { TestCase } from './TestCase.js';
import { LanguagePrompt } from '../../scripts/Controllers.js';

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

/** What the server renders for somebody whose browser asks for another language. */
function offerOf(locale) {
    const offer = document.createElement('div');
    offer.className = 'LanguagePrompt';
    offer.dataset.locale = locale;

    const question = document.createElement('p');
    question.className = 'LanguagePromptQuestion';
    question.textContent = '¿Prefieres leer este sitio en español?';
    offer.appendChild(question);

    const accept = document.createElement('button');
    accept.className = 'Button LanguagePromptAccept';
    accept.textContent = 'Sí';
    offer.appendChild(accept);

    const decline = document.createElement('button');
    decline.className = 'Button LanguagePromptDecline';
    decline.textContent = 'No, thanks';
    offer.appendChild(decline);

    document.body.appendChild(offer);

    return offer;
}

/** The dialog the offer becomes, once it has been asked. */
function dialog() {
    return document.querySelector('.ConfirmDialogCard');
}

export default {
    suite: 'LanguagePrompt',
    tests: {
        /**
         * Each answer is written in the language it leads to, so the words on
         * the button are the choice rather than a label for it.
         */
        async 'the offer is asked as a dialog in the language being offered'() {
            await withFetch(async () => {
                const offer = offerOf('es');

                LanguagePrompt.ask(offer);
                await new Promise(resolve => setTimeout(resolve, 0));

                const card = dialog();

                TestCase.assertNotNull(card, 'a dialog was opened');
                TestCase.assertEquals('dialog', card.getAttribute('role'));
                TestCase.assertEquals('true', card.getAttribute('aria-modal'));
                TestCase.assertTrue(card.textContent.includes('¿Prefieres leer este sitio en español?'));
                TestCase.assertEquals('Sí', card.querySelector('.ConfirmDialogConfirmButton').textContent);
                TestCase.assertEquals('No, thanks', card.querySelector('.ConfirmDialogCancelButton').textContent);
                TestCase.assertNull(offer.parentNode, 'the words are not left on the page as well');

                card.querySelector('.ConfirmDialogCancelButton').click();
                await new Promise(resolve => setTimeout(resolve, 0));
            });
        },

        /**
         * Declining is an answer, and it has to reach the server - an offer
         * that records nothing is an offer made again on the next page.
         */
        async 'declining records the language already being read'() {
            document.documentElement.lang = 'en';

            await withFetch(async (posted) => {
                LanguagePrompt.ask(offerOf('es'));
                await new Promise(resolve => setTimeout(resolve, 0));

                dialog().querySelector('.ConfirmDialogCancelButton').click();
                await new Promise(resolve => setTimeout(resolve, 0));

                TestCase.assertEquals(1, posted.length);
                TestCase.assertEquals('en', posted[0].body.locale);
                TestCase.assertNull(dialog(), 'and the dialog is closed');
            });
        },

        /** Nothing is sent when there is no language to send. */
        async 'a page that declares no language asks the server for nothing'() {
            await withFetch(async (posted) => {
                TestCase.assertFalse(await LanguagePrompt.remember(''));
                TestCase.assertEquals(0, posted.length);
            });
        },
    }
};
