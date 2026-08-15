import { DateFormat } from '/scripts/DateFormat.js';
import { parse_server_date } from '/scripts/utils.js';
import { Strings } from '/scripts/Strings.js';

/**
 * The time control under the map. Owns the slider, the mode buttons and the
 * playback timer; PostMap hands it the posts and a callback, and it hands back
 * the subset that should be on the map. It knows nothing about Leaflet.
 *
 * Two readings of the same handle: cumulative (everything up to that moment,
 * the default - so the map opens complete and dragging back removes pins) and
 * window (only what was posted around it, which finds bursts).
 */
export class MapScrubber {
    static #STEPS = 1000;
    static #PLAY_MS = 60;

    /** Fraction of the whole span each side of the handle in window mode. */
    static #WINDOW_FRACTION = 0.05;

    #element;
    #range;
    #label;
    #onChange;
    #posts = [];
    #first = 0;
    #last = 0;
    #mode = 'cumulative';
    #timer = null;
    #bound = false;

    constructor(element, onChange) {
        this.#element = element;
        this.#range = element.querySelector('.MapScrubberRange');
        this.#label = element.querySelector('.MapScrubberLabel');
        this.#onChange = onChange;
    }

    /**
     * Hands the scrubber the located posts. Anything without a usable date is
     * dropped rather than being parked at the epoch, which would stretch the
     * whole span back to 1970 for one bad row.
     */
    start(posts) {
        this.#posts = posts
            .map((post) => ({ post, time: parse_server_date(post.createdAt)?.getTime() }))
            .filter((entry) => Number.isFinite(entry.time));

        this.#activate();
    }

    /**
     * Shows the control once there's a span worth scrubbing, and recomputes the
     * bounds from whatever posts are known. Safe to call again as posts arrive;
     * the listeners are only bound the first time.
     */
    #activate() {
        // A single post spans no time. Stay hidden rather than offering a
        // slider that can only ever mean one thing.
        if (this.#posts.length < 2) {
            return;
        }

        const times = this.#posts.map((entry) => entry.time);
        this.#first = Math.min(...times);
        this.#last = Math.max(...times);

        this.#element.querySelector('.MapScrubberFirst').textContent = MapScrubber.#formatDate(this.#first);
        this.#element.querySelector('.MapScrubberLast').textContent = MapScrubber.#formatDate(this.#last);

        if (!this.#bound) {
            this.#bind();
            this.#bound = true;
        }

        this.#element.classList.add('Active');
        this.#apply();
    }

    /**
     * Takes a post made after the initial load. Extends the span to reach it
     * rather than leaving it outside the slider's range, where cumulative mode
     * would hide a post the viewer just made.
     */
    add(post) {
        const time = parse_server_date(post.createdAt)?.getTime();

        if (!Number.isFinite(time)) {
            return;
        }

        this.#posts.push({ post, time });

        // Recomputes from every known post rather than folding into the current
        // bounds, which would drag the span back to the epoch if the control
        // had never started and its bounds were still zero.
        this.#activate();
    }

    #bind() {
        this.#range.addEventListener('input', () => {
            this.#stop();
            this.#apply();
        });

        this.#element.querySelectorAll('.MapScrubberModeButton').forEach((button) => {
            button.addEventListener('click', () => {
                this.#mode = button.dataset.mode;
                this.#element.querySelectorAll('.MapScrubberModeButton').forEach((other) => {
                    other.classList.toggle('Active', other === button);
                });
                this.#apply();
            });
        });

        this.#element.querySelector('.MapScrubberPlay').addEventListener('click', () => this.#togglePlay());
    }

    #togglePlay() {
        if (this.#timer !== null) {
            this.#stop();
            return;
        }

        // Replaying from where the handle already sits would end instantly when
        // it's parked at "now", which is where it starts.
        if (Number(this.#range.value) >= MapScrubber.#STEPS) {
            this.#range.value = 0;
        }

        this.#element.querySelector('.MapScrubberPlay').textContent = Strings.for('MapScrubber', { pause: 'Pause' }).pause;

        this.#timer = setInterval(() => {
            const next = Number(this.#range.value) + MapScrubber.#STEPS / 120;

            if (next >= MapScrubber.#STEPS) {
                this.#range.value = MapScrubber.#STEPS;
                this.#apply();
                this.#stop();
                return;
            }

            this.#range.value = next;
            this.#apply();
        }, MapScrubber.#PLAY_MS);
    }

    #stop() {
        if (this.#timer !== null) {
            clearInterval(this.#timer);
            this.#timer = null;
        }

        this.#element.querySelector('.MapScrubberPlay').textContent = Strings.for('MapScrubber', { play: 'Play' }).play;
    }

    /** The moment the handle currently points at. */
    #handleTime() {
        const fraction = Number(this.#range.value) / MapScrubber.#STEPS;

        return this.#first + (this.#last - this.#first) * fraction;
    }

    #apply() {
        const at = this.#handleTime();
        const span = (this.#last - this.#first) * MapScrubber.#WINDOW_FRACTION;

        const visible = this.#mode === 'window'
            ? this.#posts.filter((entry) => Math.abs(entry.time - at) <= span)
            : this.#posts.filter((entry) => entry.time <= at);

        const words = Strings.for('MapScrubber', {
            cumulativeLabel: { one: 'Posted up to {date} — {count} post', other: 'Posted up to {date} — {count} posts' },
            windowLabel: { one: 'Posted around {date} — {count} post', other: 'Posted around {date} — {count} posts' },
        });

        const forms = this.#mode === 'window' ? words.windowLabel : words.cumulativeLabel;

        this.#label.textContent = Strings.plural(forms, visible.length).replace('{date}', MapScrubber.#formatDate(at));

        this.#onChange(visible.map((entry) => entry.post));
    }

    /**
     * Through DateFormat rather than toLocaleDateString(): that renders in
     * the browser's own language from the browser's own tables, and this
     * label sits on a page whose every other date is the site's locale
     * reading the locale file.
     */
    static #formatDate(time) {
        return DateFormat.short(new Date(time));
    }

}
