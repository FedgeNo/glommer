import { ClientConfig } from '/scripts/ClientConfig.js';

/**
 * The words the client twins say, which are the same words the server says.
 *
 * Fetched once as a module from /locales/{locale}.js, which the server builds
 * out of the same src/locales/ table it reads itself. A second hand-kept copy
 * of every string would be the easiest drift there is between the two
 * renderers, and they already work hard not to drift.
 *
 * load() is awaited by main.js before any twin renders, so for() can stay
 * synchronous - a renderer that had to await its own words would have to be
 * async all the way up.
 */
export class Strings {
    static #table = {};

    static async load() {
        const locale = ClientConfig.get('locale') || 'en';

        try {
            const module = await import('/locales/' + locale + '.js');

            Strings.#table = module.STRINGS || {};
        } catch {
            // No words is not a reason to render nothing: a twin asking for a
            // string it has not got falls back the same way a missing key
            // does, which is to what the caller passes as the English.
            Strings.#table = {};
        }
    }

    /**
     * What one class says. The caller passes the English it was written with,
     * which is what comes back when this locale has no words for that class -
     * the same fall-back the server does, for the same reason.
     */
    static for(name, english = {}) {
        return { ...english, ...(Strings.#table[name] || {}) };
    }
}
