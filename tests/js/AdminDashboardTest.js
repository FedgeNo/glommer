import { TestCase } from './TestCase.js';
import { AdminDashboard as DashboardDOM } from '../../scripts/HTMLObjects.js';
import { AdminDashboard } from '../../scripts/Controllers.js';
import { Api } from '../../scripts/Runtime.js';

const tile = (id, value = 'Running') => ({ id, symbol: '🌐', href: '#AdminServices', label: id, value, detail: 'Details', state: 'good' });

async function withDashboard(test) {
    const root = new DashboardDOM({ snapshot: {
        health: [{ id: 'memory', text: 'Memory: 50 free', state: 'neutral' }],
        tiles: [tile('federation'), tile('backups', 'Scheduled')],
    } }).toDOM();
    document.body.appendChild(root);
    const controller = new AdminDashboard(root);
    const originalRequest = Api.request;

    try {
        await test(controller, root);
    } finally {
        controller.destroy();
        root.remove();
        Api.request = originalRequest;
    }
}

const tests = {
    async 'refresh updates text safely and preserves focus and slower tiles'() {
        await withDashboard((controller, root) => {
            const link = root.querySelector('[data-tile="federation"]');
            link.focus();
            controller.update({
                health: [{ id: 'memory', text: 'Memory: 5 free', state: 'warning' }],
                tiles: [{ ...tile('federation'), value: '<img src=x>', detail: '<script>bad()</script>', state: 'bad' }],
            });
            TestCase.assertEquals(link, document.activeElement);
            TestCase.assertEquals('#AdminServices', link.getAttribute('href'));
            TestCase.assertEquals('bad', link.dataset.state);
            TestCase.assertEquals('<img src=x>', link.querySelector('[data-field="value"]').textContent);
            TestCase.assertNull(root.querySelector('img, script'));
            TestCase.assertEquals('Scheduled', root.querySelector('[data-tile="backups"] [data-field="value"]').textContent);
            TestCase.assertEquals('Memory: 5 free', root.querySelector('[data-reading="memory"]').textContent);
        });
    },

    async 'unknown or incomplete readings leave valid values in place'() {
        await withDashboard((controller, root) => {
            controller.update({ health: [null, { id: 'memory', text: 'broken', state: 'invalid' }], tiles: [null, { id: 'federation', state: 'good' }, tile('unrecognized')] });
            TestCase.assertEquals(2, root.querySelectorAll('[data-tile]').length);
            TestCase.assertEquals('Running', root.querySelector('[data-field="value"]').textContent);
            TestCase.assertEquals('Memory: 50 free', root.querySelector('[data-reading]').textContent);
        });
    },

    async 'only one request runs and the next timeout starts after it finishes'() {
        await withDashboard(async controller => {
            const originalTimeout = globalThis.setTimeout;
            const delays = [];
            globalThis.setTimeout = (callback, delay) => { delays.push(delay); return originalTimeout(callback, delay); };
            let finish;
            let calls = 0;
            Api.request = () => { calls++; return new Promise(resolve => { finish = resolve; }); };
            controller.running = true;

            try {
                const refresh = controller.refresh();
                await controller.refresh();
                TestCase.assertEquals(1, calls);
                TestCase.assertEquals('', delays.join(','), 'no next poll exists while awaiting the response');
                finish({ ok: true, data: { response: { health: [], tiles: [] } } });
                await refresh;
                TestCase.assertEquals('10000', delays.join(','), 'next poll is scheduled only after the response');
            } finally {
                globalThis.setTimeout = originalTimeout;
            }
        });
    },

    async 'hidden tabs do not request or schedule a refresh'() {
        await withDashboard(async controller => {
            let calls = 0;
            Api.request = async () => { calls++; return {}; };
            const descriptor = Object.getOwnPropertyDescriptor(document, 'hidden');
            Object.defineProperty(document, 'hidden', { configurable: true, value: true });

            try {
                controller.start();
                await controller.refresh();
                TestCase.assertEquals(0, calls);
                TestCase.assertNull(controller.timer);
            } finally {
                if (descriptor) Object.defineProperty(document, 'hidden', descriptor);
                else delete document.hidden;
            }
        });
    },

    async 'overview is requested only when its minute is due'() {
        await withDashboard(async controller => {
            const requested = [];
            Api.request = async (path, payload) => {
                TestCase.assertEquals('/api/admin-status', path);
                requested.push(payload.overview);
                return { ok: true, data: { response: { health: [], tiles: [] } } };
            };
            controller.running = true;
            await controller.refresh();
            controller.lastOverview = Date.now() - 61000;
            await controller.refresh();
            await controller.refresh();
            TestCase.assertEquals('false,true,false', requested.join(','));
        });
    },

    async 'failed requests retain the last readings and report the failure'() {
        await withDashboard(async (controller, root) => {
            Api.request = async () => ({ ok: false, status: 500 });
            controller.running = true;
            await controller.refresh();
            TestCase.assertEquals('Running', root.querySelector('[data-field="value"]').textContent);
            TestCase.assertTrue(root.querySelector('[data-refresh-status]').textContent.length > 0);
            TestCase.assertTrue(controller.running);
            Api.request = async () => ({ ok: false, status: 403 });
            await controller.refresh();
            TestCase.assertFalse(controller.running, 'lost admin access stops further polling');
        });
    },

    async 'leaving the page aborts the request and prevents a new timer'() {
        await withDashboard(async controller => {
            let signal;
            let finish;
            Api.request = (path, payload, options) => { signal = options.signal; return new Promise(resolve => { finish = resolve; }); };
            controller.start();
            const refresh = controller.refresh();
            window.dispatchEvent(new window.Event('pagehide'));
            TestCase.assertTrue(signal.aborted);
            finish({ ok: false, aborted: true });
            await refresh;
            TestCase.assertFalse(controller.running);
            TestCase.assertNull(controller.request);
        });
    },

    async 'tile links open their corresponding settings details'() {
        await withDashboard((controller, root) => {
            const section = document.createElement('details');
            section.id = 'AdminServices';
            document.body.appendChild(section);
            try {
                root.querySelector('[data-tile]').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
                TestCase.assertTrue(section.open);
            } finally {
                section.remove();
            }
        });
    },
};

export default { suite: 'AdminDashboard', tests };
