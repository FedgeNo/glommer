import { TestCase } from './TestCase.js';
import { PachinkoMachine, PachinkoScene } from '../../games/pachinko.js';
import { Api } from '../../scripts/Runtime.js';
import { execFileSync } from 'node:child_process';

const markup = execFileSync('php', ['-r', `
spl_autoload_register(function ($class) { require getcwd() . '/src/classes/' . $class . '.php'; });
require getcwd() . '/src/functions.php';
(new ReflectionProperty(HTMLObject::class, 'document'))->setValue(null, new DOMDocument());
$root = (new PachinkoGame())->toDOM(); echo $root->ownerDocument->saveHTML($root);
`], {encoding: 'utf8'});

function machine(stored = new Map()) {
    const parsed = new window.DOMParser().parseFromString(markup, 'text/html');
    const root = document.importNode(parsed.body.firstElementChild, true);
    const wallet = {data: {balance: 1000}, listeners: new Set(), pause() {}, resume() {}, update(data) { this.data = data; }};
    const drops = [], celebrations = [];
    const scene = {reduced: {matches: true}, excite() {}, async drop(path) { drops.push(path); }, celebrate(ratio) { celebrations.push(ratio); }, dispose() {}};
    const game = new PachinkoMachine(root, {wallet, scene, storage: {
        getItem: key => stored.get(key) || null, setItem: (key, value) => stored.set(key, value), removeItem: key => stored.delete(key),
    }});
    return {root, game, wallet, scene, stored, drops, celebrations};
}
function response(request, path = Array(12).fill(0)) {
    const pocket = path.reduce((sum, direction) => sum + direction, 0);
    const returns = [500,150,50,20,10,5,2,5,10,20,50,150,500];
    const payout = request.stake / 10 * returns[pocket];
    return {wallet: {balance: 1000 - request.stake + payout}, round: {requestKey: request.requestKey, path, pocket,
        wager: request.stake, payout, net: payout - request.stake, multiplier: returns[pocket] / 10, wins: []}};
}

