import { TestCase } from './TestCase.js';
import { CasinoWallet } from '../../games/casino.js';
import { Api } from '../../scripts/Runtime.js';

function wallet() {
    const root = document.createElement('div');
    for (const name of ['balance', 'guest-message', 'wallet-status', 'refill']) {
        const node = document.createElement('span'); node.setAttribute('data-' + name, ''); root.append(node);
    }
    return new CasinoWallet(root);
}

export default {
    suite: 'CasinoWallet',
    tests: {
        async 'leaving during a request prevents late updates and rescheduling'() {
            const original = Api.request;
            let resolve;
            Api.request = () => new Promise(done => { resolve = done; });
            const chips = wallet();
            try {
                const running = chips.tick();
                chips.stop();
                resolve({ ok: true, data: { response: { balance: 1000, guest: true, serverTime: 100, nextGrantAt: 3700 } } });
                await running;
                TestCase.assertNull(chips.data);
                TestCase.assertNull(chips.timer);
            } finally { chips.stop(); Api.request = original; }
        },
        async 'overlapping heartbeats issue one request and failures are paced'() {
            const original = Api.request;
            let resolve, calls = 0;
            Api.request = () => { calls++; return new Promise(done => { resolve = done; }); };
            const chips = wallet();
            try {
                const running = chips.tick();
                await chips.tick();
                TestCase.assertEquals(1, calls);
                resolve({ ok: false, error: 'Offline' });
                await running;
                await chips.tick();
                TestCase.assertEquals(1, calls);
            } finally { chips.stop(); Api.request = original; }
        },
        async 'hidden rooms never request hourly chips'() {
            const original = Api.request;
            const own = Object.getOwnPropertyDescriptor(document, 'hidden');
            let calls = 0;
            Api.request = async () => { calls++; return { ok: false }; };
            const chips = wallet();
            try {
                Object.defineProperty(document, 'hidden', { configurable: true, value: true });
                await chips.tick();
                TestCase.assertEquals(0, calls);
                TestCase.assertNull(chips.timer);
            } finally {
                chips.stop(); Api.request = original;
                if (own) Object.defineProperty(document, 'hidden', own); else delete document.hidden;
            }
        },
    },
};
