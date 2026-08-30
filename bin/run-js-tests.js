import { register } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join as joinPath, resolve as resolvePath, dirname as dirnameOf } from 'node:path';
import { fileURLToPath as fileURLToPathFn } from 'node:url';

// The emoji shortcode table lives in PHP and is served to the browser rather
// than kept as a JS file, so there is nothing on disk for the resolver to find.
// It is asked for here, from that one copy - a stub would be a second table,
// and the point of serving it is that there is only ever one.
//
// In a fresh per-run directory, not a fixed /tmp name: a fixed name is owned
// by whoever ran the suite first, and the next account to try (the admin
// Tests page runs this as the web server) gets EACCES off someone else's
// leftover file.
const emojiModulePath = joinPath(mkdtempSync(joinPath(tmpdir(), 'glommer-js-tests-')), 'glommer-emoji-shortcodes.js');
const projectDir = resolvePath(dirnameOf(fileURLToPathFn(import.meta.url)), '..');

writeFileSync(emojiModulePath, execFileSync('php', [
    '-r',
    "require '" + projectDir + "/src/classes/EmojiShortcodeMap.php'; echo EmojiShortcodeMap::javaScriptModule();",
]));

process.env.GLOMMER_EMOJI_MODULE = emojiModulePath;

register('../tests/js/resolver.js', import.meta.url);

import { readdirSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { JSDOM } from 'jsdom';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const testsDir = resolve(projectRoot, 'tests/js');

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
    url: 'http://localhost/',
    pretendToBeVisual: true,
});
globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.Element = dom.window.Element;
globalThis.Node = dom.window.Node;
globalThis.HTMLElement = dom.window.HTMLElement;
globalThis.CustomEvent = dom.window.CustomEvent;
globalThis.NodeFilter = dom.window.NodeFilter;
globalThis.localStorage = dom.window.localStorage;
globalThis.sessionStorage = dom.window.sessionStorage;
globalThis.HTMLImageElement = dom.window.HTMLImageElement;
globalThis.HTMLMediaElement = dom.window.HTMLMediaElement;

await import('../scripts/Runtime.js');

// Browser components read the same canonical English catalog production does.
// Loading it here keeps tests from inventing a second set of fallback strings.
const { Strings } = await import('../scripts/Runtime.js');
const englishStrings = JSON.parse(readFileSync(resolve(projectRoot, 'locales/en.json'), 'utf8'));
Strings.useLocale(englishStrings, 'en');

// Mocks for modules that need them
globalThis.Quill = class {
    constructor() {}
    // A real element, so QuillEditor's tooltip pass can query it the way it
    // queries a real toolbar - it reaches for querySelectorAll as well as
    // querySelector, and a hand-rolled shape has to answer both.
    getModule() { return { container: document.createElement('div') }; }
    getContents() { return []; }
    getText() { return ''; }
    setContents() {}
    setText() {}
};
globalThis.katex = { render: () => {} };
globalThis.renderMathInElement = () => {};
globalThis.render_math = () => {};
globalThis.render_formulas = () => {};
globalThis.ClientConfig = {
    get: (key) => key === 'currentUserId' ? 2 : null,
    siteURL: () => 'http://localhost',
};
// jsdom implements neither fetch nor Response, so the Node runtime's own
// Response is what stands in. Assigning jsdom's (undefined) over it is what made
// this stub throw on every call, so any test reaching fetch only ever exercised
// the code's error branch.
globalThis.fetch = async () => new Response(
    JSON.stringify({ response: { success: true } }),
    { status: 200 }
);
globalThis.requestAnimationFrame = (cb) => cb();
dom.window.requestAnimationFrame = (cb) => cb();
dom.window.matchMedia = () => ({
    matches: false,
    addEventListener() {},
    removeEventListener() {},
});

let total = 0, passed = 0;
const failures = [];

const files = readdirSync(testsDir).filter(f => f.endsWith('Test.js') && f !== 'resolver.js');
for (const file of files) {
    const module = await import(resolve(testsDir, file));
    const { suite, tests } = module.default;
    console.group(suite);
    for (const [name, fn] of Object.entries(tests)) {
        total++;
        try {
            // Tests that exercise locale switching must not leak their table
            // into whichever component happens to run after them.
            Strings.useLocale(englishStrings, 'en');
            await fn();
            console.log(`  \x1b[32mPASS\x1b[0m  ${suite} :: ${name}`);
            passed++;
        } catch (err) {
            console.log(`  \x1b[31mFAIL\x1b[0m  ${suite} :: ${name}  —  ${err.message}`);
            failures.push({ suite, name, message: err.message });
        }
    }
    console.groupEnd();
}
console.log(`\n${passed}/${total} passed`);
if (failures.length) {
    console.group('Failures');
    failures.forEach(f => console.log(`  ${f.suite} :: ${f.name}  —  ${f.message}`));
    console.groupEnd();
}

dom.window.close();
process.exit(failures.length ? 1 : 0);
