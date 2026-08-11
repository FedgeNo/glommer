import { Cookie } from '/scripts/Cookie.js';

export class ClientConfig {
    /** Parsed once - the cookie doesn't change within a page. */
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
            return ClientConfig.#defaults();
        }

        try {
            // The defaults sit underneath rather than beside: a page whose
            // cookie was written before a value existed still answers for it,
            // which is otherwise a key that reads undefined until the next
            // navigation rewrites the cookie.
            ClientConfig.#cached = { ...ClientConfig.#defaults(), ...JSON.parse(raw) };
        } catch (e) {
            console.error('Invalid APP-CONFIG cookie:', e);

            return ClientConfig.#defaults();
        }

        return ClientConfig.#cached;
    }

    /**
     * What a page says about itself when its cookie cannot: one that was saved,
     * one served before a value was added, one whose cookie will not parse.
     */
    static #defaults() {
        return {
            currentUserId: null,
            currentUserUsername: null,
            currentUserSkinTone: null,
            showSensitiveMedia: false,
            currentUserCanModerate: false,
            siteURL: window.location.origin,
            serverTime: Date.now(),
            WSPort: null,
            // Mirrors Carousel::INITIAL_EAGER_ITEMS, which is what the cookie
            // normally carries.
            carouselEagerItems: 5,
            // Empty rather than a guessed list: without the cookie there is no
            // composer to offer durations in, and inventing them here would be
            // a second definition of what the server accepts.
            pollDurations: {},
            pollMaxOptions: 4,
            // Mirrors QuotedPost::DESCRIPTION_MAX_LENGTH.
            quotedPostMaxLength: 280,
            // Mirrors Place::MINIMUM_QUERY_LENGTH.
            placeSearchMinimumLength: 3,
            needsMath: false,
        };
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
