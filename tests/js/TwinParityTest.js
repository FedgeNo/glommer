import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

import { TestCase } from './TestCase.js';
import { canonical_lines, first_difference } from './DOMCanonicalForm.js';
import { ClientConfig } from '../../scripts/Runtime.js';
import * as HTMLObjectExports from '../../scripts/HTMLObjects.js';

const project_root = resolve(import.meta.dirname, '../..');
const TWINS = { ...HTMLObjectExports };
const EXCLUDED_HTML_OBJECT_EXPORTS = new Map([
    ['HTMLObject', 'abstract browser DOM base'],
    ['Avatar', 'abstract avatar factory; its concrete image and initial variants are covered'],
    ['Dialog', 'client-only modal interaction with no PHP-rendered twin'],
    ['Linkifier', 'client-side tokenizer utility; PHP/JavaScript token parity has its own test'],
    ['EmojiRenderer', 'client-only enhancement pass over DOM that is already rendered'],
    ['MathRenderer', 'client-only KaTeX enhancement pass over server and browser DOM'],
    ['PostRepostButton', 'client-only controller that updates an existing PHP-rendered button'],
    ['SkinTone', 'client-side emoji transformation helper rather than a DOM renderer'],
    ['MessageCrypto', 'browser-only WebCrypto service rather than a DOM renderer'],
]);

const FACTORIES = new Map([
    ['BannedUser', payload => HTMLObjectExports.BannedUser.fromData(payload)],
    ['DeltaRenderer', payload => ({ toDOM: () => HTMLObjectExports.DeltaRenderer.render(
        payload.ops,
        payload.customEmoji,
        payload.mentionsAreLocal
    ) })],
    ['Message', payload => HTMLObjectExports.Message.fromData(payload)],
    ['Notification', payload => HTMLObjectExports.Notification.fromData(payload)],
    ['OtherUser', payload => HTMLObjectExports.OtherUser.fromData(payload)],
    ['Poll', payload => HTMLObjectExports.Poll.fromData(payload)],
    ['Post', payload => HTMLObjectExports.Post.fromData(payload)],
    ['ReceivedFriendRequest', payload => HTMLObjectExports.ReceivedFriendRequest.fromData(payload)],
    ['RelativeTime', payload => new HTMLObjectExports.RelativeTime(payload.dateString, payload.fallbackFormat)],
    ['Report', payload => HTMLObjectExports.Report.fromData(payload)],
    ['ToggleButton', payload => {
        const button = new HTMLObjectExports.ToggleButton(payload.labels, payload.className, payload.pressable);
        button.showing = payload.showing;
        return button;
    }],
    ['User', payload => HTMLObjectExports.User.fromData(payload)],
    ['UserBio', payload => new HTMLObjectExports.UserBio(payload)],
]);

function isClass(value) {
    return typeof value === 'function' && /^class\s/u.test(Function.prototype.toString.call(value));
}

function buildTwin(name, payload) {
    const factory = FACTORIES.get(name);

    if (factory) return factory(payload);

    const Twin = TWINS[name];
    const object = new Twin(payload.properties ?? payload);

    if (payload.className !== undefined) object.class = payload.className;
    Object.assign(object.attributes, payload.attributes ?? {});
    object.addContents(payload.content ?? []);

    return object;
}

function cases() {
    const output = execFileSync('php', [
        resolve(project_root, 'bin/twin-parity-cases.php'),
        ClientConfig.siteURL(),
        String(ClientConfig.get('currentUserId') ?? ''),
        ClientConfig.get('currentUserCanModerate') ? '1' : '0',
    ], {
        encoding: 'utf8',
    });

    return Object.entries(JSON.parse(output));
}

function render(twin) {
    if (typeof twin.toDOM === 'function') {
        return twin.toDOM();
    }

    if (typeof twin.toElement === 'function') {
        return twin.toElement();
    }

    return twin.element();
}

const server_cases = cases();
const tests = {
    'every server case names a registered browser twin'() {
        const missing = server_cases
            .map(([, server_case]) => server_case.class)
            .filter(name => !TWINS[name]);

        TestCase.assertEquals(
            '',
            [...new Set(missing)].join(', '),
            'server cases exist for classes with no browser twin: ' + missing.join(', ')
        );
    },
    'every exported HTML object class is covered or explicitly excluded'() {
        const exported = Object.entries(HTMLObjectExports)
            .filter(([, value]) => isClass(value))
            .map(([name]) => name);
        const covered = new Set(server_cases.map(([, server_case]) => server_case.class));
        const missing = exported.filter(name => !covered.has(name) && !EXCLUDED_HTML_OBJECT_EXPORTS.has(name));
        const stale = [...EXCLUDED_HTML_OBJECT_EXPORTS.keys()].filter(name => !exported.includes(name));

        TestCase.assertEquals('', missing.join(', '), 'exported HTML object classes need parity cases: ' + missing.join(', '));
        TestCase.assertEquals('', stale.join(', '), 'parity exclusions no longer exported: ' + stale.join(', '));
    },
};

for (const [name, server_case] of server_cases) {
    tests[`${name} builds the DOM PHP rendered`] = () => {
        const Twin = TWINS[server_case.class];

        TestCase.assertNotNull(Twin, `no browser twin registered for ${server_case.class}`);

        const rendered = canonical_lines(render(buildTwin(server_case.class, server_case.payload)));
        const difference = first_difference(server_case.canonical, rendered);

        TestCase.assertNull(difference, `${name}: the browser twin diverged from PHP - ${difference}`);
    };
}

export default { suite: 'TwinParity', tests };
