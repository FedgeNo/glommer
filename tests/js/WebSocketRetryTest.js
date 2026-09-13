import { TestCase } from './TestCase.js';
import { Api } from '../../scripts/Runtime.js';
import { WebSocketManager } from '../../scripts/Controllers.js';

async function fixture(work) {
    const saved = { post: Api.post, timeout: globalThis.setTimeout, clear: globalThis.clearTimeout, ws: globalThis.WebSocket, random: Math.random };
    const timers = new Map();
    const sockets = [];
    let id = 0;
    globalThis.setTimeout = (callback, delay) => { timers.set(++id, { callback, delay }); return id; };
    globalThis.clearTimeout = value => timers.delete(value);
    Math.random = () => 0.5;
    globalThis.WebSocket = class {
        static OPEN = 1;
        static CONNECTING = 0;
        readyState = 0;
        handlers = {};
        constructor() { sockets.push(this); }
        addEventListener(name, handler) { this.handlers[name] = handler; }
        send() {}
        close() { this.readyState = 3; this.handlers.close?.(); }
        open() { this.readyState = 1; this.handlers.open(); }
    };
    const take = delay => {
        const entry = [...timers].find(([, timer]) => timer.delay === delay);
        TestCase.assertTrue(Boolean(entry), 'Expected timer at ' + delay);
        timers.delete(entry[0]);
        return entry[1].callback();
    };
    try { await work({ manager: new WebSocketManager(), timers, sockets, take }); }
    finally {
        Api.post = saved.post;
        globalThis.setTimeout = saved.timeout;
        globalThis.clearTimeout = saved.clear;
        globalThis.WebSocket = saved.ws;
        Math.random = saved.random;
    }
}

export default {
    suite: 'WebSocketRetry',
    tests: {
        async 'capacity failures keep retrying with bounded backoff and one pending attempt'() {
            await fixture(async ({ manager, timers, sockets, take }) => {
                Api.post = async () => ({ token: 'signed lease' });
                await manager.connect();
                for (const delay of [10000, 20000, 40000, 80000, 160000, 300000, 300000]) {
                    const socket = sockets.at(-1);
                    socket.open();
                    socket.close(); // handshake opened, account admission refused.
                    manager.scheduleReconnect();
                    TestCase.assertEquals(1, timers.size);
                    take(delay);
                    await Promise.resolve();
                    await Promise.resolve();
                }
                TestCase.assertEquals(8, sockets.length);
            });
        },
        async 'slow token requests cannot overlap and thrown failures schedule a retry'() {
            await fixture(async ({ manager, timers }) => {
                let reject;
                let calls = 0;
                Api.post = () => { calls++; return new Promise((resolve, fail) => { reject = fail; }); };
                const pending = manager.connect();
                await manager.connect();
                TestCase.assertEquals(1, calls);
                reject(new Error('offline'));
                await pending;
                TestCase.assertEquals(1, timers.size);
                TestCase.assertFalse(manager.connecting);
            });
        },
        async 'a connection surviving renewal resets its retry delay'() {
            await fixture(async ({ manager, sockets, take }) => {
                Api.post = async () => ({ token: 'signed lease' });
                manager.reconnectAttempts = 5;
                await manager.connect();
                sockets[0].open();
                TestCase.assertEquals(5, manager.reconnectAttempts);
                await take(15000);
                TestCase.assertEquals(0, manager.reconnectAttempts);
                sockets[0].close();
                take(10000);
                await Promise.resolve();
            });
        },
    },
};
