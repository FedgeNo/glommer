import { TestCase } from './TestCase.js';
import { Api, Strings } from '../../scripts/Runtime.js';
import { Dialog } from '../../scripts/HTMLObjects.js';
import { MessageTranslateButton } from '../../scripts/Controllers.js';

function receivedMessage() {
    const message = document.createElement('div');
    message.className = 'Message Encrypted';
    const body = document.createElement('div');
    body.className = 'MessageBody';
    body.textContent = 'A private received message';
    const button = document.createElement('button');
    button.className = 'MessageTranslateButton';
    button.dataset.messageId = '42';
    message.append(body, button);
    document.body.replaceChildren(message);
    return button;
}

const settle = () => new Promise(resolve => setTimeout(resolve, 0));

export default {
    suite: 'Message translation notice',
    tests: {
        async 'the old local-only notice cannot authorize external translation'() {
            const confirm = Dialog.confirm;
            const post = Api.post;
            const button = receivedMessage();
            let shown = null;
            let requests = 0;
            localStorage.setItem('translation-notice-read', '1');
            localStorage.removeItem(MessageTranslateButton.NOTICE_KEY);
            Dialog.confirm = async text => { shown = text; return false; };
            Api.post = async () => { requests++; return null; };
            try {
                button.click();
                await settle();
                TestCase.assertEquals(Strings.for('MessageTranslationNotice').body, shown);
                TestCase.assertTrue(shown.includes('Google'));
                TestCase.assertEquals(0, requests);
                TestCase.assertEquals(null, localStorage.getItem(MessageTranslateButton.NOTICE_KEY));
            } finally {
                Dialog.confirm = confirm;
                Api.post = post;
                localStorage.removeItem('translation-notice-read');
                document.body.replaceChildren();
            }
        },
        async 'accepting the updated notice allows only requested messages to be sent'() {
            const confirm = Dialog.confirm;
            const post = Api.post;
            const button = receivedMessage();
            let notices = 0;
            const requests = [];
            localStorage.removeItem(MessageTranslateButton.NOTICE_KEY);
            Dialog.confirm = async () => { notices++; return true; };
            Api.post = async (url, payload) => { requests.push({ url, payload }); return null; };
            try {
                TestCase.assertEquals(0, requests.length);
                button.click();
                await settle();
                TestCase.assertEquals(1, notices);
                TestCase.assertEquals(1, requests.length);
                TestCase.assertEquals('/api/translate-message', requests[0].url);
                TestCase.assertEquals('A private received message', requests[0].payload.text);
                TestCase.assertEquals('1', localStorage.getItem(MessageTranslateButton.NOTICE_KEY));
                button.click();
                await settle();
                TestCase.assertEquals(1, notices);
                TestCase.assertEquals(2, requests.length);
            } finally {
                Dialog.confirm = confirm;
                Api.post = post;
                localStorage.removeItem(MessageTranslateButton.NOTICE_KEY);
                document.body.replaceChildren();
            }
        },
    },
};
