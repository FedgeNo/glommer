import { TestCase } from './TestCase.js';
import { MathRenderer, render_formulas } from '../../scripts/MathRenderer.js';

export default {
    suite: 'MathRenderer',
    tests: {
        'render_formulas processes .PostFormula elements'() {
            // Set up a DOM element that render_formulas should transform
            const span = document.createElement('span');
            span.className = 'PostFormula';
            span.dataset.formula = 'E=mc^2';
            document.body.appendChild(span);

            render_formulas(document.body);
            // After rendering, the data‑rendered flag should be set
            TestCase.assertEquals('1', span.dataset.rendered);
            document.body.removeChild(span);
        },
        'init() runs without error'() {
            globalThis.ClientConfig = { get: () => false };
            MathRenderer.init();
            TestCase.assertTrue(true);   // smoke test – module loads correctly
        },
    }
};
