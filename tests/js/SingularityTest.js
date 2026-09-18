import { TestCase } from './TestCase.js';
import { SingularityMachine, SingularitySymbol } from '../../games/singularity.js';
import { Api } from '../../scripts/Runtime.js';
import { execFileSync } from 'node:child_process';

const markup = execFileSync('php', ['-r', `
spl_autoload_register(function ($class) { require getcwd() . '/src/classes/' . $class . '.php'; });
require getcwd() . '/src/functions.php';
(new ReflectionProperty(HTMLObject::class, 'document'))->setValue(null, new DOMDocument());
$root = (new SingularityGame())->toDOM(); echo $root->ownerDocument->saveHTML($root);
`], {encoding: 'utf8'});

function machine() {
    const parsed = new window.DOMParser().parseFromString(markup, 'text/html');
    const root = document.importNode(parsed.body.firstElementChild, true);
    const stored = new Map([['glommer.vlt.pending', JSON.stringify({stake: 500, requestKey: 'a'.repeat(32)})]]);
    const wallet = {data: {balance: 1000}, listeners: new Set(), pause() {}, resume() {}, update(data) { this.data = data; }};
    const celebrations = [];
    const scene = {excite() {}, celebrate(value) { celebrations.push(value); }, dispose() {}};
    const game = new SingularityMachine(root, {wallet, scene, storage: {
        getItem: key => stored.get(key) || null, setItem: (key, value) => stored.set(key, value), removeItem: key => stored.delete(key),
    }});
    game.reels.reduced = {matches: true};
    return {game, root, stored, wallet, celebrations};
}
function response(key, payout = 1000) {
    return {round: {requestKey: key, grid: [[0,1,2],[0,1,2],[0,1,2],[3,4,5],[3,4,5]],
        wins: [{line: 1, count: 3, symbol: 0, payout}], wager: 20, payout, net: payout - 20, multiplier: 5}, wallet: {balance: 980 + payout}};
}

