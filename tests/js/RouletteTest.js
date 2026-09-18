import { TestCase } from './TestCase.js';
import { RouletteTable, RouletteScene } from '../../games/roulette.js';
import { Api } from '../../scripts/Runtime.js';
import { execFileSync } from 'node:child_process';

// Exercise the actual PHP betting controls, not a second hand-built table.
const markup = execFileSync('php', ['-r', `
spl_autoload_register(function ($class) { require getcwd() . '/src/classes/' . $class . '.php'; });
require getcwd() . '/src/functions.php';
(new ReflectionProperty(HTMLObject::class, 'document'))->setValue(null, new DOMDocument());
$root = (new RouletteGame())->toDOM(); echo $root->ownerDocument->saveHTML($root);
`], { encoding: 'utf8' });

function table() {
    const parsed = new window.DOMParser().parseFromString(markup, 'text/html');
    const root = document.importNode(parsed.body.firstElementChild, true);
    const storage = new Map();
    const wallet = { data: { balance: 1000 }, listeners: new Set(), pause() {}, resume() {}, update(data) { this.data = data; } };
    const landed = [];
    const scene = { spin: async number => landed.push(number), reduced: { matches: true } };
    const game = new RouletteTable(root, { wallet, scene, storage: {
        getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key),
    } });
    return { game, root, wallet, landed, storage };
}

export default {
    suite: 'Roulette',
    tests: {
        async 'fallback stays hidden while loading and appears only when rendering fails'() {
            const {root} = table();
            const element = root.querySelector('[data-scene]');
            const fallback = element.querySelector('.wheel-fallback');
            TestCase.assertTrue(fallback.hidden);
            let fail;
            class LoadingScene extends RouletteScene {
                initialize() { return new Promise((resolve, reject) => { fail = reject; }); }
            }
            const scene = new LoadingScene(element, JSON.parse(root.dataset.wheel));
            TestCase.assertTrue(fallback.hidden);
            fail(new Error('WebGL unavailable'));
            await scene.ready;
            TestCase.assertFalse(fallback.hidden);
            scene.dispose();
        },
        'chip placement respects limits and removal and clear work with touch buttons'() {
            const { game, root } = table();
            root.querySelector('[data-bet="red"]').click();
            TestCase.assertEquals(25, game.total());
            root.querySelector('[data-chip="500"]').click();
            root.querySelector('[data-bet="black"]').click();
            root.querySelector('[data-bet="black"]').click();
            TestCase.assertEquals(525, game.total());
            root.querySelector('[data-remove="red"]').click();
            TestCase.assertEquals(500, game.total());
            root.querySelector('[data-clear]').click();
            TestCase.assertEquals(0, game.total());
            TestCase.assertTrue(root.querySelector('[data-spin]').disabled);
        },
        async 'lost responses keep the same key and block edits until recovery'() {
            const { game, root, landed, storage, wallet } = table();
            const original = Api.request;
            const requests = [];
            Api.request = async (path, payload) => {
                requests.push(structuredClone(payload));
                if (requests.length === 1) return { ok: false, status: 0 };
                return { ok: true, data: { response: { wallet: { balance: 1025 }, round: {
                    requestKey: payload.requestKey, number: 7, color: 'red', net: 25, payout: 50,
                } } } };
            };
            try {
                game.add('red');
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(1, storage.size);
                TestCase.assertTrue(root.querySelector('[data-bet="red"]').disabled);
                TestCase.assertEquals('RECOVER SPIN', root.querySelector('[data-spin]').textContent);
                await game.spin(new AbortController().signal);
                TestCase.assertEquals(requests[0].requestKey, requests[1].requestKey);
                TestCase.assertEquals(25, requests[1].bets.red);
                TestCase.assertEquals(7, landed[0]);
                TestCase.assertEquals(1025, wallet.data.balance);
                TestCase.assertEquals(0, storage.size);
                TestCase.assertFalse(root.querySelector('[data-bet="red"]').disabled);
            } finally { Api.request = original; }
        },
        async 'explicit rejected bets unlock editing and do not animate'() {
            const { game, landed, storage } = table();
            const original = Api.request;
            Api.request = async () => ({ ok: false, status: 409, data: { error: 'Not enough chips' }, error: 'Not enough chips' });
            try {
                game.add('red'); await game.spin(new AbortController().signal);
                TestCase.assertNull(game.pending);
                TestCase.assertEquals(0, landed.length);
                TestCase.assertEquals(0, storage.size);
            } finally { Api.request = original; }
        },
        async 'cancellation preserves the receipt key and discards late UI updates'() {
            const { game, landed, storage, wallet } = table();
            const original = Api.request;
            const controller = new AbortController();
            Api.request = async () => { controller.abort(); return { ok: true }; };
            try {
                game.add('red'); await game.spin(controller.signal);
                TestCase.assertEquals(1, storage.size);
                TestCase.assertEquals(0, landed.length);
                TestCase.assertEquals(1000, wallet.data.balance);
            } finally { Api.request = original; }
        },
        'every animated endpoint lies in the server-selected pocket'() {
            const { root } = table();
            const pockets = JSON.parse(root.dataset.wheel);
            for (const rotation of [0, 1.1, 20, 1000]) for (const number of pockets) {
                const angle = RouletteScene.pocketAngle(pockets, number, rotation);
                const index = Math.floor((angle - rotation) / (2 * Math.PI) * 37);
                TestCase.assertEquals(number, pockets[index]);
            }
        },
    },
};
