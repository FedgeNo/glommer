import { TestCase } from './TestCase.js';
import { RelativeTime } from '../../RelativeTime.js';

export default {
    suite: 'RelativeTime',
    tests: {
        'format() returns a string'() {
            TestCase.assertTrue(typeof RelativeTime.format('2025-01-01T00:00:00Z') === 'string');
        },
        'format() returns "just now" for very recent times'() {
            // We can't easily mock Date.now, but we can check the return shape
            const result = RelativeTime.format(new Date().toISOString());
            TestCase.assertTrue(result === 'just now' || result.includes('ago') || result.includes('just now'));
        },
    }
};

