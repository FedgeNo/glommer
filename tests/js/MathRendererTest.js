import { TestCase } from './TestCase.js';
import { MathRenderer, render_formulas } from '../../scripts/HTMLObjects.js';

export default {
    suite: 'MathRenderer',
    tests: {
        // A second pass has to leave an already-rendered formula alone: KaTeX has
        // replaced the span's contents by then, so rendering it again would be
        // rendering its own output.
        'render_formulas renders each formula once'() {
            const span = document.createElement('span');
            span.className = 'PostFormula';
            span.dataset.formula = 'E=mc^2';
            document.body.appendChild(span);

            const original_katex = globalThis.katex;
            const rendered = [];
            globalThis.katex = { render: (source, target) => rendered.push({ source, target }) };

            render_formulas(document.body);
            render_formulas(document.body);

            globalThis.katex = original_katex;
            document.body.removeChild(span);

            TestCase.assertEquals(1, rendered.length, 'the formula should be rendered once, not again on the second pass');
            TestCase.assertEquals('E=mc^2', rendered[0].source);
            TestCase.assertTrue(rendered[0].target === span, 'it should render into the formula span itself');
        },
        'init() runs without error'() {
            globalThis.ClientConfig = { get: () => false };
            MathRenderer.init();
            TestCase.assertTrue(true);   // smoke test – module loads correctly
        },
    }
};