export default {
    suite: 'Pachinko',
    tests: {
        'every visual path reaches the same pocket as the server'() {
            for (let bits = 0; bits < 4096; bits++) {
                const path = Array.from({length: 12}, (_, row) => bits >> row & 1);
                const points = PachinkoScene.pathPoints(path);
                TestCase.assertEquals(14, points.length);
                TestCase.assertEquals(390 + (path.reduce((sum, direction) => sum + direction, 0) - 6) * 50, points.at(-1).x);
                for (let row = 1; row < 12; row++) TestCase.assertEquals(path[row - 1] ? 25 : -25, points[row + 1].x - points[row].x);
            }
        },
        async 'lost response recovers the same ball and credits only the server receipt'() {
            const {game, root, wallet, stored, drops, celebrations} = machine(); const original = Api.request; const requests = [];
            Api.request = async (url, request) => {
                TestCase.assertEquals('/api/pachinko', url); requests.push({...request});
                return requests.length === 1 ? {ok: false, status: 0} : {ok: true, data: {response: response(request)}};
            };
            try {
                await game.spin(new AbortController().signal);
                TestCase.assertTrue(stored.has('glommer.pachinko.pending'));
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(requests[0].requestKey, requests[1].requestKey);
                TestCase.assertEquals(1980, wallet.data.balance);
                TestCase.assertEquals(1, drops.length); TestCase.assertEquals(50, celebrations[0]);
                TestCase.assertEquals('0', root.querySelector('.PachinkoPocket.Landed').dataset.pocket);
                TestCase.assertEquals('JACKPOT · 50×', root.querySelector('.VLTWinTitle').textContent);
                TestCase.assertEquals(0, stored.size);
            } finally { Api.request = original; }
        },
        async 'a central pocket shows a net loss without a win celebration'() {
            const {game, root, celebrations, wallet} = machine(); const original = Api.request;
            Api.request = async (_, request) => ({ok: true, data: {response: response(request, [0,1,0,1,0,1,0,1,0,1,0,1])}});
            try {
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(984, wallet.data.balance); TestCase.assertEquals(0, celebrations.length);
                TestCase.assertTrue(root.querySelector('.VLTResult').textContent.includes('−16 net'));
                TestCase.assertEquals('6', root.querySelector('.PachinkoPocket.Landed').dataset.pocket);
            } finally { Api.request = original; }
        },
        async 'cancelling animation preserves the recovery key and leaves wallet updates pending'() {
            const {game, wallet, stored, scene} = machine(); const original = Api.request; const controller = new AbortController();
            Api.request = async (_, request) => ({ok: true, data: {response: response(request)}});
            scene.drop = async () => controller.abort();
            try {
                await game.spin(controller.signal);
                TestCase.assertEquals(1000, wallet.data.balance); TestCase.assertTrue(stored.has('glommer.pachinko.pending'));
                const recovered = machine(stored);
                TestCase.assertEquals(game.pending.requestKey, recovered.game.pending.requestKey);
                TestCase.assertFalse(recovered.root.querySelector('.VLTSpin').disabled);
            } finally { Api.request = original; }
        },
        'inconsistent paths payouts and pockets cannot clear a receipt'() {
            const {game} = machine(); const round = response({stake: 20, requestKey: 'a'.repeat(32)}).round;
            TestCase.assertTrue(game.validRound(round));
            TestCase.assertFalse(game.validRound({...round, path: [0]}));
            TestCase.assertFalse(game.validRound({...round, pocket: 12}));
            TestCase.assertFalse(game.validRound({...round, payout: 99999}));
            TestCase.assertFalse(game.validRound({...round, path: Array(12).fill('0')}));
        },
        async 'fallback appears only on failure and reduced motion lands immediately'() {
            const {root} = machine();
            class QuietScene extends PachinkoScene { async initialize() {} }
            const scene = new QuietScene(root.querySelector('.PachinkoBoard')); await scene.ready;
            scene.reduced = {matches: true, removeEventListener() {}};
            try {
                TestCase.assertEquals('none', scene.picture.style.display);
                scene.fallback(); TestCase.assertEquals('block', scene.picture.style.display);
                await scene.drop(Array(12).fill(1), new AbortController().signal);
                TestCase.assertEquals('690', scene.fallbackBall.getAttribute('cx'));
                TestCase.assertEquals('652', scene.fallbackBall.getAttribute('cy'));
                TestCase.assertEquals(null, root.querySelector('.PachinkoLoading'));
            } finally { scene.dispose(); }
        },
        async 'animated bounces hit twelve pegs then settle and cancellation stops the drop'() {
            const {root} = machine();
            class QuietScene extends PachinkoScene { async initialize() {} }
            const scene = new QuietScene(root.querySelector('.PachinkoBoard')); await scene.ready;
            scene.reduced = {matches: false, removeEventListener() {}}; scene.fallback();
            const original = window.requestAnimationFrame; let frame;
            window.requestAnimationFrame = callback => { frame = callback; return 1; };
            try {
                const impacts = [];
                const dropping = scene.drop(Array(12).fill(0), new AbortController().signal, count => impacts.push(count));
                for (let time = 0; time <= 4000; time += 50) { const next = frame; frame = null; next?.(time); }
                await dropping;
                TestCase.assertEquals(JSON.stringify(Array.from({length: 12}, (_, i) => i + 1)), JSON.stringify(impacts));
                TestCase.assertEquals('90', scene.fallbackBall.getAttribute('cx'));
                TestCase.assertEquals('652', scene.fallbackBall.getAttribute('cy'));
                const controller = new AbortController();
                const cancelled = scene.drop(Array(12).fill(1), controller.signal);
                controller.abort(); await cancelled;
                TestCase.assertEquals('90', scene.fallbackBall.getAttribute('cx'));
            } finally { window.requestAnimationFrame = original; scene.dispose(); }
        },
    },
};
