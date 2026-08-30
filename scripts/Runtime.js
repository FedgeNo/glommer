/** Shared browser runtime: configuration, localization, DOM utilities, and API plumbing. */

// dom.js
// dom.js – import once to enable parent.appendWithSpace(child) and
// parent.insertBeforeWithSpace(newNode, refNode)

const BLOCK_TAGS = new Set([
    'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI',
    'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER',
]);

function isWhitespaceText(node) {
    return node && node.nodeType === Node.TEXT_NODE && /^\s*$/.test(node.data);
}
function isInlineElement(node) {
    return node && node.nodeType === Node.ELEMENT_NODE && !BLOCK_TAGS.has(node.tagName);
}

function insideQuillEditor(node) {
    return node && node.closest && node.closest('.ql-editor');
}

function spaceBetween(parent, prev, next) {
    const prevText = (prev.innerText || prev.textContent || '').trimEnd();
    const nextText = (next.innerText || next.textContent || '').trimStart();
    if (prevText.length > 0 && !/\s$/.test(prevText) &&
        nextText.length > 0 && !/^\s/.test(nextText)) {
        parent.insertBefore(document.createTextNode(' '), next);
    }
}

// ----------------------------------------------------------------
// appendWithSpace
// ----------------------------------------------------------------

Element.prototype.appendWithSpace = function (child) {
    if (child.nodeType !== Node.ELEMENT_NODE) {
        return this.appendChild(child);
    }
    if (insideQuillEditor(child) || insideQuillEditor(this)) {
        return this.appendChild(child);
    }

    const prev = this.lastChild;
    const result = this.appendChild(child);

    if (BLOCK_TAGS.has(child.tagName)) {
        // newline before
        if (prev && !isWhitespaceText(prev) && prev.nextSibling === child) {
            this.insertBefore(document.createTextNode('\n'), child);
        }
        // newline after
        const next = child.nextSibling;
        if (!isWhitespaceText(next)) {
            if (next) {
                this.insertBefore(document.createTextNode('\n'), next);
            } else {
                this.appendChild(document.createTextNode('\n'));
            }
        }
    } else if (isInlineElement(prev) && isInlineElement(child)) {
        spaceBetween(this, prev, child);
    }
    return result;
};

// ----------------------------------------------------------------
// insertBeforeWithSpace
// ----------------------------------------------------------------

Element.prototype.insertBeforeWithSpace = function (newNode, refNode) {
    if (newNode.nodeType !== Node.ELEMENT_NODE) {
        return this.insertBefore(newNode, refNode);
    }
    if (insideQuillEditor(newNode) || insideQuillEditor(this)) {
        return this.insertBefore(newNode, refNode);
    }

    const prev = refNode ? refNode.previousSibling : this.lastChild;
    const next = refNode;  // the node that will become newNode's next sibling

    const result = this.insertBefore(newNode, refNode);

    if (BLOCK_TAGS.has(newNode.tagName)) {
        // newline before
        if (prev && !isWhitespaceText(prev) && prev.nextSibling === newNode) {
            this.insertBefore(document.createTextNode('\n'), newNode);
        }
        // newline after
        const after = newNode.nextSibling; // could be next if it was refNode, or something else
        if (!isWhitespaceText(after)) {
            if (after) {
                this.insertBefore(document.createTextNode('\n'), after);
            } else {
                this.appendChild(document.createTextNode('\n'));
            }
        }
    } else {
        // inline spacing
        if (isInlineElement(prev) && isInlineElement(newNode)) {
            spaceBetween(this, prev, newNode);
        }
        if (isInlineElement(newNode) && isInlineElement(next)) {
            spaceBetween(this, newNode, next);
        }
    }
    return result;
};

// Cookie.js
/**
 * Static helper for reading a single cookie.
 *
 *  Cookie.get('CSRF-TOKEN') → token string or null
 */
export class Cookie {
    /**
     * Read a cookie value by name. Returns null if the cookie is not set.
     * @param {string} name
     * @returns {string|null}
     */
    static get(name) {
        const match = document.cookie.match(
            new RegExp(
                '(?:^|; )' +
                    name.replace(/([.$?*|{}()[\]\\\/+^])/g, '\\$1') +
                    '=([^;]*)'
            )
        );
        return match ? decodeURIComponent(match[1]) : null;
    }
}

