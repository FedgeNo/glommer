export class ClientConfig {
    /** Parsed once - the block it comes from doesn't change within a page. */
    static #cached = null;

    /** @returns {string|null} */
    static _readBlock() {
        return document.getElementById('ClientConfig')?.textContent ?? null;
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

        const raw = this._readBlock();
        if (!raw) {
            // Sensible defaults when the block is missing (saved page, etc.)
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
                // page normally carries.
                carouselEagerItems: 5,
                // Empty rather than a guessed list: without the config there is
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
            console.error('Invalid ClientConfig block:', e);
            return {};
        }

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
