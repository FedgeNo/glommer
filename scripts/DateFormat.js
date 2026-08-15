import { Strings } from '/scripts/Strings.js';

/**
 * A date, written the way the reader's language writes one - the twin of
 * DateFormat.php, building the same string from the same locale entry.
 *
 * Not toLocaleDateString(): the browser's CLDR and the server's locale table
 * are two different sources, and a card the server rendered sitting beside one
 * the client built would show the same date two ways. One table, both sides.
 *
 * UTC throughout, for the reason the server is: it cannot know the viewer's
 * timezone, and a date that moved across midnight depending on which renderer
 * produced it would be worse than one that is plainly UTC everywhere.
 */
export class DateFormat {
    static #ENGLISH = {
        months: {
            1: 'January', 2: 'February', 3: 'March', 4: 'April', 5: 'May', 6: 'June',
            7: 'July', 8: 'August', 9: 'September', 10: 'October', 11: 'November', 12: 'December',
        },
        shortMonths: {
            1: 'Jan', 2: 'Feb', 3: 'Mar', 4: 'Apr', 5: 'May', 6: 'Jun',
            7: 'Jul', 8: 'Aug', 9: 'Sep', 10: 'Oct', 11: 'Nov', 12: 'Dec',
        },
        long: '{month} {day}, {year}',
        short: '{month} {day}, {year}',
        time: '{hour}:{minute} {meridiem}',
        dateAndTime: '{date} at {time}',
        am: 'AM',
        pm: 'PM',
        clock: 12,
    };

    static #words() {
        return Strings.for('DateFormat', DateFormat.#ENGLISH);
    }

    /** "August 11, 2026" - the full date. */
    static long(date) {
        return DateFormat.#date(date, 'long', 'months');
    }

    /** "Aug 11, 2026" - the same date where there is less room for it. */
    static short(date) {
        return DateFormat.#date(date, 'short', 'shortMonths');
    }

    /** "August 11, 2026 at 3:04 PM" - joined the way the language joins them. */
    static longWithTime(date) {
        return DateFormat.#words().dateAndTime
            .replace('{date}', DateFormat.long(date))
            .replace('{time}', DateFormat.time(date));
    }

    /** "3:04 PM", or "15:04" where the language keeps a twenty-four hour clock. */
    static time(date) {
        const words = DateFormat.#words();
        const hour = date.getUTCHours();
        const twelve = Number(words.clock) === 12;

        return String(words.time ?? '')
            .replaceAll('{hour}', twelve
                ? String(hour % 12 || 12)
                : String(hour).padStart(2, '0'))
            .replaceAll('{minute}', String(date.getUTCMinutes()).padStart(2, '0'))
            .replaceAll('{meridiem}', String(words[hour < 12 ? 'am' : 'pm'] ?? ''));
    }

    /**
     * Coerced and replaceAll'd to match DateFormat.php exactly, which casts
     * what it reads and substitutes with str_replace: a pattern naming a piece
     * of the date twice would otherwise be filled once here and twice there,
     * and the same day would read differently depending on which side built
     * the card.
     */
    static #date(date, pattern, monthList) {
        const words = DateFormat.#words();
        const months = words[monthList] ?? {};

        return String(words[pattern] ?? '')
            .replaceAll('{month}', String(months[date.getUTCMonth() + 1] ?? ''))
            .replaceAll('{day}', String(date.getUTCDate()))
            .replaceAll('{year}', String(date.getUTCFullYear()));
    }
}