// ClientConfig.js
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

// ReadyHandler.js
export class ReadyHandler {
    static #tasks = [];
    static #fired = false;

    // Run the queued tasks when the DOM is ready.
    static {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () =>
                ReadyHandler.#runAll()
            );
        } else {
            ReadyHandler.#runAll();
        }
    }

    /**
     * Register a function to be called when the DOM is ready.
     * If the DOM is already ready, the function is invoked immediately.
     *
     * @param {() => void} fn
     */
    static add(fn) {
        if (ReadyHandler.#fired) {
            fn();
        } else {
            ReadyHandler.#tasks.push(fn);
        }
    }

    static #runAll() {
        ReadyHandler.#fired = true;
        ReadyHandler.#tasks.forEach((fn) => fn());
        ReadyHandler.#tasks = []; // release references
    }
}

// Strings.js
/**
 * The words the client twins say, which are the same words the server says.
 *
 * Fetched once from /locales/{locale}.json - the same file the server reads,
 * not a copy built for the browser. A second hand-kept set of every string
 * would be the easiest drift there is between the two renderers, and they
 * already work hard not to drift.
 *
 * load() is awaited by main.js before any twin renders, so for() can stay
 * synchronous - a renderer that had to await its own words would have to be
 * async all the way up.
 */
export class Strings {
    static #table = {};
    static #locale = 'en';

    static async load() {
        const locale = ClientConfig.get('locale') || 'en';

        try {
            const response = await fetch('/locales/' + encodeURIComponent(locale) + '.json');

            // Checked before it is parsed, the same as Api does: an error page
            // that happens to be readable as JSON would install itself as the
            // whole string table, and every twin would say pieces of it.
            if (!response.ok) {
                throw new Error('locales/' + locale + '.json answered ' + response.status);
            }

            Strings.useLocale(await response.json(), locale);
        } catch (error) {
            // No words is not a reason to render nothing: a twin asking for a
            // string it has not got falls back the same way a missing key
            // does, which is to what the caller passes as the English. The
            // locale still stands, so counted phrasings that fell back to their
            // English are at least counted this language's way.
            //
            // Said out loud, because a whole language quietly reverting to
            // English is the one thing nobody thinks to look for - the server
            // logs the same failure for the same reason.
            console.error('Strings: falling back to English.', error);

            Strings.useLocale({}, locale);
        }
    }