export default {
    suite: 'Singularity',
    tests: {
        'reactor owns its symbols labels and recovery key without consuming a Nova spin'() {
            const {game, root, stored} = machine();
            TestCase.assertEquals(null, game.pending);
            TestCase.assertEquals('glommer.singularity.pending', game.storageKey);
            TestCase.assertEquals('IGNITE THE REACTOR', root.querySelector('.VLTSpin').textContent);
            TestCase.assertEquals(25, root.querySelectorAll('.SingularitySymbol').length);
            TestCase.assertTrue(stored.has('glommer.vlt.pending'));
            const symbol = new SingularitySymbol(4, game.symbols).toDOM();
            TestCase.assertEquals('Reactor', symbol.getAttribute('aria-label'));
            TestCase.assertEquals(3, symbol.querySelectorAll('g > path').length);
        },
        async 'cluster cycle animates every cascade and credits the combined return'() {
            const {game, root, wallet} = machine(); const original = Api.request;
            const grid = Array.from({length: 5}, (_, x) => Array.from({length: 5}, (_, y) => (x + y) % 6));
            const cluster = {symbol: 0, count: 5, cells: [[0,0],[0,1],[0,2],[0,3],[0,4]], payout: 44};
            const first = grid.map(column => [...column]); first[0] = [0,0,0,0,0];
            const second = grid.map(column => [...column]); second[0] = [0,0,0,0,0];
            const steps = [{grid: first, multiplier: 1, clusters: [cluster], payout: 44}, {grid: second, multiplier: 2, clusters: [{...cluster, payout: 88}], payout: 88}];
            Api.request = async (_, request) => ({ok: true, data: {response: {wallet: {balance: 1112}, round: {
                version: 2, requestKey: request.requestKey, wager: 20, payout: 132, net: 112, multiplier: 2, grid, cascades: steps, wins: [],
            }}}});
            try {
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(1112, wallet.data.balance);
                TestCase.assertEquals(2, root.querySelectorAll('.SingularityCascadeReceipt').length);
                TestCase.assertEquals('OVERDRIVE · 2×', root.querySelector('.VLTPower').textContent);
                TestCase.assertEquals(25, root.querySelectorAll('.SingularitySymbol').length);
                TestCase.assertEquals(0, root.querySelectorAll('.Winning').length);
                TestCase.assertEquals(null, game.pending);
                TestCase.assertFalse(game.validRound({version: 2, grid, cascades: [{...steps[0], multiplier: 3}], multiplier: 1, payout: 44}));
            } finally { Api.request = original; }
        },
        async 'aborting a cascade keeps its recovery key and does not credit early'() {
            const {game, stored, wallet} = machine(); const original = Api.request;
            const controller = new AbortController();
            const grid = Array.from({length: 5}, () => [0,1,2,3,4]);
            Api.request = async (_, request) => ({ok: true, data: {response: {wallet: {balance: 980}, round: {version: 2,
                requestKey: request.requestKey, wager: 20, payout: 0, net: -20, multiplier: 1, grid, cascades: [], wins: []}}}});
            game.reels.drop = async () => controller.abort();
            try {
                await game.spin(controller.signal);
                TestCase.assertTrue(stored.has('glommer.singularity.pending'));
                TestCase.assertEquals(1000, wallet.data.balance);
            } finally { Api.request = original; }
        },
        async 'gravity animates surviving symbols from their old rows and cancellation clears effects'() {
            const {game, root} = machine(); game.reels.reduced = {matches: false};
            const grid = Array.from({length: 5}, () => [5,5,0,2,4]);
            let moves = [];
            game.reels.motion = async entries => { moves = entries; };
            await game.reels.drop(grid, [{cells: [[0,1],[0,3]]}], new AbortController().signal);
            const first = root.querySelector('.VLTReel');
            TestCase.assertEquals('translateY(-200%)', moves.find(([element]) => element === first.children[2])[1][0].transform);
            TestCase.assertEquals('translateY(-100%)', moves.find(([element]) => element === first.children[3])[1][0].transform);
            TestCase.assertFalse(moves.some(([element]) => element === first.children[4]));
            const controller = new AbortController();
            game.reels.motion = async () => controller.abort();
            await game.reels.explode([{cells: [[0,0],[0,1],[0,2],[0,3],[0,4]]}], controller.signal);
            TestCase.assertEquals(0, root.querySelectorAll('.Winning').length);
        },
        async 'reactor recovers its own endpoint and preserves Nova recovery while crediting once'() {
            const {game, root, stored, wallet} = machine(); const original = Api.request; const requests = [];
            Api.request = async (url, payload) => {
                TestCase.assertEquals('/api/singularity', url); requests.push(structuredClone(payload));
                return requests.length === 1 ? {ok: false, status: 0} : {ok: true, data: {response: response(payload.requestKey)}};
            };
            try {
                await game.spin(new AbortController().signal);
                TestCase.assertTrue(stored.has('glommer.singularity.pending'));
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(requests[0].requestKey, requests[1].requestKey);
                TestCase.assertFalse(stored.has('glommer.singularity.pending')); TestCase.assertTrue(stored.has('glommer.vlt.pending'));
                TestCase.assertEquals(1980, wallet.data.balance);
                TestCase.assertEquals('REALITY RUPTURE', root.querySelector('.VLTWinTitle').textContent);
                TestCase.assertEquals('OVERDRIVE · 5×', root.querySelector('.VLTPower').textContent);
                TestCase.assertEquals(0, root.querySelectorAll('.SingularityShard').length);
            } finally { Api.request = original; }
        },
        'big wins produce a bounded eruption and cleanup removes it without changing chips'() {
            const {game, root, wallet} = machine();
            game.reels.reduced = {matches: false};
            const original = window.requestAnimationFrame; window.requestAnimationFrame = () => 1;
            try {
                game.result(response('b'.repeat(32)).round);
                TestCase.assertEquals(70, root.querySelectorAll('.SingularityShard').length);
                TestCase.assertEquals(1, root.querySelectorAll('.SingularityScreenWave').length);
                root.querySelector('.SingularityShard').dispatchEvent(new window.Event('animationend'));
                TestCase.assertEquals(69, root.querySelectorAll('.SingularityShard').length);
                game.clearEffects(); TestCase.assertEquals(0, root.querySelectorAll('.SingularityShard').length);
                TestCase.assertEquals(1000, wallet.data.balance);
                game.result(response('c'.repeat(32), 10).round);
                TestCase.assertEquals(0, root.querySelectorAll('.SingularityShard').length);
            } finally { window.requestAnimationFrame = original; }
        },
    },
};
