import { TestCase } from './TestCase.js';
import { RelativeTime } from '../../scripts/RelativeTime.js';

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
        // These two mirror PHP's date('F j, Y') and date('F j, Y g:i A'), which
        // the server renders in UTC. Formatting in the viewer's timezone instead
        // would have the same moment read as a different day either side of
        // midnight, depending only on which side built the element.
        'date() matches the server\'s full-date format, in UTC'() {
            TestCase.assertEquals('July 30, 2026', RelativeTime.date('2026-07-30 15:04:00'));
        },
        'date() does not drift across midnight in the viewer\'s timezone'() {
            TestCase.assertEquals('July 30, 2026', RelativeTime.date('2026-07-30 00:30:00'));
            TestCase.assertEquals('July 30, 2026', RelativeTime.date('2026-07-30 23:30:00'));
        },
        'dateAndTime() matches the server\'s date-and-time format, in UTC'() {
            TestCase.assertEquals('July 30, 2026 3:04 PM', RelativeTime.dateAndTime('2026-07-30 15:04:00'));
        },
    }
};