    /**
     * The words and the language they are, for whatever is not asking a
     * browser - the same name as the server's Strings::useLocale() because it
     * is the same call, the way every twin here shares its object's name.
     *
     * Both together, because they are one fact: a table of Polish with the
     * language still set to English picks singulars for counts Polish has three
     * other forms for, and reads as a bug in the translation rather than in the
     * call that installed it.
     */
    static useLocale(table, locale = 'en') {
        Strings.#table = table || {};
        Strings.#locale = locale || 'en';
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
     * One of a set of phrasings keyed by CLDR category, chosen by how many of
     * the thing there are - the client's Strings::plural().
     *
     * The category comes from Intl.PluralRules, which is the browser's copy of
     * CLDR - the same data ICU answers the server's PluralRule with, so a new
     * language needs no rule written for it on either side.
     *
     * The two agree for as long as the locale is one they both know. Neither
     * fails on a code it does not: this falls back to the runtime's own
     * locale and ICU falls back to root, which are different answers, so a
     * made-up code counts one way here and another on the server. A test
     * keeps every locale file to a name ICU knows for that reason.
     *
     * Unlike the server's, this substitutes {count} - every caller here wants
     * it, and a phrasing that leaves the number out simply has no token to fill.
     *
     * ?? rather than ||, and replaceAll rather than replace, to match
     * Strings::plural() exactly: an empty phrasing is a language saying there
     * are no words for this here, which || would throw away in favour of
     * another form, and a phrasing naming the count twice would otherwise
     * fill one of them and print the token at the reader for the other.
     */
    static plural(forms, count) {
        const category = new Intl.PluralRules(Strings.#locale).select(count);
        // The same chain as Strings::plural(), reset() included: a set holding
        // only a "one" would otherwise say its text on the server and nothing
        // here, in the same place on the same page.
        const chosen = forms[category] ?? forms.other ?? Object.values(forms)[0] ?? '';

        return chosen.replaceAll('{count}', String(count));
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

// DateFormat.js
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

// utils.js
// ----------------------------------------------------------------
// Date parsing
// ----------------------------------------------------------------
export function parse_server_date(dateString) {
    const normalized = dateString.includes('T')
        ? dateString
        : dateString.replace(' ', 'T');
    return new Date(
        /Z|[+-]\d\d:\d\d$/.test(normalized)
            ? normalized
            : normalized + 'Z'
    );
}

// ----------------------------------------------------------------
// CSRF headers
// ----------------------------------------------------------------
export function csrf_headers(extra = {}) {
    const token = Cookie.get('CSRF-TOKEN');
    return Object.assign({ 'X-CSRF-Token': token || '' }, extra);
}

// ----------------------------------------------------------------
// Text
// ----------------------------------------------------------------

/**
 * The same cut as PHP's truncate() in src/functions.php. A post can reach the
 * page from either renderer, so the two have to land on the same character or
 * the writing changes length when the page is reloaded.
 *
 * Counted in code points, which is what mb_substr counts; backing up to the
 * last space is also what keeps the cut out of the middle of an emoji, since
 * anything long enough to truncate has a space to retreat to.
 */
export function truncate(text, length = 50) {
    const characters = Array.from(text);

    if (characters.length <= length) return text;

    let cut = characters.slice(0, length).join('');
    const last_space = cut.lastIndexOf(' ');

    if (last_space !== -1) cut = cut.slice(0, last_space);

    return cut.replace(/\s+$/, '') + '…';
}

// ----------------------------------------------------------------
// DOM helpers
// ----------------------------------------------------------------
export function list_item(child) {
    const item = document.createElement('li');
    item.appendWithSpace(child);
    return item;
}

/**
 * The list inside a container, built over the empty-state notice standing
 * there if this is the first item to arrive. A list with nothing in it isn't
 * rendered at all - only the notice saying so - so there is nothing to append
 * to until something asks for one.
 *
 * Null when the container holds neither, which is how a caller tells that this
 * list isn't on the page at all rather than being empty.
 */
export function list_in(container, classes) {
    if (!container) return null;

    const existing = container.querySelector('.' + classes.split(' ')[0]);

    if (existing) return existing;

    const notice = container.querySelector('.Notice');

    if (!notice) return null;

    const list = document.createElement('ul');
    list.className = classes;
    notice.replaceWith(list);

    return list;
}

/**
 * Keeps the browser chrome's own colour in step with the active theme -
 * read from the live --paper token, so every theme (and Match System) is
 * covered without a second list of colours anywhere.
 */
export function sync_theme_color() {
    const paper = getComputedStyle(document.documentElement).getPropertyValue('--paper').trim();

    if (paper === '') return;

    let meta = document.querySelector('meta[name="theme-color"]');

    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'theme-color';
        document.head.appendChild(meta);
    }

    meta.content = paper;
}

// DOMUtils.js
export class DOMUtils {
    /**
     * Slide out an element (remove with animation).
     * @param {HTMLElement} element – any node inside the item to remove
     */
    static slideOut(element) {
        if (!element) return;
        const item = element.closest('li') || element;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            item.remove();
            return;
        }
        item.style.height = item.getBoundingClientRect().height + 'px';
        item.classList.add('SlidingOut');
        void item.offsetHeight; // force reflow
        item.style.height = '0';
        setTimeout(() => item.remove(), 250); // original SLIDE_OUT_MS (200) + 50
    }
}

// ClickHandler.js
export class ClickHandler {
    /**
     * @param {{ selector: string, handler: (el: Element, event: Event) => void }[]} handlers
     */
    static init(handlers) {
        document.addEventListener('click', (event) => {
            for (const { selector, handler } of handlers) {
                const target = event.target.closest(selector);
                if (target) {
                    handler(target, event);
                    return; // only first matching handler runs
                }
            }
        });
    }
}

// FormErrors.js
/**
 * Puts a server's refusal under the input it is about.
 *
 * A toast says "that is already your email address" at the edge of the screen
 * and leaves somebody to work out which of five boxes it means - awkward
 * looking at it, hopeless not looking at it. Here the reason sits under its
 * own box, tied to it by aria-describedby so it is read out on reaching the
 * box, and the box is marked invalid so the eye lands on which one before
 * reading why.
 *
 * All of them at once, too. An endpoint that checks one thing, refuses, and
 * makes somebody press the button again to be told the next thing wastes five
 * round trips on one form; these arrive together.
 *
 * The markup mirrors InputField::errorElement() on the server, which renders
 * the same thing for a refusal known before the page is drawn.
 */
export class FormErrors {
    /** Takes down whatever the last attempt said. */
    static clear(form) {
        if (!form) return;

        form.querySelectorAll('.FieldError').forEach((error) => error.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        });
    }

    /**
     * Marks each named field with its reason.
     *
     * @param {Element} form
     * @param {Object<string, string>} fields reason by field name
     * @returns {boolean} whether any of them was found to mark
     */
    static show(form, fields) {
        if (!form || !fields) return false;

        FormErrors.clear(form);

        let first = null;

        for (const [name, reason] of Object.entries(fields)) {
            const field = form.querySelector('[name="' + name + '"]');

            if (!field) continue;

            const id = name + 'Error';

            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', id);

            const error = document.createElement('p');
            error.className = 'FieldError';
            error.id = id;
            error.textContent = reason;

            // After the input, inside whatever wraps the two - which is where
            // the server puts it, and what keeps it with its own field rather
            // than at the foot of the form.
            field.parentNode?.insertBefore(error, field.nextSibling);

            if (first === null) first = field;
        }

        // Straight to the first thing to fix, so nobody has to go looking for
        // where the form went wrong.
        first?.focus();

        return first !== null;
    }
}

// Toast.js
export class Toast {
    static container = null;

