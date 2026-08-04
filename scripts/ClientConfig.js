import { Cookie } from '/scripts/Cookie.js';

export class ClientConfig {
    /** The parsed config, held here because the cookie is deleted on first read. */
    static #cached = null;

    /** @returns {string|null} */
    static _getCookie(name) {
        return Cookie.get(name);
    }

    /**
     * Return the full configuration object.
     * @returns {{
     *   currentUserId: number|null,
     *   currentUserUsername: string|null,
     *   currentUserSkinTone: number|null,
     *   currentUserCanModerate: boolean,
     *   siteURL: string,
     *   serverTime: number,
     *   WSPort: number|null,
     *   carouselEagerItems: number,
     *   needsMath: boolean
     * }}
     */
    static all() {
        if (ClientConfig.#cached !== null) {
            return ClientConfig.#cached;
        }

        const raw = this._getCookie('APP-CONFIG');
        if (!raw) {
            // Sensible defaults when cookie is missing (saved page, logged‑out, etc.)
            return {
                currentUserId: null,
                currentUserUsername: null,
                currentUserSkinTone: null,
                showSensitiveMedia: false,
                currentUserCanModerate: false,
                siteURL: window.location.origin,
                serverTime: Date.now(),
                WSPort: null,
                // Mirrors Carousel::INITIAL_EAGER_ITEMS, which is what the
                // cookie normally carries.
                carouselEagerItems: 5,
                // Empty rather than a guessed list: without the cookie there is
                // no composer to offer durations in, and inventing them here
                // would be a second definition of what the server accepts.
                pollDurations: {},
                pollMaxOptions: 4,
                needsMath: false,
            };
        }
        try {
            ClientConfig.#cached = JSON.parse(raw);
        } catch (e) {
            console.error('Invalid APP-CONFIG cookie:', e);
            return {};
        }

        // The cookie is a one-way delivery from the render that just
        // happened: parsed once, then dropped, so its weight (per-page keys,
        // ICE servers, ...) doesn't ride along on every request this page
        // makes afterwards. The next page render sets a fresh one.
        document.cookie = 'APP-CONFIG=; Max-Age=0; path=/; secure; samesite=Strict';

        return ClientConfig.#cached;
    }

    /**
     * Get a single config value by key.
     * @param {string} key
     * @returns {*}
     */
    static get(key) {
        return this.all()[key];
    }

    /**
     * Convenience: the base URL of the site, e.g. 'https://glommer.org'.
     * @returns {string}
     */
    static siteURL() {
        return this.get('siteURL');
    }

    /**
     * Convenience: the WebSocket port number.
     * @returns {number|null}
     */
    static wsPort() {
        return this.get('WSPort');
    }
}
