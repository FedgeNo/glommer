import { TestCase } from './TestCase.js';
import { PokerTable, PokerCard, PokerSound } from '../../games/poker.js';
import { Api } from '../../scripts/Runtime.js';
import { FormForm } from '../../scripts/HTMLObjects.js';
import { execFileSync } from 'node:child_process';

const markup = execFileSync('php', ['-r', `
spl_autoload_register(function ($class) { require getcwd() . '/src/classes/' . $class . '.php'; });
require getcwd() . '/src/functions.php';
(new ReflectionProperty(HTMLObject::class, 'document'))->setValue(null, new DOMDocument());
$root = (new PokerGame(['signedIn' => true]))->toDOM(); echo $root->ownerDocument->saveHTML($root);
`], { encoding: 'utf8' });

function game() {
    const parsed = new window.DOMParser().parseFromString(markup, 'text/html');
    const root = document.importNode(parsed.body.firstElementChild, true);
    const wallet = { start: async () => {}, update() {} };
    const scene = { update() {} };
    const game = new PokerTable(root, { wallet, scene });
    game.data = state(); game.render();
    return { game, root };
}

function state() {
    const seats = Array.from({ length: 9 }, (_, i) => ({ userId: i < 2 ? i + 1 : null,
        name: i < 2 ? '@person' + i : '♠ Copper Fox', image: i < 2 ? '/avatars/' + i + '.webp' : null,
        stack: 1000, bet: 0, total: 0, cards: i === 0 ? [12, 25] : [null, null], action: '', folded: false, won: 0 }));
    return { userId: 1, queued: true, waiting: 0, matchAt: 120, serverTime: 100, wallet: {}, table: {
        tableId: 10, version: 1, seats, turn: 0, dealer: 8, deadline: 125, street: 'preflop', pot: 30, board: [],
        legal: { call: 20, canCheck: false, canRaise: true, minRaiseTo: 40, maxRaiseTo: 1000, allInCall: false }, log: [], chat: [],
    } };
}