    static getContainer() {
        if (!Toast.container) {
            Toast.container = document.createElement('div');
            Toast.container.className = 'ToastContainer';
            document.body.appendWithSpace(Toast.container);
        }
        return Toast.container;
    }

    static show(message) {
        const container = Toast.getContainer();

        const toast = document.createElement('div');
        toast.className = 'Toast';
        toast.setAttribute('role', 'alert');

        const text = document.createElement('div');
        text.className = 'ToastMessage';

        if (message instanceof Node) {
            text.appendWithSpace(message);
        } else {
            text.textContent = message;
        }

        toast.appendWithSpace(text);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'ToastCloseButton';
        closeButton.setAttribute('aria-label', Strings.for('Toast').dismiss || '');
        closeButton.textContent = '×';
        // Bind directly – no delegation needed
        closeButton.addEventListener('click', () => Toast.dismiss(toast));
        toast.appendWithSpace(closeButton);

        container.appendWithSpace(toast);

        requestAnimationFrame(() => {
            toast.classList.add('Active');
        });

        setTimeout(() => Toast.dismiss(toast), 6000);

        return toast;
    }

    static dismiss(element) {
        const toast = element.closest?.('.Toast') || element;
        if (!toast?.classList.contains('Active')) return;

        toast.classList.remove('Active');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        setTimeout(() => toast.remove(), 300);
    }
}

// Working.js
/**
 * A control that is waiting on the server, saying so.
 *
 * Disabling a button stops a second press but tells the reader nothing: the
 * button greys slightly and then sits there, and on anything slow - a
 * translation is a round trip to a model, and can take many seconds - it reads
 * as a press that did not land. So a control that is working throbs while it
 * does, and says the same thing to assistive tech as aria-busy.
 *
 * Paired calls rather than a wrapper, because the callers already have the
 * try/finally that guarantees the second one: whatever goes wrong in between,
 * a button must never be left disabled and pulsing forever.
 */
export class Working {
    /** Stops a second press, and shows that the first one landed. */
    static start(control) {
        if (!control) return;

        control.disabled = true;
        control.classList.add('Working');
        control.setAttribute('aria-busy', 'true');
    }

