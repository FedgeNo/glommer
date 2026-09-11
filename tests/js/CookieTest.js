import { TestCase } from './TestCase.js';
import { Cookie, ClientConfig, csrf_headers } from '../../scripts/Runtime.js';
import { JSDOM } from 'jsdom';

function on_https_page(test) {
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;
    const dom = new JSDOM('', { url: 'https://example.test/' });

    try {
        globalThis.window = dom.window;
        globalThis.document = dom.window.document;
        test();
    } finally {
        globalThis.window = previousWindow;
        globalThis.document = previousDocument;
        dom.window.close();
    }
}

export default {
    suite: 'Cookie',
    tests: {
        'get() returns null for missing cookie'() {
            document.cookie = 'existing=value;';
            TestCase.assertNull(Cookie.get('nonexistent'));
        },
        'get() returns decoded value'() {
            document.cookie = 'test=%20hello%20;';
            TestCase.assertEquals(' hello ', Cookie.get('test'));
        },
        'get() handles special regex characters in cookie name'() {
            document.cookie = 'some.cookie$=works;';
            TestCase.assertEquals('works', Cookie.get('some.cookie$'));
        },
        'HTTPS sends CSRF from the host cookie when an old cookie also exists'() {
            on_https_page(() => {
                document.cookie = 'CSRF-TOKEN=untrusted; Domain=example.test; Path=/; Secure';
                document.cookie = '__Host-CSRF-TOKEN=trusted%20token; Path=/; Secure';
                TestCase.assertEquals('trusted token', Cookie.get('CSRF-TOKEN'));
                TestCase.assertEquals('trusted token', csrf_headers()['X-CSRF-Token']);
            });
        },
        'HTTPS does not fall back to the old CSRF cookie'() {
            on_https_page(() => {
                document.cookie = 'CSRF-TOKEN=untrusted; Path=/; Secure';
                TestCase.assertNull(Cookie.get('CSRF-TOKEN'));
                TestCase.assertEquals('', csrf_headers()['X-CSRF-Token']);
            });
        },
        'HTTPS configuration reads only the host cookie'() {
            on_https_page(() => {
                document.cookie = 'APP-CONFIG=untrusted; Domain=example.test; Path=/; Secure';
                TestCase.assertNull(ClientConfig._getCookie('APP-CONFIG'));

                const config = JSON.stringify({ currentUserId: 7 });
                document.cookie = '__Host-APP-CONFIG=' + encodeURIComponent(config) + '; Path=/; Secure';
                TestCase.assertEquals(config, ClientConfig._getCookie('APP-CONFIG'));
            });
        },
        'HTTP setup can send its unprefixed CSRF cookie'() {
            const previous = Cookie.get('CSRF-TOKEN');

            try {
                document.cookie = 'CSRF-TOKEN=setup-token; Path=/';
                TestCase.assertEquals('setup-token', csrf_headers()['X-CSRF-Token']);
            } finally {
                document.cookie = previous === null
                    ? 'CSRF-TOKEN=; Path=/; Max-Age=0'
                    : 'CSRF-TOKEN=' + encodeURIComponent(previous) + '; Path=/';
            }
        },
    }
};