export default {
    suite: 'Poker',
    tests: {
        'raise presets include the call and explain additional chips without submitting'() {
            const { game: table, root } = game();
            try {
                table.data.table.pot = 100; table.data.table.seats[0].bet = 10;
                table.preset('half');
                TestCase.assertEquals('90', table.betForm.elements.amount.value);
                TestCase.assertTrue(root.querySelector('.PokerBetCost').textContent.includes('80 more chips'));
                table.preset('pot'); TestCase.assertEquals('150', table.betForm.elements.amount.value);
                table.data.table.legal.maxRaiseTo = 110;
                table.preset('pot'); TestCase.assertEquals('110', table.betForm.elements.amount.value);
                table.preset('minimum'); TestCase.assertEquals('40', table.betForm.elements.amount.value);
            } finally { table.stop(); }
        },
        'bets collect between streets and cards do not redeal on every action'() {
            const { game: table, root } = game();
            try {
                TestCase.assertEquals(18, root.querySelectorAll('.PokerCard.Dealing').length);
                table.data.table.version++; table.data.table.seats[0].bet = 20; table.data.table.seats[0].total = 20;
                table.render();
                TestCase.assertEquals(0, root.querySelectorAll('.PokerCard.Dealing').length);
                TestCase.assertEquals('20', root.querySelector('.PokerBet').textContent);
                TestCase.assertEquals(1, root.querySelectorAll('.PokerChipFlight').length);
                table.data.table.version++; table.data.table.street = 'flop'; table.data.table.board = [0, 1, 2]; table.data.table.seats[0].bet = 0;
                table.render();
                TestCase.assertEquals(0, root.querySelectorAll('.PokerBet').length);
                TestCase.assertEquals(3, root.querySelectorAll('.PokerBoard .Revealing').length);
                const count = root.querySelectorAll('.PokerChipFlight').length;
                table.render(); TestCase.assertEquals(count, root.querySelectorAll('.PokerChipFlight').length);
                table.data.table.version++; table.render();
                TestCase.assertEquals(0, root.querySelectorAll('.PokerBoard .Revealing').length);
            } finally { table.stop(); }
        },
        'showdown explains pots refunds and net chips and highlights exactly the winning five'() {
            const { game: table, root } = game();
            try {
                const data = table.data.table;
                data.version++; data.street = 'finished'; data.showdown = true; data.turn = null;
                data.board = [8, 9, 10, 11, 12];
                data.seats[0].cards = [13, 14]; data.seats[0].total = 100; data.seats[0].won = 160; data.seats[0].stack = 1060;
                data.seats[0].bestCards = data.board; data.seats[0].hand = 'Straight Flush';
                data.seats[1].cards = [15, 16]; data.seats[1].bestCards = data.board; data.seats[1].hand = 'Straight Flush'; data.seats[1].won = 100;
                data.pots = [{amount: 200, winners: [0, 1], refund: false}, {amount: 60, winners: [0], refund: true}];
                table.render();
                TestCase.assertEquals(5, root.querySelectorAll('.PokerCard.Winning').length);
                TestCase.assertEquals(2, root.querySelectorAll('.PokerWinningHand').length);
                TestCase.assertTrue(root.querySelector('.PokerPots').textContent.includes('Main Pot · 200 chips · Split'));
                TestCase.assertTrue(root.querySelector('.PokerPots').textContent.includes('Uncalled Bet Returned · 60'));
                TestCase.assertTrue(root.querySelector('.PokerNet').textContent.includes('+60 chips net'));
                TestCase.assertTrue(root.querySelector('.PokerResult').classList.contains('Arriving'));
                TestCase.assertEquals(2, root.querySelectorAll('.PokerSeat.Winner').length);
                root.querySelectorAll('.PokerWinningHand')[1].click();
                TestCase.assertEquals('true', root.querySelectorAll('.PokerWinningHand')[1].getAttribute('aria-pressed'));
                const flights = root.querySelectorAll('.PokerChipFlight').length;
                table.render(); TestCase.assertEquals(flights, root.querySelectorAll('.PokerChipFlight').length);
                table.stop(); TestCase.assertEquals(0, root.querySelectorAll('.PokerChipFlight').length);
            } finally { table.stop(); }
        },
        'sound preference survives a new game and can be muted without audio support'() {
            const previous = localStorage.getItem('pokerSound');
            try {
                localStorage.removeItem('pokerSound');
                const button = document.createElement('button'); const sound = new PokerSound(button);
                TestCase.assertFalse(sound.enabled); button.click(); TestCase.assertTrue(sound.enabled);
                const other = document.createElement('button'); const restored = new PokerSound(other);
                TestCase.assertTrue(restored.enabled); TestCase.assertEquals('Mute Sound', other.textContent);
                other.click(); TestCase.assertFalse(restored.enabled); restored.play('turn'); restored.stop();
            } finally { if (previous === null) localStorage.removeItem('pokerSound'); else localStorage.setItem('pokerSound', previous); }
        },
        'table shows nine seats with avatars, private cards, and legal controls'() {
            const { game: table, root } = game();
            try {
                TestCase.assertEquals(9, root.querySelectorAll('.PokerSeat').length);
                TestCase.assertEquals(2, root.querySelectorAll('.PokerSeat img').length);
                TestCase.assertEquals('/users/person0/', root.querySelector('.PokerSeat img').closest('a').getAttribute('href'));
                TestCase.assertEquals('/users/person0/', root.querySelector('.PokerSeat .PokerName').closest('a').getAttribute('href'));
                TestCase.assertEquals(2, root.querySelectorAll('.PokerSeat a').length);
                TestCase.assertEquals(7, root.querySelectorAll('.PokerBotAvatar').length);
                TestCase.assertEquals(16, root.querySelectorAll('.PokerCard.Back').length);
                TestCase.assertEquals('Call 20', root.querySelector('[data-poker-move="call"]').textContent);
                TestCase.assertTrue(root.querySelector('[data-poker-move="check"]').disabled);
                table.data.table.legal = {}; table.controls();
                TestCase.assertTrue(root.querySelector('[data-poker-move="fold"]').disabled);
            } finally { table.stop(); }
        },
        'chat displays avatars and literal text and clears when tables change'() {
            const { game: table, root } = game();
            try {
                table.data.table.chat = [{ messageId: 1, userId: 2, name: '@person1', image: '/avatars/1.webp', body: '<img src=x onerror=evil()> hi' }];
                table.render(); table.render();
                TestCase.assertEquals(1, root.querySelectorAll('.PokerMessage').length);
                TestCase.assertEquals(1, root.querySelectorAll('.PokerMessage img').length);
                TestCase.assertEquals('/users/person1/', root.querySelector('.PokerMessage img').closest('a').getAttribute('href'));
                TestCase.assertEquals('/users/person1/', root.querySelector('.PokerMessage .PokerName').closest('a').getAttribute('href'));
                TestCase.assertEquals('<img src=x onerror=evil()> hi', root.querySelector('.PokerMessage p').textContent);
                table.data.table.chat = []; table.render();
                TestCase.assertEquals(0, root.querySelectorAll('.PokerMessage').length);
                table.data.table.tableId = 11; table.render();
                TestCase.assertEquals(0, root.querySelectorAll('.PokerMessage').length);
            } finally { table.stop(); }
        },
        'real player names link to profiles in turn notices results and action history'() {
            const { game: table, root } = game();
            try {
                table.data.table.turn = 1;
                table.render();
                TestCase.assertEquals('/users/person1/', root.querySelector('.PokerTurn a').getAttribute('href'));
                table.data.table.version++;
                table.data.table.street = 'finished';
                table.data.table.turn = null;
                table.data.table.seats[1].won = 60;
                table.data.table.seats[2].won = 20;
                table.data.table.log = ['@person1: Call 20', '♠ Copper Fox: Check'];
                table.render();
                TestCase.assertEquals('/users/person1/', root.querySelector('.PokerResult a').getAttribute('href'));
                TestCase.assertEquals(1, root.querySelectorAll('.PokerResult a').length);
                TestCase.assertEquals('/users/person1/', root.querySelector('.PokerLog a').getAttribute('href'));
                TestCase.assertEquals(1, root.querySelectorAll('.PokerLog a').length);
                TestCase.assertEquals('@person1: Call 20', root.querySelector('.PokerLog li').textContent);
            } finally { table.stop(); }
        },
        'new moves float at their players without replaying or vanishing on updates'() {
            const { game: table, root } = game();
            try {
                TestCase.assertEquals(0, root.querySelectorAll('.PokerAction').length);
                table.data.table.version++;
                table.data.table.log = ['@person1: Raise To 80'];
                table.render();
                const action = root.querySelector('.PokerAction');
                TestCase.assertEquals('Raise To 80', action.textContent);
                TestCase.assertTrue(action.classList.contains('Raise'));
                TestCase.assertEquals(root.querySelectorAll('.PokerSeat')[1].style.getPropertyValue('--seat-x'), action.style.left);
                table.render();
                TestCase.assertEquals(1, root.querySelectorAll('.PokerAction').length);
                table.data.table.version += 2;
                table.data.table.log.push('@person0: Call 80', '♠ Copper Fox: Raise To 1000 · All In');
                table.render();
                TestCase.assertTrue(root.contains(action));
                TestCase.assertEquals(3, root.querySelectorAll('.PokerAction').length);
                TestCase.assertEquals('Call 80', root.querySelector('.PokerAction.Call').textContent);
                TestCase.assertEquals('Raise To 1000 · All In', root.querySelector('.PokerAction.AllIn').textContent);
                action.dispatchEvent(new window.Event('animationend'));
                TestCase.assertFalse(root.contains(action));
                table.data.table.tableId++;
                table.render();
                TestCase.assertEquals(0, root.querySelectorAll('.PokerAction').length);
            } finally { table.stop(); }
        },
        async 'move sends the server hand version and cannot duplicate a pending bet'() {
            const { game: table } = game(); const original = Api.request;
            const requests = []; let release;
            Api.request = async (url, payload) => {
                requests.push(payload);
                await new Promise(resolve => { release = resolve; });
                return { ok: true, data: { response: state() } };
            };
            try {
                const first = FormForm.run(table.betForm, (_, { signal }) => table.move('call', signal));
                await Promise.resolve(); await Promise.resolve();
                const second = FormForm.run(table.betForm, (_, { signal }) => table.move('call', signal));
                await Promise.resolve(); release(); await Promise.all([first, second]);
                TestCase.assertEquals(1, requests.length);
                TestCase.assertEquals(10, requests[0].tableId);
                TestCase.assertEquals(1, requests[0].version);
                TestCase.assertEquals('call', requests[0].move);
            } finally { Api.request = original; table.stop(); }
        },
        async 'leaving the page aborts requests and ignores late responses'() {
            const { game: table } = game(); const original = Api.request; let release; let signal;
            Api.request = async (url, payload, options) => {
                signal = options.signal; await new Promise(resolve => { release = resolve; });
                const changed = state(); changed.table.tableId = 99;
                return { ok: true, data: { response: changed } };
            };
            try {
                const pending = table.send({ action: 'state' }); await Promise.resolve();
                table.stop(); release(); await pending;
                TestCase.assertTrue(signal.aborted);
                TestCase.assertEquals(10, table.data.table.tableId);
            } finally { Api.request = original; table.stop(); }
        },
        'short all-in call remains available without reopening a raise'() {
            const { game: table, root } = game();
            try {
                table.data.table.legal = { call: 15, canCheck: false, canRaise: false, minRaiseTo: 100, maxRaiseTo: 35, allInCall: true };
                table.controls();
                TestCase.assertFalse(root.querySelector('[data-poker-move="allin"]').disabled);
                TestCase.assertTrue(root.querySelector('.PokerBetForm [type="submit"]').disabled);
                TestCase.assertEquals('A of hearts', new PokerCard(25).toDOM().getAttribute('aria-label'));
            } finally { table.stop(); }
        },
    },
};
