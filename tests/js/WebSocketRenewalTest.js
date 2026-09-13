import { TestCase } from './TestCase.js';
import { Api } from '../../scripts/Runtime.js';
import { WebSocketManager } from '../../scripts/Controllers.js';

async function fixture(work) {
    const original = { post: Api.post, timeout: globalThis.setTimeout, clear: globalThis.clearTimeout, ws: globalThis.WebSocket };
    const timers = new Map();
    let next = 0;
    const socket = { readyState: 1, sent: [], closed: false,
        send(value) { this.sent.push(value); }, close() { this.closed = true; this.readyState = 3; } };
    globalThis.WebSocket = { OPEN: 1 };
    globalThis.setTimeout = (callback, delay) => { const id = ++next; timers.set(id, { callback, delay }); return id; };
    globalThis.clearTimeout = id => timers.delete(id);
    const manager = new WebSocketManager();
    manager.socket = socket;
    const tick = () => {
        const [id, timer] = timers.entries().next().value;
        timers.delete(id);
        TestCase.assertEquals(15000, timer.delay);
        return timer.callback();
    };
    try { await work({ manager, socket, timers, tick }); }
    finally {
        Api.post = original.post;
        globalThis.setTimeout = original.timeout;
        globalThis.clearTimeout = original.clear;
        globalThis.WebSocket = original.ws;
    }
}

export default {
    suite: 'WebSocketRenewal',
    tests: {
        async 'renewals wait for completion and send only the PHP-issued lease'() {
            await fixture(async ({ manager, socket, timers, tick }) => {
                let resolve;
                let calls = 0;
                Api.post = () => { calls++; return new Promise(done => { resolve = done; }); };
                manager.scheduleRenewal(socket);
                const pending = tick();
                TestCase.assertEquals(1, calls);
                TestCase.assertEquals(0, timers.size);
                resolve({ token: 'signed-renewal' });
                await pending;
                TestCase.assertEquals('signed-renewal', socket.sent[0]);
                TestCase.assertEquals(1, timers.size);
            });
        },
        async 'refused or failed renewals close the socket and stop scheduling'() {
            for (const reject of [false, true]) {
                await fixture(async ({ manager, socket, timers, tick }) => {
                    Api.post = async () => { if (reject) throw new Error('offline'); return null; };
                    manager.scheduleRenewal(socket);
                    await tick();
                    TestCase.assertTrue(socket.closed);
                    TestCase.assertEquals(0, timers.size);
                });
            }
        },
        async 'a late response cannot renew or reschedule a replaced or closed socket'() {
            for (const replace of [false, true]) {
                await fixture(async ({ manager, socket, timers, tick }) => {
                    let resolve;
                    Api.post = () => new Promise(done => { resolve = done; });
                    manager.scheduleRenewal(socket);
                    const pending = tick();
                    if (replace) manager.socket = {};
                    else socket.close();
                    resolve({ token: 'late-renewal' });
                    await pending;
                    TestCase.assertEquals(0, socket.sent.length);
                    TestCase.assertEquals(0, timers.size);
                });
            }
        },
    },
};
