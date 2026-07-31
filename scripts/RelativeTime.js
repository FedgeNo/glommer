import { ClientConfig } from '/scripts/ClientConfig.js';
import { parse_server_date } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Single source of truth for relative‑time display and periodic refresh.
 *
 *  - RelativeTime.format(dateString)   → human‑readable string
 *  - RelativeTime.refresh(root)        → immediately update .RelativeTime elements
 *  - RelativeTime.start() / stop()     → manage the 60‑second refresh cycle
 */
export class RelativeTime {
    // Offset between server time and local time, computed once at module load.
    static #serverTimeOffset =
        (typeof ClientConfig.get('serverTime') === 'number'
            ? ClientConfig.get('serverTime')
            : 0) - Date.now();

    static #intervalId = null;
    static #running = false;

    // ----------------------------------------------------------------
    // Formatting
    // ----------------------------------------------------------------

    static format(dateString) {
        const target = parse_server_date(dateString);
        const diffSeconds = Math.round(
            (RelativeTime.#correctedNow() - target.getTime()) / 1000
        );

        if (diffSeconds < 60) return 'just now';

        const diffMinutes = Math.round(diffSeconds / 60);
        if (diffMinutes < 60) return diffMinutes + 'm ago';

        const diffHours = Math.round(diffMinutes / 60);
        if (diffHours < 24) return diffHours + 'h ago';

        const diffDays = Math.round(diffHours / 24);
        if (diffDays < 7) return diffDays + 'd ago';

        return target.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    /**
     * A full date, as the server writes it (PHP's 'F j, Y'). In UTC, like every
     * absolute time the server renders: it has no way to know the viewer's
     * timezone, so formatting a client-built copy locally would have the same
     * date read differently depending on which side rendered it.
     */
    static date(dateString) {
        return parse_server_date(dateString).toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            timeZone: 'UTC',
        });
    }

    /** That date plus the time of day, matching PHP's 'F j, Y g:i A'. */
    static dateAndTime(dateString) {
        const target = parse_server_date(dateString);

        return RelativeTime.date(dateString) + ' ' + target.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            timeZone: 'UTC',
        });
    }

    // ----------------------------------------------------------------
    // DOM refresh (was refresh_relative_times in main.js)
    // ----------------------------------------------------------------

    /**
     * Update every .RelativeTime element inside `root` (defaults to document).
     * Safe to call before start().
     */
    static refresh(root = document) {
        root.querySelectorAll('.RelativeTime').forEach((el) => {
            // If the element lacks a datetime, try to pull it from a parent .Post
            if (!el.hasAttribute('datetime')) {
                const post = el.closest('.Post');
                if (!post) return;
                el.setAttribute('datetime', post.dataset.createdAt);
            }
            el.textContent = RelativeTime.format(el.getAttribute('datetime'));
        });
    }

    // ----------------------------------------------------------------
    // Lifecycle (start / stop)
    // ----------------------------------------------------------------

    static init() {
        if (RelativeTime.#running) return;
        RelativeTime.#running = true;

        RelativeTime.refresh();   // initial render
        RelativeTime.#intervalId = setInterval(
            () => RelativeTime.refresh(),
            60000
        );
    }

    static stop() {
        if (RelativeTime.#intervalId !== null) {
            clearInterval(RelativeTime.#intervalId);
            RelativeTime.#intervalId = null;
        }
        RelativeTime.#running = false;
    }

    // ----------------------------------------------------------------
    // Private helper (was corrected_now in utils.js)
    // ----------------------------------------------------------------

    static #correctedNow() {
        return Date.now() + RelativeTime.#serverTimeOffset;
    }
}

ReadyHandler.add(RelativeTime.init);
