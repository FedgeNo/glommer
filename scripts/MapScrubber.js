import { parse_server_date } from '/scripts/utils.js';

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

        if (this.#posts.length < 2) {
            // One post has no span to scrub through; leave the control hidden
            // and the map showing everything.
            return;
        }

        const times = this.#posts.map((entry) => entry.time);
        this.#first = Math.min(...times);
        this.#last = Math.max(...times);

        this.#element.querySelector('.MapScrubberFirst').textContent = MapScrubber.#formatDate(this.#first);
        this.#element.querySelector('.MapScrubberLast').textContent = MapScrubber.#formatDate(this.#last);

        this.#bind();
        this.#element.classList.add('Active');
        this.#apply();
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

        this.#element.querySelector('.MapScrubberPlay').textContent = 'Pause';

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

        this.#element.querySelector('.MapScrubberPlay').textContent = 'Play';
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

        this.#label.textContent = this.#mode === 'window'
            ? 'Posted around ' + MapScrubber.#formatDate(at) + ' — ' + visible.length + ' post' + (visible.length === 1 ? '' : 's')
            : 'Posted up to ' + MapScrubber.#formatDate(at) + ' — ' + visible.length + ' post' + (visible.length === 1 ? '' : 's');

        this.#onChange(visible.map((entry) => entry.post));
    }

    static #formatDate(time) {
        return new Date(time).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }
}
