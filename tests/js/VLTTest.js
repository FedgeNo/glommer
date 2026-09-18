import { TestCase } from './TestCase.js';
import { VLTMachine, VLTReels } from '../../games/vlt.js';
import { Api } from '../../scripts/Runtime.js';
import { FormForm } from '../../scripts/HTMLObjects.js';
import { execFileSync } from 'node:child_process';

const markup = execFileSync('php', ['-r', `
spl_autoload_register(function ($class) { require getcwd() . '/src/classes/' . $class . '.php'; });
require getcwd() . '/src/functions.php';
(new ReflectionProperty(HTMLObject::class, 'document'))->setValue(null, new DOMDocument());
$root = (new VLTGame())->toDOM(); echo $root->ownerDocument->saveHTML($root);
`], { encoding: 'utf8' });

function machine(saved = []) {
    const parsed = new window.DOMParser().parseFromString(markup, 'text/html');
    const root = document.importNode(parsed.body.firstElementChild, true);
    const storage = new Map(saved);
    const wallet = { data: { balance: 1000 }, listeners: new Set(), pause() {}, resume() {}, update(data) { this.data = data; } };
    const celebrations = [];
    const scene = { excite() {}, celebrate(ratio) { celebrations.push(ratio); }, dispose() {} };
    const reels = new VLTReels(root, JSON.parse(root.dataset.symbols), { play() {} }); reels.reduced = { matches: true };
    const game = new VLTMachine(root, { wallet, scene, reels, storage: {
        getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key),
    } });
    return { game, root, wallet, storage, celebrations };
}
function result(key, payout = 40) {
    return { wallet: { balance: 980 + payout }, round: { requestKey: key, wager: 20, payout, net: payout - 20, multiplier: 2,
        grid: [[1,0,2], [2,0,3], [3,0,4], [1,2,3], [2,3,4]], wins: [{line: 0, symbol: 0, count: 3, payout}] } };
}

