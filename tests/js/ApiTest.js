import { TestCase } from './TestCase.js';
import { Api } from '../../scripts/Api.js';

export default {
    suite: 'Api',
    tests: {
        async 'post() returns response on success'() {
            const orig = globalThis.fetch;
            globalThis.fetch = async () => ({
                ok: true,
                json: async () => ({ response: { success: true } }),
            });
            const result = await Api.post('/api/test');
            TestCase.assertNotNull(result);
            globalThis.fetch = orig;
        },
        async 'post() handles network error gracefully'() {
            const orig = globalThis.fetch;
            globalThis.fetch = async () => { throw new Error('Network error'); };
            const result = await Api.post('/api/test');
            TestCase.assertNull(result);
            globalThis.fetch = orig;
        },
        async 'post() handles non-ok response with error message'() {
            const orig = globalThis.fetch;
            globalThis.fetch = async () => ({
                ok: false,
                json: async () => ({ error: 'Invalid' }),
            });
            const result = await Api.post('/api/test');
            TestCase.assertNull(result);
            globalThis.fetch = orig;
        },
    }
};
