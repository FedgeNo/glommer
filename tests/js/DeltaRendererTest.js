import { TestCase } from './TestCase.js';
import { DeltaRenderer } from '../../scripts/DeltaRenderer.js';

/**
 * The client twin of DeltaRenderer.php, which rebuilds a post body from the
 * same Delta. Quill marks every line of a code block separately, the way it
 * marks every item of a list, so a renderer that opens an element per line
 * turns a script into a stack of one-line boxes.
 *
 * See tests/DeltaRendererTest.php for the server's half; a post that differs
 * depending on whether it arrived in the page or over AJAX is the failure both
 * halves exist to prevent.
 */
const codeLine = (text) => [
    { insert: text },
    { attributes: { 'code-block': true }, insert: '\n' },
];

export default {
    suite: 'DeltaRenderer',
    tests: {
        'a multi-line code block is one pre'() {
            const body = DeltaRenderer.render([
                ...codeLine('function greet() {'),
                ...codeLine('    return 1;'),
                ...codeLine('}'),
            ]);

            const blocks = body.querySelectorAll('pre');

            TestCase.assertEquals(1, blocks.length);
            TestCase.assertEquals('function greet() {\n    return 1;\n}', blocks[0].textContent);
        },
        'a blank line inside the block survives'() {
            const body = DeltaRenderer.render([
                ...codeLine('one'),
                ...codeLine(''),
                ...codeLine('three'),
            ]);

            TestCase.assertEquals('one\n\nthree', body.querySelector('pre').textContent);
        },
        'writing after the block is not inside it'() {
            const body = DeltaRenderer.render([
                ...codeLine('code();'),
                { insert: 'and then some words\n' },
            ]);

            TestCase.assertEquals('code();', body.querySelector('pre').textContent);
            TestCase.assertEquals('and then some words', body.querySelector('p').textContent);
        },
        'two blocks separated by writing stay apart'() {
            const body = DeltaRenderer.render([
                ...codeLine('first();'),
                { insert: 'between\n' },
                ...codeLine('second();'),
            ]);

            const blocks = body.querySelectorAll('pre');

            TestCase.assertEquals(2, blocks.length);
            TestCase.assertEquals('first();', blocks[0].textContent);
            TestCase.assertEquals('second();', blocks[1].textContent);
        },
        // A <pre> is whitespace-significant, so the helper that spaces markup
        // out for readability must not be the one filling it.
        'nothing is spaced out inside the block'() {
            const body = DeltaRenderer.render([
                { insert: 'a', attributes: { bold: true } },
                { insert: 'b', attributes: { italic: true } },
                { attributes: { 'code-block': true }, insert: '\n' },
            ]);

            TestCase.assertEquals('ab', body.querySelector('pre').textContent);
        },
    }
};