export default {
    suite: 'VLT',
    tests: {
        'cabinet renders unique jewel art all paylines and touch controls'() {
            const { game, root } = machine();
            TestCase.assertEquals(15, root.querySelectorAll('.VLTSymbol svg').length);
            TestCase.assertEquals(15, new Set(Array.from(root.querySelectorAll('linearGradient'), node => node.id)).size);
            TestCase.assertEquals(10, root.querySelectorAll('.VLTLineGuide figure').length);
            const paths = root.querySelectorAll('.VLTWindow .VLTPaylines polyline');
            TestCase.assertEquals(10, paths.length);
            game.lines.forEach((rows, line) => TestCase.assertEquals(rows.map((row, reel) => `${50 + reel * 100},${50 + row * 100}`).join(' '), paths[line].getAttribute('points')));
            TestCase.assertEquals('20', game.form.elements.stake.value);
            TestCase.assertFalse(root.querySelector('.VLTSpin').disabled);
        },
        async 'lost response recovery uses the same stake and key and displays server symbols'() {
            const { game, root, wallet, storage, celebrations } = machine(); const original = Api.request; const requests = [];
            Api.request = async (url, payload) => {
                requests.push(structuredClone(payload));
                return requests.length === 1 ? { ok: false, status: 0 } : { ok: true, data: {response: result(payload.requestKey)} };
            };
            try {
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(1, storage.size);
                TestCase.assertTrue(game.form.elements.stake.disabled);
                TestCase.assertEquals('RECOVER SPIN', root.querySelector('.VLTSpin').textContent);
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(requests[0].requestKey, requests[1].requestKey);
                TestCase.assertEquals(20, requests[1].stake);
                TestCase.assertEquals(1020, wallet.data.balance);
                TestCase.assertEquals(0, storage.size);
                TestCase.assertEquals(3, root.querySelectorAll('.VLTSymbol.Winning').length);
                TestCase.assertEquals(10, root.querySelectorAll('.VLTPaylines polyline').length);
                TestCase.assertEquals(1, root.querySelectorAll('.VLTPaylines .Matching').length);
                TestCase.assertEquals('40', root.querySelector('.VLTWinAmount').textContent);
                TestCase.assertTrue(root.querySelector('.VLTResult').textContent.includes('+20 net'));
                TestCase.assertEquals(1, celebrations.length);
                root.querySelector('.VLTWins button').click();
                TestCase.assertEquals(1, root.querySelectorAll('.VLTPaths').length);
                TestCase.assertEquals(10, root.querySelectorAll('.VLTPaylines polyline').length);
                TestCase.assertEquals('true', root.querySelector('.VLTWins button').getAttribute('aria-pressed'));
            } finally { Api.request = original; }
        },
        async 'reloading restores a pending spin even when the available balance is lower'() {
            const saved = [[ 'glommer.vlt.pending', JSON.stringify({stake: 250, requestKey: 'b'.repeat(32)}) ]];
            const { game, root, wallet, storage } = machine(saved); const original = Api.request;
            wallet.data.balance = 0; game.controls();
            TestCase.assertEquals('250', game.form.elements.stake.value);
            TestCase.assertFalse(root.querySelector('.VLTSpin').disabled);
            Api.request = async (url, payload) => {
                TestCase.assertEquals('b'.repeat(32), payload.requestKey); TestCase.assertEquals(250, payload.stake);
                const response = result(payload.requestKey, 0); response.round.wager = 250; response.round.net = -250; response.round.wins = []; response.wallet.balance = 750;
                return {ok: true, data: {response}};
            };
            try { await game.spin(new AbortController().signal); TestCase.assertEquals(750, wallet.data.balance); TestCase.assertEquals(0, storage.size); }
            finally { Api.request = original; }
        },
        'a return below the stake is labelled as a net loss without a celebration'() {
            const { game, root, celebrations } = machine();
            game.result(result('a'.repeat(32), 10).round);
            TestCase.assertTrue(root.querySelector('.VLTResult').textContent.includes('−10 net'));
            TestCase.assertEquals('SPIN COMPLETE', root.querySelector('.VLTWinTitle').textContent);
            TestCase.assertFalse(root.querySelector('.VLTCabinet').classList.contains('Celebrating'));
            TestCase.assertEquals(0, celebrations.length);
        },
        async 'pending form submissions cannot duplicate a spin'() {
            const { game, wallet } = machine(); const original = Api.request; let release; let count = 0;
            Api.request = async (url, payload) => { count++; await new Promise(resolve => { release = resolve; }); return {ok: true, data: {response: result(payload.requestKey)}}; };
            try {
                const first = FormForm.run(game.form, (_, {signal}) => game.spin(signal));
                await Promise.resolve();
                const second = FormForm.run(game.form, (_, {signal}) => game.spin(signal));
                release(); await Promise.all([first, second]);
                TestCase.assertEquals(1, count); TestCase.assertEquals(1020, wallet.data.balance);
            } finally { Api.request = original; }
        },
        async 'an explicit refusal unlocks the stake and an aborted response keeps recovery'() {
            const { game, wallet, storage } = machine(); const original = Api.request;
            Api.request = async () => ({ok: false, status: 409, data: {error: 'Not enough chips'}, error: 'Not enough chips'});
            try {
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(0, storage.size); TestCase.assertFalse(game.form.elements.stake.disabled);
                const controller = new AbortController();
                Api.request = async (url, payload) => { controller.abort(); return {ok: true, data: {response: result(payload.requestKey)}}; };
                await game.spin(controller.signal);
                TestCase.assertEquals(1000, wallet.data.balance); TestCase.assertEquals(1, storage.size);
            } finally { Api.request = original; }
        },
        async 'storage failure prevents charging and invalid stakes stay disabled'() {
            const { game, root, storage } = machine(); const original = Api.request; let calls = 0;
            Api.request = async () => { calls++; return {ok: false}; };
            try {
                game.storage.setItem = () => { throw new Error('disabled'); };
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(0, calls); TestCase.assertEquals(0, storage.size);
                TestCase.assertTrue(root.querySelector('.VLTResult').textContent.includes('session storage'));
                game.form.elements.stake.value = '999'; game.controls();
                TestCase.assertTrue(root.querySelector('.VLTSpin').disabled);
            } finally { Api.request = original; }
        },
    },
};
