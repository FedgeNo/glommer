import { TestCase } from './TestCase.js';
import { page_language } from '../../scripts/Runtime.js';

export default {
    suite: 'PageLanguage',
    tests: {
        'translation follows the rendered page rather than the browser preference'() {
            const previous = document.documentElement.lang;

            try {
                document.documentElement.lang = 'fr';
                TestCase.assertEquals('fr', page_language());
            } finally {
                document.documentElement.lang = previous;
            }
        },

        'an undeclared page falls back to English'() {
            const previous = document.documentElement.lang;

            try {
                document.documentElement.removeAttribute('lang');
                TestCase.assertEquals('en', page_language());
            } finally {
                document.documentElement.lang = previous;
            }
        },
    },
};