    /** Gives the control back. */
    static stop(control) {
        if (!control) return;

        control.disabled = false;
        control.classList.remove('Working');
        control.removeAttribute('aria-busy');
    }
}

// Api.js
/**
 * The one way this site talks to its own server.
 *
 * Nothing calls fetch directly. Everything that did was reimplementing the
 * same four things - the CSRF header, the ok check, the unwrapping of
 * data.response, and staying quiet when a request is deliberately aborted -
 * and each copy was a chance to get one of them subtly wrong.
 *
 * Two ways in. post() is what almost everything wants: the answer, or null,
 * with a toast raised on the caller's behalf. request() is for the few that
 * need the status code itself - a scroller telling a rate limit apart from a
 * real failure, a diagnostic panel whose whole job is reporting which refusal
 * came back - and it never toasts, because a caller reading the status is
 * handling the outcome itself.
 */
export class Api {
    /**
     * A POST, reported in full.
     *
     * Never throws and never toasts. An aborted request is not a failure worth
     * reporting - a typeahead cancels one on every keystroke - so it comes
     * back marked as aborted and otherwise empty.
     *
     * @param {string} path – API path, e.g. '/api/create-post'
     * @param {*} [payload] – JSON-serialisable body, or FormData
     * @param {{ signal?: AbortSignal, keepalive?: boolean }} [options] –
     *   `keepalive` keeps the request alive past the page that started it,
     *   which is the only way a hangup sent while somebody closes the tab
     *   actually leaves the browser
     * @returns {Promise<{ ok: boolean, status: number, data: ?object, error: ?string, aborted: boolean }>}
     */
    static async request(path, payload, { signal, keepalive } = {}) {
        let response;

        try {
            // FormData goes as it is. Encoding it as JSON would throw the
            // files away, and setting a Content-Type would override the
            // multipart boundary the browser generates - which is the one
            // header that must be left alone.
            const is_form_data = payload instanceof FormData;

            response = await fetch(ClientConfig.siteURL() + path, {
                method: 'POST',
                headers: csrf_headers(
                    payload !== undefined && !is_form_data
                        ? { 'Content-Type': 'application/json' }
                        : undefined
                ),
                body: payload === undefined ? undefined : (is_form_data ? payload : JSON.stringify(payload)),
                signal,
                keepalive,
            });
        } catch (error) {
            const aborted = error.name === 'AbortError';

            return {
                ok: false,
                status: 0,
                data: null,
                error: aborted ? null : Strings.for('Api').networkError || '',
                aborted,
            };
        }

        let body = null;

        try {
            body = await response.json();
        } catch (_) {
            // Not JSON, which for this server means something went wrong
            // upstream of the endpoint - reported below as a plain failure.
        }

        return {
            ok: response.ok && body !== null,
            status: response.status,
            data: body,
            error: body?.error ?? (response.ok ? null : Strings.for('Api').genericError || ''),
            aborted: false,
        };
    }

    /**
     * A POST, answered.
     *
     * @param {string} path – API path, e.g. '/api/create-post'
     * @param {*} [payload] – JSON-serialisable body, or FormData
     * @param {{ signal?: AbortSignal, form?: Element, quiet?: boolean, keepalive?: boolean }} [options] –
     *   `form` writes a refusal that names its fields under those fields
     *   instead of throwing it at the corner of the screen; `quiet` says
     *   nothing at all, for a call whose failure is not the reader's business
     *   - a background token refresh, a seen-marker, a typeahead
     * @returns {Promise<object|null>} – the response, or null on any failure
     */
    static async post(path, payload, { signal, form, quiet, keepalive } = {}) {
        const result = await Api.request(path, payload, { signal, keepalive });

        if (result.ok) {
            // Whatever the last attempt complained about is answered.
            if (form) FormErrors.clear(form);

            return result.data.response;
        }

        // An abort is somebody changing their mind, not a fault.
        if (result.aborted || quiet) {
            return null;
        }

        // A refusal that names the inputs it is about belongs on those inputs.
        // The toast is the fallback for everything else - and for a named field
        // this form does not actually have, which would otherwise be a
        // complaint nobody could see.
        if (form && result.data?.fields && FormErrors.show(form, result.data.fields)) {
            return null;
        }

        Toast.show(result.error || Strings.for('Api').genericError || '');

        return null;
    }
}
