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

            Strings.use(module.STRINGS);
        } catch {
            // No words is not a reason to render nothing: a twin asking for a
            // string it has not got falls back the same way a missing key
            // does, which is to what the caller passes as the English.
            Strings.use({});
        }
    }

    /** The words, handed over directly - the counterpart to the server's
     *  Strings::useLocale(), for whatever is not asking a browser. */
    static use(table) {
        Strings.#table = table || {};
    }

    /**
     * What one class says. The caller passes the English it was written with,
     * which is what comes back when this locale has no words for that class -
     * the same fall-back the server does, for the same reason.
     */
    static for(name, english = {}) {
        return Strings.#merge(english, Strings.#table[name] || {});
    }

    /**
     * What array_replace_recursive does on the server, because a sentence is a
     * list of named pieces and a locale may have translated one of them and not
     * another. Replacing the entry wholesale would drop the pieces nobody got
     * to yet, so a half-translated sentence would lose its English words here
     * while still reading correctly server-side.
     */
    static #merge(base, over) {
        const merged = { ...base };

        for (const [key, value] of Object.entries(over)) {
            merged[key] = Strings.#isTable(value) && Strings.#isTable(base[key])
                ? Strings.#merge(base[key], value)
                : value;
        }

        return merged;
    }

    static #isTable(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }
}
