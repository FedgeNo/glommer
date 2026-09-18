import { TestCase } from './TestCase.js';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function runtime() {
    const stored = new Map();
    const state = {cash: 1000, upgrades: {car: {engine: 0}}, raceFormat: 'standard'};
    const context = vm.createContext({
        Game: state, Economy: {format: value => value.toLocaleString() + ' chips'}, crypto: globalThis.crypto,
        sessionStorage: {setItem: (k, v) => stored.set(k, v), removeItem: k => stored.delete(k)},
        document: {body: {setAttribute() {}, removeAttribute() {}}, querySelectorAll: () => []},
        setTimeout: () => 1, clearTimeout() {},
    });
    const source = readFileSync(new URL('../../games/derby.js', import.meta.url), 'utf8');
    vm.runInContext(source.replace('new GlommerDerbyAdapter();', ''), context);
    const adapter = vm.runInContext('Object.create(GlommerDerbyAdapter.prototype)', context);
    Object.assign(adapter, {ready: true, busy: false, stopped: false, pending: null, pendingKey: 'pending', status: {}, retry: {}, race: 'a'.repeat(32)});
    adapter.apply = data => { state.cash = data.wallet.balance; };
    return {adapter, state, stored, context};
}

export default {
    suite: 'DerbyAdapter',
    tests: {
        'welcome advertisement uses the short banner on desktop and the phone slot on mobile'() {
            const {adapter, context} = runtime(); context.document = document;
            const screen = document.createElement('div'); context.$ = () => screen;
            let wide = true; let update;
            context.matchMedia = () => ({get matches() { return wide; }, addEventListener(type, callback) { update = callback; }});
            adapter.advertisement();
            const frame = screen.querySelector('iframe');
            TestCase.assertEquals('', screen.textContent);
            TestCase.assertEquals('728', frame.width); TestCase.assertEquals('90', frame.height);
            TestCase.assertTrue(frame.src.includes('idzone=6032906'));
            wide = false; update();
            TestCase.assertEquals('300', frame.width); TestCase.assertEquals('250', frame.height);
            TestCase.assertTrue(frame.src.includes('idzone=6032898'));
        },
        'the result separates score rewards from a won life and subtracts entry once'() {
            const {adapter, state, context} = runtime(); context.document = document;
            state.eventType = 'standard'; state.lastBreakdown = {freeLife: true, raceLoss: false};
            adapter.settlement = {payout: 765};
            const board = document.createElement('div');
            adapter.resultSummary(board);
            const rows = [...board.querySelectorAll('.line')].map(row => row.textContent);
            TestCase.assertEquals('Entry · 1 life−500 chips', rows[0]);
            TestCase.assertEquals('Race, combat and drift+265 chips', rows[1]);
            TestCase.assertEquals('Life won+500 chips', rows[2]);
            TestCase.assertEquals('Total winnings+765 chips', rows[3]);
            TestCase.assertEquals('Net after entry+265 chips', rows[4]);
            adapter.resultSummary(board);
            TestCase.assertEquals(1, board.querySelectorAll('.glommer-derby-result').length);
        },
        'unconfirmed winnings are not presented as credited chips'() {
            const {adapter, state, context} = runtime(); context.document = document;
            state.eventType = 'standard'; state.lastBreakdown = {freeLife: true, raceLoss: false};
            const board = document.createElement('div'); adapter.resultSummary(board);
            TestCase.assertTrue(board.textContent.includes('Awaiting confirmation'));
            TestCase.assertFalse(board.textContent.includes('Net after entry'));
        },
        async 'an uncertain transaction retains its key and calls back only after recovery'() {
            const {adapter, stored} = runtime();
            let called = 0; const keys = [];
            adapter.request = async payload => { keys.push(payload.requestKey); throw new Error('network lost'); };
            adapter.transact({type: 'race'}, () => called++);
            await new Promise(resolve => setTimeout(resolve, 0));
            TestCase.assertEquals(0, called);
            TestCase.assertTrue(stored.has('pending'));
            adapter.request = async payload => { keys.push(payload.requestKey); return {wallet: {balance: 500}}; };
            await adapter.send();
            TestCase.assertEquals(keys[0], keys[1]);
            TestCase.assertEquals(1, called);
            TestCase.assertEquals(null, adapter.pending);
            TestCase.assertFalse(stored.has('pending'));
        },
        async 'a refused charge does not authorize play and clears the pending transaction'() {
            const {adapter, stored} = runtime(); let accepted;
            adapter.request = async () => { const error = new Error('Not enough chips'); error.definitive = true; throw error; };
            adapter.transact({type: 'race'}, value => { accepted = value; });
            await new Promise(resolve => setTimeout(resolve, 0));
            TestCase.assertEquals(false, accepted);
            TestCase.assertEquals(null, adapter.pending);
            TestCase.assertFalse(stored.has('pending'));
        },
        'a reward waits behind a wallet refresh rather than being discarded'() {
            const {adapter} = runtime(); adapter.busy = true;
            const action = {type: 'reward', amount: 10}; const done = () => {};
            adapter.transact(action, done);
            TestCase.assertEquals(action, adapter.queued.action);
            TestCase.assertEquals(done, adapter.queued.done);
        },
    },
};
