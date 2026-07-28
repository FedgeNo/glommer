import { Cookie } from '/Cookie.js';

export class ClientConfig {
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
        const raw = this._getCookie('APP-CONFIG');
        if (!raw) {
            // Sensible defaults when cookie is missing (saved page, logged‑out, etc.)
            return {
                currentUserId: null,
                currentUserUsername: null,
                currentUserSkinTone: null,
                currentUserCanModerate: false,
                siteURL: window.location.origin,
                serverTime: Date.now(),
                WSPort: null,
                carouselEagerItems: 3,
                needsMath: false,
            };
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.error('Invalid APP-CONFIG cookie:', e);
            return {};
        }
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
