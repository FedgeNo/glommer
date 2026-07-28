import { TestCase } from './TestCase.js';
import { RelativeTime } from '../../RelativeTime.js';

export default {
    suite: 'RelativeTime',
    tests: {
        'format() returns a string'() {
            TestCase.assertTrue(typeof RelativeTime.format('2025-01-01T00:00:00Z') === 'string');
        },
        'format() returns "just now" or similar for recent times'() {
            const result = RelativeTime.format(new Date().toISOString());
            TestCase.assertTrue(typeof result === 'string' && result.length > 0);
        },
    }
};
