import {
    Api,
    ClientConfig,
    DOMUtils,
    DateFormat,
    ReadyHandler,
    Strings,
    Toast,
    Working,
    list_in,
    list_item,
    parse_server_date,
    truncate,
} from '/scripts/Runtime.js';
import { EMOJI_SHORTCODES } from '/emoji-shortcodes.js';

const STRUCTURAL_PROPERTIES = new Set([
    'tagName',
    'class',
    'attributes',
    'contents',
    'rendered',
    'items',
    'contentType',
]);
const PROPERTY_DEFAULTS = new WeakMap();

function copyDefault(value) {
    if (Array.isArray(value)) {
        return value.map(copyDefault);
    }

    if (value !== null && typeof value === 'object' && Object.getPrototypeOf(value) === Object.prototype) {
        return Object.fromEntries(Object.entries(value).map(([name, item]) => [name, copyDefault(item)]));
    }

    return value;
}

/** The browser-side DOM-building core, mirroring PHP's HTMLObject contract. */
export class HTMLObject {
    static tagName = null;
    static className = null;
    static properties = {
        id: null,
    };

    #rendered = false;

    constructor(properties = null) {
        this.class = null;
        this.attributes = {};
        this.contents = [];
        const defaults = this.constructor.propertyDefaults();

        for (const [name, value] of Object.entries(defaults)) {
            this[name] = copyDefault(value);
        }

        if (properties !== null) {
            for (const [name, value] of Object.entries(properties)) {
                if (Object.hasOwn(defaults, name)) {
                    this[name] = value;
                }
            }
        }
    }

    static fromData(properties) {
        return new this(properties);
    }

    static propertyDefaults() {
        if (PROPERTY_DEFAULTS.has(this)) {
            return PROPERTY_DEFAULTS.get(this);
        }

        const levels = [];

        for (let type = this; type && type !== Function.prototype; type = Object.getPrototypeOf(type)) {
            levels.push(type);

            if (type === HTMLObject) break;
        }

        const defaults = {};

        levels.reverse().forEach(type => {
            if (!Object.hasOwn(type, 'properties')) return;

            for (const [name, value] of Object.entries(type.properties)) {
                if (!STRUCTURAL_PROPERTIES.has(name)) {
                    defaults[name] = value;
                }
            }
        });

        PROPERTY_DEFAULTS.set(this, Object.freeze(defaults));

        return defaults;
    }

    static compoundedClassName() {
        const levels = [];

        for (let type = this; type && type !== HTMLObject; type = Object.getPrototypeOf(type)) {
            levels.push(type);
        }

        let first = null;

        levels.forEach((type, index) => {
            if (Object.hasOwn(type, 'className') && type.className !== null) {
                first = index;
            }
        });

        if (first === null) return '';

        const names = [];

        for (let index = first; index >= 0; index--) {
            const type = levels[index];
            names.push(Object.hasOwn(type, 'className') && type.className !== null ? type.className : type.name);
        }

        return names.join(' ');
    }

    addContent(item) {
        this.contents.push(item);
    }

    addContents(items) {
        items.forEach(item => this.addContent(item));
    }

    toDOM() {
        this.#markRendered();

        const tag_name = this.constructor.tagName;

        if (!tag_name) {
            throw new Error(this.constructor.name + ' has no tagName.');
        }

        const element = document.createElement(tag_name);

        if (this.id !== null) {
            element.setAttribute('id', this.id);
        }

        const inherited = this.constructor.compoundedClassName();
        const inherited_names = inherited.split(' ').filter(Boolean);
        const added = String(this.class || '')
            .split(' ')
            .filter(name => name && !inherited_names.includes(name));
        const class_name = [inherited, ...added].filter(Boolean).join(' ');

        if (class_name !== '') {
            element.setAttribute('class', class_name);
        }

        for (const [name, value] of Object.entries(this.attributes)) {
            if (value === null) {
                console.warn(this.constructor.name + ' left the "' + name + '" attribute null');
                continue;
            }

            element.setAttribute(name, value);
        }

        this.contents.forEach(item => {
            const node = this.#contentToNode(item);

            if (node !== null) {
                element.appendChild(node);
            }
        });

        return element;
    }

    /** Compatibility with the existing client renderer API during migration. */
    toElement() {
        return this.toDOM();
    }

    #markRendered() {
        if (this.#rendered) {
            throw new Error(this.constructor.name + ' produced output twice; build a fresh instance per output step.');
        }

        this.#rendered = true;
    }

    #contentToNode(item) {
        if (item instanceof HTMLObject) {
            return item.toDOM();
        }

        if (typeof item === 'string') {
            return document.createTextNode(item);
        }

        if (item instanceof Node) {
            return item;
        }

        return null;
    }
}

export class Anchor extends HTMLObject {
    static tagName = 'a';
    static properties = {
        href: null,
    };

    constructor(href = null, text = null) {
        super(href !== null && typeof href === 'object' ? href : { href });

        if (text !== null) {
            this.addContent(text);
        }
    }

    toDOM() {
        if (this.href !== null) {
            this.attributes.href = this.href;
        }

        return super.toDOM();
    }
}

export class Article extends HTMLObject {
    static tagName = 'article';
}

export class Button extends HTMLObject {
    static tagName = 'button';
    static properties = {
        type: 'button',
    };

    toDOM() {
        this.attributes.type = this.type;

        return super.toDOM();
    }
}

export class Div extends HTMLObject {
    static tagName = 'div';
}

export class Image extends HTMLObject {
    static tagName = 'img';
    static properties = {
        src: null,
        alt: null,
    };

    toDOM() {
        if (this.src !== null) {
            this.attributes.src = this.src;
        }

        if (this.alt !== null) {
            this.attributes.alt = this.alt;
        }

        return super.toDOM();
    }
}

export class Span extends HTMLObject {
    static tagName = 'span';
}

export class Time extends HTMLObject {
    static tagName = 'time';
    static properties = {
        datetime: null,
    };

    toDOM() {
        if (this.datetime !== null) {
            this.attributes.datetime = this.datetime;
        }

        return super.toDOM();
    }
}

class ButtonButton extends Button {
    static className = 'Button';
}

/** Browser twin of Avatar.php and its two concrete descendants. */
export class Avatar extends HTMLObject {
    static className = 'Avatar';
    static properties = {
        name: null,
        userId: 0,
    };

    static create(has_image, image_url, name, user_id) {
        const avatar = has_image && image_url !== null
            ? new AvatarImage({ imageURL: image_url })
            : new AvatarInitial();

        avatar.name = name;
        avatar.userId = user_id;

        return avatar;
    }

    static forUser(user) {
        if (!user) {
            return Avatar.create(false, null, null, 0);
        }

        return Avatar.create(Boolean(user.image), user.image, user.title || user.slug, user.userId);
    }
}

export class AvatarImage extends Avatar {
    static tagName = 'img';
    static properties = {
        imageURL: null,
    };

    toDOM() {
        this.attributes.src = String(this.imageURL ?? '');
        this.attributes.decoding = 'async';
        this.attributes.alt = String(this.name ?? '') + '\'s avatar';

        return super.toDOM();
    }
}

export class AvatarInitial extends Avatar {
    static tagName = 'div';

    toDOM() {
        this.attributes['aria-hidden'] = 'true';
        this.attributes.style = '--avatar-hue: ' + (Number(this.userId) * 137 % 360) + 'deg';
        const first_character = Array.from(String(this.name ?? ''))[0];
        this.addContent(first_character ? first_character.toUpperCase() : '?');

        return super.toDOM();
    }
}

class TrendingEntityCount extends Span {
    static className = 'TrendingEntityCount';
}

class TrendingEntityBanButton extends ButtonButton {
    toDOM() {
        this.attributes['data-entity-type'] = this.entityType;
        this.attributes['data-entity-value'] = this.entityValue;
        this.addContent(this.label);

        return super.toDOM();
    }
}

/** Browser twin of Entity.php. */
export class Entity extends Div {
    static className = 'Entity';
    static properties = {
        entityId: null,
        type: null,
        title: null,
        url: null,
        count: null,
        canModerate: false,
        banLabel: '',
    };

    toDOM() {
        const link = new Anchor(this.url, String(this.title ?? ''));
        link.class = 'TrendingEntityLink';

        if (this.count !== null && this.count !== undefined) {
            const count = new TrendingEntityCount();
            count.addContent(String(this.count));
            link.addContent(count);
        }

        this.addContent(link);

        if (this.canModerate) {
            const ban = new TrendingEntityBanButton();
            ban.entityType = this.type;
            ban.entityValue = this.title;
            ban.label = this.banLabel;
            this.addContent(ban);
        }

        return super.toDOM();
    }
}

/** Browser twin of RelativeTime.php plus its live relative-time behavior. */
export class RelativeTime extends Time {
    static className = 'RelativeTime';
    static properties = {
        dateString: null,
        fallbackFormat: 'longWithTime',
    };

    static #serverTimeOffset =
        (typeof ClientConfig.get('serverTime') === 'number'
            ? ClientConfig.get('serverTime')
            : 0) - Date.now();

    static #intervalId = null;
    static #running = false;

    constructor(date_string = null, fallback_format = 'longWithTime') {
        super({ dateString: date_string, fallbackFormat: fallback_format });
    }

    toDOM() {
        const date = parse_server_date(this.dateString);
        this.datetime = date.toISOString().replace(/\.\d{3}Z$/u, '+00:00');
        this.addContent(this.fallbackFormat === 'short'
            ? DateFormat.short(date)
            : RelativeTime.dateAndTime(this.dateString));

        return super.toDOM();
    }

    static format(dateString) {
        const target = parse_server_date(dateString);
        const diffSeconds = Math.round(
            (RelativeTime.#correctedNow() - target.getTime()) / 1000
        );

        const words = Strings.for('RelativeTime', {
            justNow: 'just now',
            minutes: { one: '{count}m ago', other: '{count}m ago' },
            hours: { one: '{count}h ago', other: '{count}h ago' },
            days: { one: '{count}d ago', other: '{count}d ago' },
        });

        if (diffSeconds < 60) return words.justNow;

        const diffMinutes = Math.round(diffSeconds / 60);
        if (diffMinutes < 60) return Strings.plural(words.minutes, diffMinutes);

        const diffHours = Math.round(diffMinutes / 60);
        if (diffHours < 24) return Strings.plural(words.hours, diffHours);

        const diffDays = Math.round(diffHours / 24);
        if (diffDays < 7) return Strings.plural(words.days, diffDays);

        return DateFormat.short(target);
    }

    static date(dateString) {
        return DateFormat.long(parse_server_date(dateString));
    }

    static dateAndTime(dateString) {
        return DateFormat.longWithTime(parse_server_date(dateString));
    }

    static refresh(root = document) {
        root.querySelectorAll('.RelativeTime').forEach(element => {
            if (!element.hasAttribute('datetime')) {
                const post = element.closest('.Post');
                if (!post) return;
                element.setAttribute('datetime', post.dataset.createdAt);
            }

            element.textContent = RelativeTime.format(element.getAttribute('datetime'));
        });
    }

    static init() {
        if (RelativeTime.#running) return;
        RelativeTime.#running = true;

        RelativeTime.refresh();
        RelativeTime.#intervalId = setInterval(() => RelativeTime.refresh(), 60000);
    }

    static stop() {
        if (RelativeTime.#intervalId !== null) {
            clearInterval(RelativeTime.#intervalId);
            RelativeTime.#intervalId = null;
        }

        RelativeTime.#running = false;
    }

    static now() {
        return RelativeTime.#correctedNow();
    }

    static #correctedNow() {
        return Date.now() + RelativeTime.#serverTimeOffset;
    }
}

class ToggleButtonLabel extends Span {
    static className = 'ToggleButtonLabel';
}

/** Browser twin of ToggleButton.php. */
export class ToggleButton extends ButtonButton {
    static className = 'ToggleButton';
    static properties = {
        labels: [],
        showing: null,
        pressable: true,
    };

    constructor(labels = [], class_name = null, pressable = true) {
        super({ labels, pressable });
        this.class = class_name;
    }

    toDOM() {
        const showing = this.showing ?? this.labels[0] ?? '';

        if (this.pressable) {
            this.attributes['aria-pressed'] = String(showing !== (this.labels[0] ?? ''));
        }

        for (const text of this.labels) {
            const label = new ToggleButtonLabel();
            label.class = text === showing ? null : 'Inactive';
            label.addContent(text);
            this.addContent(label);
        }

        return super.toDOM();
    }

    static build(labels, class_name, pressable = true) {
        return new ToggleButton(labels, class_name, pressable).toDOM();
    }

    static select(button, text) {
        for (const label of button.querySelectorAll('.ToggleButtonLabel')) {
            label.classList.toggle('Inactive', label.textContent !== text);
        }

        if (button.hasAttribute('aria-pressed')) {
            const first = button.querySelector('.ToggleButtonLabel');
            button.setAttribute('aria-pressed', String(first !== null && first.textContent !== text));
        }
    }

    static selected(button) {
        const showing = button.querySelector('.ToggleButtonLabel:not(.Inactive)');

        return showing === null ? '' : showing.textContent;
    }
}

// Dialog.js
const DialogModule = (() => {
class Dialog {
    // Currently active cancel callback – set by confirm/prompt, cleared on close
    static #activeCancel = null;

    /** Ids for aria-labelledby, since a dialog is named by the words in it. */
    static #counter = 0;

    /**
     * Everything inside the card a keyboard can reach, in the order it will.
     */
    static #focusable(card) {
        const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

        return [...card.querySelectorAll(selector)].filter((element) => !element.disabled);
    }

    /**
     * Makes a dialog behave like one.
     *
     * Without this the card is decoration: Tab walks straight out of it into
     * the page behind, which is still there and still clickable, and on close
     * the focus somebody had is gone - they are returned to the top of the
     * document to find their way back to whatever they were deleting. So the
     * card says what it is, keeps Tab inside itself while it is open, and
     * hands focus back where it came from afterwards.
     *
     * @returns {() => void} call it when the dialog closes
     */
    static #trap(card, message_element) {
        const returnTo = document.activeElement;

        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');

        if (message_element) {
            const id = 'DialogMessage' + (++Dialog.#counter);
            message_element.id = id;
            card.setAttribute('aria-labelledby', id);
        }

        const onTab = (event) => {
            if (event.key !== 'Tab') return;

            const focusable = Dialog.#focusable(card);

            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onTab);

        return () => {
            document.removeEventListener('keydown', onTab);

            // Back where they were. A dialog opened from a post's delete
            // button should leave them on that button, not at the top of a
            // feed they now have to scroll through again.
            if (returnTo && typeof returnTo.focus === 'function' && returnTo.isConnected) {
                returnTo.focus();
            }
        };
    }

    /**
     * Show a confirmation dialog with OK / Cancel buttons.
     * The buttons can be labelled with the answers themselves rather than OK
     * and Cancel - a question whose two answers are in different languages has
     * to say each one in its own.
     *
     * @param {string} message
     * @param {{ confirmText?: string, cancelText?: string }} labels
     * @returns {Promise<boolean>} – resolves true for OK, false for Cancel/Escape
     */
    static confirm(message, { confirmText = null, cancelText = null } = {}) {
        const words = Strings.for('Dialog');
        confirmText ??= words.confirm || '';
        cancelText ??= words.cancel || '';
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions';

            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'Button ConfirmDialogCancelButton';
            cancelButton.textContent = cancelText;

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = confirmText;

            actions.appendWithSpace(cancelButton);
            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const release = Dialog.#trap(card, text);

            const finish = (confirmed) => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                release();
                overlay.remove();
                resolve(confirmed);
            };

            Dialog.#activeCancel = () => finish(false);

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(false);
                }
            };

            cancelButton.addEventListener('click', () => finish(false));
            confirmButton.addEventListener('click', () => finish(true));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish(false);
                }
            });
            document.addEventListener('keydown', onKeydown);

            cancelButton.focus();
        });
    }

    /**
     * Show a message with a single OK button.
     * @param {string} message
     * @returns {Promise<void>} – resolves when dismissed
     */
    static alert(message) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = Strings.for('Dialog').confirm || '';

            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const release = Dialog.#trap(card, text);

            const finish = () => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                release();
                overlay.remove();
                resolve();
            };

            Dialog.#activeCancel = finish;

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish();
                }
            };

            confirmButton.addEventListener('click', finish);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish();
                }
            });
            document.addEventListener('keydown', onKeydown);

            confirmButton.focus();
        });
    }

    /**
     * Show a prompt dialog with a textarea and a confirm button.
     * @param {string} message
     * @param {object} [options]
     * @param {string} [options.placeholder] – placeholder text for the textarea
     * @param {string} [options.confirmLabel='OK'] – confirm button label
     * @returns {Promise<string|null>} – resolves with the input value, or null for Cancel/Escape
     */
    static prompt(message, options = {}) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const input = document.createElement('textarea');
            input.className = 'ConfirmDialogInput';
            input.rows = 3;

            if (options.placeholder) {
                input.placeholder = options.placeholder;
            }

            card.appendWithSpace(input);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions';

            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'Button ConfirmDialogCancelButton';
            cancelButton.textContent = Strings.for('Dialog').cancel || '';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = options.confirmLabel || Strings.for('Dialog').confirm || '';
            // Off until the box has something in it - a rule about the input,
            // not a wait, so it does not throb.
            confirmButton.disabled = true;

            actions.appendWithSpace(cancelButton);
            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const release = Dialog.#trap(card, text);

            // The question above the box is the box's label. A placeholder is
            // not one: it is gone the moment anybody types.
            input.setAttribute('aria-labelledby', text.id);

            const finish = (value) => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                release();
                overlay.remove();
                resolve(value);
            };

            Dialog.#activeCancel = () => finish(null);

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(null);
                }
            };

            input.addEventListener('input', () => {
                confirmButton.disabled = input.value.trim() === '';
            });

            cancelButton.addEventListener('click', () => finish(null));
            confirmButton.addEventListener('click', () => {
                const value = input.value.trim();
                if (value !== '') {
                    finish(value);
                }
            });
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish(null);
                }
            });
            document.addEventListener('keydown', onKeydown);

            input.focus();
        });
    }
}

    return { Dialog };
})();
export const Dialog = DialogModule.Dialog;

// EmojiShortcode.js
const EmojiShortcodeModule = (() => {
/**
 * Turns :shortcode: into the emoji it names - the client half of
 * EmojiShortcode.php, working off the same table, served by it.
 *
 * The table is imported rather than duplicated. It is hard-coded once in
 * EmojiShortcodeMap.php and handed here as data, so there is no second copy to
 * drift and nothing writes executable source from anything fetched.
 *
 * Only ever at the last step of output. What someone typed is never rewritten,
 * so the composer, the stored post and an edit all still say exactly that; only
 * what is rendered carries the emoji.
 *
 * A name this table does not hold is left alone, which is what keeps a clock
 * time, a ratio and a custom emoji intact - the last of those so a per-post
 * Emoji tag can resolve it later.
 */

const SHORTCODE = /:([a-z0-9_+-]+):/gi;

/** Where a colon means something other than an emoji. */
const CODE_CONTEXT = 'pre, code, .katex, .PostFormula';

function expand(text) {
    // Most text has no colons at all, and scanning it is the common case.
    if (!text.includes(':')) {
        return text;
    }

    return text.replace(SHORTCODE, (whole, name) => EMOJI_SHORTCODES[name.toLowerCase()] ?? whole);
}

/**
 * Expands every shortcode under a node, except inside code.
 *
 * Walks the finished tree rather than substituting while it is built, for the
 * same reason the server does: a code block is known only once it exists. The
 * skip list matches EmojiRenderer's, so the two passes agree about what is
 * left alone.
 */
function expandInDOM(root, custom = {}) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: node => {
            if (node.parentElement?.closest(CODE_CONTEXT)) {
                return NodeFilter.FILTER_REJECT;
            }

            return node.data.includes(':') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
        }
    });

    // Collected before changing anything: editing a node's data while the
    // walker is still traversing is asking for a surprise.
    const nodes = [];

    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }

    nodes.forEach(node => {
        const replacement = fragmentFor(node.data, custom);

        if (replacement !== null) {
            node.parentNode?.replaceChild(replacement, node);
        }
    });
}

/**
 * One text node's worth of expansion, or null when nothing in it changes.
 *
 * A custom emoji becomes an image and so needs real nodes, which is why this
 * builds a fragment rather than a string. A Unicode one is still just text.
 *
 * The custom map wins where both know a name: a tag is the sending server
 * stating what a shortcode means in THIS post, which is a more specific claim
 * than a table everyone shares.
 */
function fragmentFor(text, custom) {
    if (!text.includes(':')) {
        return null;
    }

    const fragment = document.createDocumentFragment();
    let cursor = 0;
    let changed = false;

    SHORTCODE.lastIndex = 0;

    let match;

    while ((match = SHORTCODE.exec(text)) !== null) {
        const name = match[1].toLowerCase();
        const image = custom[name];
        const character = EMOJI_SHORTCODES[name];

        if (image === undefined && character === undefined) {
            continue;
        }

        if (match.index > cursor) {
            fragment.appendChild(document.createTextNode(text.slice(cursor, match.index)));
        }

        if (image !== undefined) {
            const element = document.createElement('img');
            element.className = 'CustomEmoji';
            element.src = image;
            // The shortcode is the alt text: it is what the author wrote, and
            // the only description of the picture that exists.
            element.alt = `:${name}:`;
            element.title = `:${name}:`;
            element.loading = 'lazy';
            fragment.appendChild(element);
        } else {
            fragment.appendChild(document.createTextNode(character));
        }

        cursor = match.index + match[0].length;
        changed = true;
    }

    if (!changed) {
        return null;
    }

    if (cursor < text.length) {
        fragment.appendChild(document.createTextNode(text.slice(cursor)));
    }

    return fragment;
}

    return { expand, expandInDOM };
})();
export const expand = EmojiShortcodeModule.expand;
export const expandInDOM = EmojiShortcodeModule.expandInDOM;

// Linkifier.js
const LinkifierModule = (() => {
class Linkifier {
    static MAX_TAG_LENGTH = 50;
    static MAX_MENTION_LENGTH = 255;
    static URL_TRAILING_TRIM = ".,!?;:)";
    // Kept identical to Linkifier.php's TAG_CHARS - see the reasoning there
    // for why a #tag is stated as the ASCII it may not contain rather than as
    // the characters it may.
    static TAG_CHARS = "[^\\x00-\\x2F\\x3A-\\x40\\x5B-\\x5E\\x60\\x7B-\\x7F]";
    // Punctuation that ends a tag rather than belonging to it - identical to
    // Linkifier.php's TAG_TRAILING_PUNCTUATION, see the reasoning there. This
    // matters most here: a feed's truncated posts are rendered by this side.
    static TAG_TRAILING_PUNCTUATION = ['…', '—', '–', '“', '”', '‘', '’', '«', '»', '„'];
    // The characters a URL may be spelled with, once one has started.
    static URL_CHARS = "[A-Za-z0-9._~:/?#\\[\\]@!$&'()*+,;=%-]";
    // A link written without its scheme - see Linkifier.php's BARE_URL for
    // why it is this narrow.
    static BARE_URL = "(?<![A-Za-z0-9._~:/?#@-])(?:www\\.[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)*|[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)*\\.[A-Za-z][A-Za-z]+/)";
    static SCAN = "https?://" + Linkifier.URL_CHARS + "+|" + Linkifier.BARE_URL + Linkifier.URL_CHARS + "*|(?<!" + Linkifier.TAG_CHARS + ")(?<!#)#" + Linkifier.TAG_CHARS + "+|(?<![A-Za-z0-9_@])@[A-Za-z0-9_]+(?:@[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)+)?";
    static LOOKS_URL = "https?://|www\\.[A-Za-z0-9-]|[A-Za-z0-9-]+\\.[A-Za-z][A-Za-z]+/";
    static AUTHORITY = "^(?:[A-Za-z][A-Za-z0-9+.-]*:)?//([^/?#]*)";

    /** Pass 1's anti-phishing detector: does this text read as a URL to a human? */
    static textLooksURL(text) {
        return new RegExp(Linkifier.LOOKS_URL).test(text);
    }

    static linkHost(url) {
        const stripped = url.replace(/[\u0000-\u0020]+/g, '');
        const match = new RegExp(Linkifier.AUTHORITY).exec(stripped);

        if (match === null) {
            return null;
        }

        let authority = match[1];
        const at = authority.lastIndexOf('@');

        if (at !== -1) {
            authority = authority.slice(at + 1);
        }

        const colon = authority.indexOf(':');

        if (colon !== -1) {
            authority = authority.slice(0, colon);
        }

        return authority.toLowerCase();
    }

    static tokenize(text) {
        const segments = [];
        let cursor = 0;
        const re = new RegExp(Linkifier.SCAN, 'g');
        let match;

        while ((match = re.exec(text)) !== null) {
            const matched = match[0];
            const offset = match.index;
            const classified = Linkifier.#classify(matched);

            if (classified === null) {
                continue;
            }

            if (offset > cursor) {
                segments.push({ type: 'text', text: text.slice(cursor, offset) });
            }

            segments.push(classified.segment);
            cursor = offset + matched.length;

            if (classified.trailing !== '') {
                segments.push({ type: 'text', text: classified.trailing });
            }
        }

        if (cursor < text.length) {
            segments.push({ type: 'text', text: text.slice(cursor) });
        }

        return Linkifier.#mergeText(segments);
    }

    /**
     * The mirror of Linkifier::isTagSlug(). Counted in code points rather
     * than in string length, which counts UTF-16 units and would make the cap
     * mean something different here than it does on the server.
     */
    static isTagSlug(tag) {
        if (tag === '' || [...tag].length > Linkifier.MAX_TAG_LENGTH) {
            return false;
        }

        if (!new RegExp('^' + Linkifier.TAG_CHARS + '+$').test(tag)) {
            return false;
        }

        return [...tag].some((character) => !'0123456789_'.includes(character));
    }

    /** Mirrors Linkifier::withoutTrailingPunctuation(). */
    static #withoutTrailingPunctuation(tag) {
        let trimming = true;

        while (trimming && tag !== '') {
            trimming = false;

            for (const mark of Linkifier.TAG_TRAILING_PUNCTUATION) {
                if (tag.endsWith(mark)) {
                    tag = tag.slice(0, -mark.length);
                    trimming = true;
                }
            }
        }

        return tag;
    }

    static #classify(matched) {
        if (matched[0] === '#') {
            const tag = Linkifier.#withoutTrailingPunctuation(matched.slice(1));
            const trailing = matched.slice(1 + tag.length);

            if (!Linkifier.isTagSlug(tag)) {
                return null;
            }

            return { segment: { type: 'hashtag', text: '#' + tag, tag: tag.toLowerCase() }, trailing };
        }

        if (matched[0] === '@') {
            const username = matched.slice(1);

            if (username === '' || username.length > Linkifier.MAX_MENTION_LENGTH) {
                return null;
            }

            // Lowercased for both display and the link - unlike a hashtag, a
            // username is always stored lowercase, so there's no legitimate
            // original casing to keep. Mirrors Linkifier::classify() exactly.
            const lowercased = username.toLowerCase();

            return { segment: { type: 'mention', text: '@' + lowercased, username: lowercased }, trailing: '' };
        }

        let end = matched.length;

        while (end > 0 && Linkifier.URL_TRAILING_TRIM.includes(matched[end - 1])) {
            end--;
        }

        const url = matched.slice(0, end);
        const trailing = matched.slice(end);

        const lower = url.toLowerCase();
        const schemeLength = lower.startsWith('https://') ? 8 : (lower.startsWith('http://') ? 7 : 0);

        // Trimmed down to just the scheme - not a real URL.
        if (schemeLength > 0 && url.length === schemeLength) {
            return null;
        }

        // The text stays as it was written; where the scheme is missing the
        // destination supplies one, since a link has to be absolute to lead
        // anywhere and https is what a bare host means now.
        return {
            segment: {
                type: 'url',
                text: url,
                href: schemeLength > 0 ? url : 'https://' + url,
            },
            trailing,
        };
    }

    static #mergeText(segments) {
        const merged = [];

        segments.forEach((segment) => {
            const last = merged.length - 1;

            if (segment.type === 'text' && last >= 0 && merged[last].type === 'text') {
                merged[last].text += segment.text;
                return;
            }

            merged.push(segment);
        });

        return merged;
    }
}

    return { Linkifier };
})();
export const Linkifier = LinkifierModule.Linkifier;

// DeltaRenderer.js
const DeltaRendererModule = (() => {
class DeltaRenderer {
    /**
     * Renders a Quill Delta (an array of insert ops) to real DOM nodes - no
     * innerHTML, no HTML strings. Mirrors the server-side DeltaRenderer so a post
     * looks identical whether it arrived in the initial page or over AJAX.
     *
     * Quill's model: inline runs are ops whose insert is a string (with inline
     * attributes like bold/link); a line's block type lives on the "\n" that ends
     * it (header/list/blockquote/code-block). So we buffer inline nodes per line
     * and flush a block element when a newline names the block. Math is either a
     * formula embed op ({insert:{formula:'...'}}, rendered directly via KaTeX) or
     * typed/pasted delimiters left in the text (rendered afterward by render_math).
     *
     * Runs the "honest links" pass (see Linkifier), kept identical to
     * DeltaRenderer: pass 1 strips the href off any link whose text reads as a URL
     * (anti-phishing), pass 2 linkifies bare URLs and #hashtags. External links open
     * in a new tab; internal/hashtag links open in place.
     *
     * @param {Array} ops  the Delta's ops array
     * @returns {HTMLElement} a .PostBody div containing the rendered content
     */
    static render(ops, customEmoji = {}, mentionsAreLocal = true) {
        const root = document.createElement('div');
        root.className = 'PostBody';

        if (!Array.isArray(ops)) {
            return root;
        }

        // Pass 1: neutralise deceptive anchors before rendering.
        ops = DeltaRenderer.#stripDeceptiveLinks(ops);

        let inline = [];      // inline nodes accumulated for the current line
        let list_el = null;   // the <ol>/<ul> currently being filled, or null
        let list_kind = null; // 'ordered' | 'bullet'
        let pre_el = null;    // the <pre> currently being filled, or null

        const formula_node = (source) => {
            // Carries the LaTeX source; render_formulas() renders it via KaTeX
            // (the same client pass that renders server-emitted formula spans), so
            // math from a formula embed and math from a server render take one
            // path. The textContent is the fallback if KaTeX never runs.
            const span = document.createElement('span');
            span.className = 'PostFormula';
            span.setAttribute('data-formula', source);
            span.textContent = source;
            return span;
        };

        const flush_line = (block_attributes) => {
            const attrs = block_attributes || {};

            // List items group consecutive same-kind lines under one <ol>/<ul>.
            if (attrs.list === 'ordered' || attrs.list === 'bullet') {
                pre_el = null;

                if (list_el === null || list_kind !== attrs.list) {
                    list_el = document.createElement(attrs.list === 'ordered' ? 'ol' : 'ul');
                    list_kind = attrs.list;
                    root.appendWithSpace(list_el);
                }

                const li = document.createElement('li');
                inline.forEach((n) => li.appendWithSpace(n));
                list_el.appendWithSpace(li);
                list_el.appendWithSpace(document.createTextNode('\n'));
                inline = [];
                return;
            }

            // Any non-list line closes an open list.
            list_el = null;
            list_kind = null;

            // Mirrors DeltaRenderer.php: a code block is one <pre> however
            // many lines it runs to. Quill marks each of its lines separately,
            // the way it marks each list item, so a fresh element per line
            // turns a script into a stack of one-line boxes.
            if (attrs['code-block']) {
                if (pre_el === null) {
                    pre_el = document.createElement('pre');
                    root.appendWithSpace(pre_el);
                } else {
                    // Between lines rather than after each, so the block does
                    // not end on a blank line it never had.
                    pre_el.appendChild(document.createTextNode('\n'));
                }

                // appendChild, not appendWithSpace: that one puts a space
                // between adjacent inline elements to keep the markup
                // readable, which is fine in a paragraph and is an edit to the
                // code in here.
                inline.forEach((n) => pre_el.appendChild(n));
                inline = [];

                return;
            }

            pre_el = null;

            let block;
            if (attrs.header === 1 || attrs.header === 2 || attrs.header === 3) {
                block = document.createElement('h' + attrs.header);
            } else if (attrs.blockquote) {
                block = document.createElement('blockquote');
            } else {
                block = document.createElement('p');
            }

            inline.forEach((n) => block.appendWithSpace(n));

            // An empty line (Quill renders it as <p><br></p>) still takes space.
            if (inline.length === 0 && block.tagName === 'P') {
                block.appendWithSpace(document.createElement('br'));
            }

            root.appendWithSpace(block);
            root.appendWithSpace(document.createTextNode('\n'));
            inline = [];
        };

        ops.forEach((op) => {
            if (typeof op.insert === 'string') {
                // Each "\n" in the string ends a line; its block type comes from
                // this op's attributes (Quill puts block attrs on the newline op).
                const segments = op.insert.split('\n');

                segments.forEach((text, index) => {
                    if (text !== '') {
                        inline.push(...DeltaRenderer.#inlineNodes(text, op.attributes, mentionsAreLocal));
                    }

                    if (index < segments.length - 1) {
                        flush_line(op.attributes);
                    }
                });
            } else if (op.insert && typeof op.insert === 'object' && typeof op.insert.formula === 'string') {
                inline.push(formula_node(op.insert.formula));
            }
            // Other embed types (none authored by this app) are ignored.
        });

        // A Quill delta always ends with a trailing "\n", so `inline` is normally
        // empty here; flush anything left just in case of a malformed delta.
        if (inline.length > 0) {
            flush_line({});
        }

        // Last step of the output stage, matching DeltaRenderer.php. Over the
        // finished tree, so code keeps its colons.
        expandInDOM(root, customEmoji);

        return root;
    }

    static ALLOWED_LINK_SCHEMES = ['http:', 'https:', 'mailto:'];

    /**
     * Pass 1: group consecutive string ops sharing a link value and, if the group's
     * combined text reads as a URL, strip the link from all of them (mirrors
     * DeltaRenderer::stripDeceptiveLinks). Grouping - not per-op - is what stops a
     * URL split across formatting ops from keeping a deceptive href.
     */
    static #stripDeceptiveLinks(ops) {
        const result = [];
        let group = [];
        let group_text = '';
        let group_link = null;

        const resolve = () => {
            if (group.length > 0 && Linkifier.textLooksURL(group_text)) {
                group.forEach((i) => {
                    const attrs = { ...result[i].attributes };
                    delete attrs.link;
                    result[i] = { ...result[i], attributes: attrs };
                });
            }
            group = [];
            group_text = '';
            group_link = null;
        };

        ops.forEach((op) => {
            const link = typeof op.insert === 'string' && op.attributes ? op.attributes.link : undefined;

            if (typeof link === 'string') {
                if (group_link !== null && link !== group_link) {
                    resolve();
                }
                result.push(op);
                group.push(result.length - 1);
                group_text += op.insert;
                group_link = link;
            } else {
                resolve();
                result.push(op);
            }
        });

        resolve();

        return result;
    }

    /**
     * Pass 2: the inline node(s) for one text run (mirrors DeltaRenderer::inlineNodes).
     * A link that survived pass 1 is a URL-free label -> one honest anchor; inline
     * code is never linkified; otherwise URLs become self-links, #hashtags tag
     * links, the rest plain - each wrapped in the run's formatting, anchor outermost.
     */
    static #inlineNodes(text, attributes, mentionsAreLocal = true) {
        const attrs = attributes || {};

        if (typeof attrs.link === 'string') {
            return [DeltaRenderer.linkedNode(attrs.link, DeltaRenderer.#formattedTextNode(text, attrs))];
        }

        if (attrs.code) {
            return [DeltaRenderer.#formattedTextNode(text, attrs)];
        }

        return Linkifier.tokenize(text).map((segment) => {
            const inner = DeltaRenderer.#formattedTextNode(segment.text, attrs);

            if (segment.type === 'url') {
                return DeltaRenderer.linkedNode(segment.href, inner);
            }
            if (segment.type === 'hashtag') {
                return DeltaRenderer.hashtagNode(segment.tag, inner);
            }
            if (segment.type === 'mention') {
                // Mirrors DeltaRenderer.php: a bare name in writing from
                // another server is one of the writer's own neighbours, and
                // the account of that name here is a different person.
                return mentionsAreLocal || segment.username.includes('@')
                    ? DeltaRenderer.mentionNode(segment.username, inner)
                    : inner;
            }
            return inner;
        });
    }

    /** A text node wrapped in the run's inline formatting (no link). */
    static #formattedTextNode(text, attrs) {
        let node = document.createTextNode(text);

        if (attrs.code) {
            const code = document.createElement('code');
            code.appendWithSpace(node);
            node = code;
        }
        if (attrs.bold) {
            const strong = document.createElement('strong');
            strong.appendWithSpace(node);
            node = strong;
        }
        if (attrs.italic) {
            const em = document.createElement('em');
            em.appendWithSpace(node);
            node = em;
        }
        if (attrs.underline) {
            const u = document.createElement('u');
            u.appendWithSpace(node);
            node = u;
        }
        if (attrs.strike) {
            const s = document.createElement('s');
            s.appendWithSpace(node);
            node = s;
        }

        return node;
    }

    /** An anchor to href (external -> new tab), or the bare node if unsafe. */
    static linkedNode(href, inner) {
        if (!DeltaRenderer.isSafeLink(href, DeltaRenderer.ALLOWED_LINK_SCHEMES)) {
            return inner;
        }

        const anchor = document.createElement('a');
        anchor.setAttribute('href', href);

        if (DeltaRenderer.#opensInNewTab(href)) {
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener');
        }

        anchor.appendWithSpace(inner);
        return anchor;
    }

    /** An internal (same-window) anchor to a hashtag's tag page. */
    static hashtagNode(tag, inner) {
        const anchor = document.createElement('a');
        anchor.setAttribute('href', ClientConfig.siteURL() + '/tags/' + tag);
        anchor.appendWithSpace(inner);
        return anchor;
    }

    /** An internal (same-window) anchor to a mentioned user's profile. */
    static mentionNode(username, inner) {
        const anchor = document.createElement('a');
        anchor.setAttribute('href', ClientConfig.siteURL() + '/users/' + username + '/');
        anchor.appendWithSpace(inner);
        return anchor;
    }

    static #opensInNewTab(href) {
        const host = Linkifier.linkHost(href);

        if (host === null) {
            return false;
        }

        return host !== Linkifier.linkHost(ClientConfig.siteURL());
    }

    /**
     * The "See More…" link a truncated feed preview appends, linking to the full
     * post. Mirrors the server-side SeeMore class (same SeeMore class name, text,
     * and href) so a truncated post looks identical whether it arrived in the page
     * or over AJAX.
     *
     * @param {string} url  the full post's URL
     * @returns {HTMLElement} an <a class="SeeMore">
     */
    static seeMoreElement(url) {
        const anchor = document.createElement('a');
        anchor.className = 'SeeMore';
        anchor.href = url;
        anchor.textContent = Strings.for('MiscellaneousClient').seeMore || '';
        return anchor;
    }

    /**
     * Whether a link URL is safe to render (a known scheme, or a scheme-relative /
     * relative URL). Blocks javascript:, data:, etc. Server-side validation is the
     * real gate; this is client-side defense in depth.
     */
    static isSafeLink(url, allowed_schemes) {
        // Browsers strip ASCII whitespace/control chars while parsing a URL, so
        // "java\tscript:" would run; strip them (interior ones too) before the
        // scheme test. Mirrors DeltaRenderer::isSafeLink().
        const stripped = url.replace(/[\u0000-\u0020]+/g, '');
        const match = /^([a-z][a-z0-9+.-]*):/i.exec(stripped);

        if (match === null) {
            return true; // relative or scheme-relative URL - no scheme to abuse
        }

        return allowed_schemes.includes(match[1].toLowerCase() + ':');
    }
}

    return { DeltaRenderer };
})();
export const DeltaRenderer = DeltaRendererModule.DeltaRenderer;

// EmojiRenderer.js
const EmojiRendererModule = (() => {
const EMOJI_SEQUENCE = /\p{Emoji_Presentation}\uFE0F?\p{Emoji_Modifier}?(\u200D\p{Emoji}\uFE0F?\p{Emoji_Modifier}?)*|\p{Emoji}\uFE0F\p{Emoji_Modifier}?(\u200D\p{Emoji}\uFE0F?\p{Emoji_Modifier}?)*/gu;
/** What counts as one character to the person who typed it. */
const GRAPHEMES = new Intl.Segmenter(undefined, { granularity: 'grapheme' });

class EmojiRenderer {
    /**
     * Where an emoji is somebody's writing rather than part of the page.
     *
     * Enlarging one is only ever right inside what somebody wrote. Everywhere
     * else - an action bar's buttons, a display name, a topic, a nav label -
     * the emoji IS the furniture and is already sized by its own rules.
     */
    static CONTENT = '.PostContent, .MessageLine';

    static init() {
        document.querySelectorAll(EmojiRenderer.CONTENT).forEach(content => EmojiRenderer.render(content));
        EmojiRenderer.#markExistingEmojiOnly();
    }

    static render(root) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: node => {
                if (node.parentElement.closest('.emoji-text, pre, code, .katex, .PostFormula')) {
                    return NodeFilter.FILTER_REJECT;
                }
                // The regex is global, so a previous test() leaves lastIndex
                // mid-string - unreset, the next node's test starts there and
                // can miss an emoji earlier in its text.
                EMOJI_SEQUENCE.lastIndex = 0;
                return EMOJI_SEQUENCE.test(node.data) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            }
        });

        const nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(textNode => {
            const parent = textNode.parentNode;
            const fragment = document.createDocumentFragment();
            let plain = '';

            // Cut into grapheme clusters and decide per cluster, rather than
            // wrapping whatever the pattern happened to match. A flag is a
            // PAIR of regional indicators with nothing joining them, so a
            // pattern matching one emoji at a time takes 🇺🇸 as two and puts
            // each in its own span - which is why flags came out as two big
            // letters. Keycaps and joined families split the same way.
            // The cluster is the unit the writer typed and the unit a font
            // draws, so it is the unit to wrap.
            for (const { segment } of GRAPHEMES.segment(textNode.data)) {
                EMOJI_SEQUENCE.lastIndex = 0;

                if (!EMOJI_SEQUENCE.test(segment)) {
                    plain += segment;

                    continue;
                }

                if (plain !== '') {
                    fragment.appendChild(document.createTextNode(plain));
                    plain = '';
                }

                const span = document.createElement('span');
                span.className = 'emoji-text';
                span.textContent = segment;
                fragment.appendChild(span);
            }

            if (plain !== '') {
                fragment.appendChild(document.createTextNode(plain));
            }

            parent.replaceChild(fragment, textNode);
        });
    }

    /**
     * Whether this is emoji and nothing else - what decides that a post or a
     * message is shown big and centred.
     *
     * Cluster by cluster, for the same reason render() is: stripping matches
     * out of the text and asking whether anything is left leaves behind the
     * parts of a sequence the pattern does not reach - a keycap's enclosing
     * mark, say - and one leftover character answers no.
     */
    static isEmojiOnly(element) {
        let sawEmoji = false;

        for (const { segment } of GRAPHEMES.segment(element.textContent)) {
            // trim() takes the no-break space too, so nothing separating the
            // emoji counts against them.
            if (segment.trim() === '') {
                continue;
            }

            EMOJI_SEQUENCE.lastIndex = 0;

            if (!EMOJI_SEQUENCE.test(segment)) {
                return false;
            }

            sawEmoji = true;
        }

        return sawEmoji;
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    static #markExistingEmojiOnly() {
        // Posts
        document.querySelectorAll('.PostBody').forEach(body => {
            if (EmojiRenderer.isEmojiOnly(body)) {
                const card = body.closest('.Post');
                if (card) card.classList.add('emoji-only');
            }
        });

        // Messages
        document.querySelectorAll('.MessageBody').forEach(body => {
            if (EmojiRenderer.isEmojiOnly(body)) {
                const card = body.closest('.Message');
                if (card) card.classList.add('emoji-only');
            }
        });
    }
}

ReadyHandler.add(EmojiRenderer.init);

    return { EmojiRenderer };
})();
export const EmojiRenderer = EmojiRendererModule.EmojiRenderer;

// MathRenderer.js
const MathRendererModule = (() => {
const MATH_COALESCE_SKIP = 'pre, code, .PostFormula, .katex';

function coalesce_display_math(post_body) {
    display_math_block_groups(post_body).forEach((group) => {
        const segments = math_text_segments(group);
        let logical = '';

        segments.forEach((segment) => {
            segment.start = logical.length;
            logical += segment.text;
        });

        const matches = [...logical.matchAll(/\$\$[^\u0000]*?\$\$|\\\[[^\u0000]*?\\\]/g)];

        for (let i = matches.length - 1; i >= 0; i--) {
            coalesce_run(segments, logical, matches[i].index, matches[i].index + matches[i][0].length);
        }
    });
}

function display_math_block_groups(post_body) {
    const groups = [];
    let open_p_group = null;

    Array.from(post_body.children).forEach((child) => {
        if (child.tagName === 'P') {
            if (open_p_group === null) {
                open_p_group = [];
                groups.push(open_p_group);
            }
            open_p_group.push(child);
            return;
        }

        open_p_group = null;

        if (child.tagName === 'PRE') {
            return;
        }

        if (child.tagName === 'OL' || child.tagName === 'UL') {
            Array.from(child.children).forEach((li) => groups.push([li]));
            return;
        }

        groups.push([child]);
    });

    return groups;
}

function math_text_segments(blocks) {
    const segments = [];

    blocks.forEach((block, index) => {
        if (index > 0) {
            segments.push({ text: '\n' });
        }

        collect_math_segments(block, block, segments);
    });

    return segments;
}

function collect_math_segments(node, block, segments) {
    node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
            segments.push({ text: child.data, node: child, block: block });
        } else if (child.nodeType === Node.ELEMENT_NODE) {
            if (child.tagName === 'BR') {
                segments.push({ text: '\n', node: child, block: block });
            } else if (child.matches(MATH_COALESCE_SKIP)) {
                segments.push({ text: '\u0000' });
            } else {
                collect_math_segments(child, block, segments);
            }
        }
    });
}

function coalesce_run(segments, logical, start, end) {
    const covered = segments.filter((segment) =>
        segment.node !== undefined
        && segment.start < end
        && segment.start + segment.text.length > start
    );
    const first = covered[0];
    const last = covered[covered.length - 1];

    if (first === last) {
        return;
    }

    let start_node = first.node;

    if (start > first.start) {
        start_node = start_node.splitText(start - first.start);
    }

    if (end - last.start < last.node.data.length) {
        last.node.splitText(end - last.start);
    }

    start_node.parentNode.insertBeforeWithSpace(document.createTextNode(logical.slice(start, end)), start_node);

    start_node.remove();
    covered.slice(1).forEach((segment) => segment.node.remove());

    let block = first.block.nextElementSibling;

    while (block !== null && block !== last.block) {
        const next = block.nextElementSibling;
        block.remove();
        block = next;
    }

    if (last.block !== first.block && !last.block.hasChildNodes()) {
        last.block.remove();
    }
}

function render_math(element) {
    render_formulas(element);

    if (typeof renderMathInElement !== 'function') {
        return;
    }
    element.querySelectorAll('.PostBody').forEach((post_body) => {
        const text = post_body.textContent;

        if (text.includes('$$') || text.includes('\\[')) {
            coalesce_display_math(post_body);
        }
    });

    renderMathInElement(element, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '\\[', right: '\\]', display: true },
            { left: '\\(', right: '\\)', display: false },
            { left: '$', right: '$', display: false },
        ],
        throwOnError: false,
    });
}

// Which formula spans KaTeX has already replaced the contents of. Held here
// rather than marked on the element: whether this pass has run is the renderer's
// own business, and a weak set lets a span that leaves the page be collected.
const rendered_formulas = new WeakSet();

function render_formulas(element) {
    if (typeof katex === 'undefined' || typeof katex.render !== 'function') {
        return;
    }

    element.querySelectorAll('.PostFormula[data-formula]').forEach((span) => {
        if (rendered_formulas.has(span)) {
            return;
        }

        katex.render(span.dataset.formula, span, { throwOnError: false });
        rendered_formulas.add(span);
    });
}

class MathRenderer {
    static init() {
        if (ClientConfig.get('needsMath')) {
            render_math(document.body);
        }
    }
}

ReadyHandler.add(MathRenderer.init);

    return { render_math, render_formulas, MathRenderer };
})();
export const render_math = MathRendererModule.render_math;
export const render_formulas = MathRendererModule.render_formulas;
export const MathRenderer = MathRendererModule.MathRenderer;

// CodeBlockCopy.js
const CodeBlockCopyModule = (() => {
// CodeBlockCopy.js
// Adds a "Copy" button to every <pre> inside a container (e.g., a post body).
// After copying, the button briefly shows "Copied!" then reverts.

const COPIED_TIMEOUT = 2000; // ms before "Copied!" goes back to "Copy"

function addCopyButton(pre) {
  // Avoid adding a second button if already enhanced
  if (pre.parentElement?.classList.contains('CodeBlockWrapper')) return;

  // Wrap the <pre> in a relative container so we can position the button
  const wrapper = document.createElement('div');
  wrapper.className = 'CodeBlockWrapper';
  pre.parentNode.insertBefore(wrapper, pre);
  wrapper.appendChild(pre);

  const button = document.createElement('button');
  button.className = 'CodeCopyButton';
  const words = Strings.for('CodeBlockCopy');
  button.textContent = words.copy || '';
  button.setAttribute('aria-label', words.copyLabel || '');
  button.setAttribute('type', 'button');

  button.addEventListener('click', () => {
    navigator.clipboard.writeText(pre.textContent)
      .then(() => {
        button.textContent = words.copied || '';
        button.classList.add('copied');
        setTimeout(() => {
          button.textContent = words.copy || '';
          button.classList.remove('copied');
        }, COPIED_TIMEOUT);
      })
      .catch(() => {
        // Clipboard API may fail (e.g., non‑HTTPS); silently ignore
      });
  });

  wrapper.appendChild(button);
}

/**
 * Enhance all <pre> elements inside a given container (e.g., a post element).
 */
function enhanceCodeBlocks(container) {
  if (!container) return;
  container.querySelectorAll('.PostBody pre').forEach(pre => addCopyButton(pre));
}

    return { enhanceCodeBlocks };
})();
export const enhanceCodeBlocks = CodeBlockCopyModule.enhanceCodeBlocks;

// UserBio.js
const UserBioModule = (() => {
/** Mirrors UserBio.php: a user's plain-text bio, linkified the same way the
 * server renders it (the shared Linkifier), so a saved bio round-trips
 * identically. Newlines are preserved by the .UserBio white-space rule. */
class UserBio {
    constructor(user) {
        this.description = user.description || '';
    }

    toElement() {
        const bio = document.createElement('div');
        bio.className = 'UserBio';

        for (const segment of Linkifier.tokenize(this.description)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                bio.appendWithSpace(DeltaRenderer.linkedNode(segment.href, inner));
            } else if (segment.type === 'hashtag') {
                bio.appendWithSpace(DeltaRenderer.hashtagNode(segment.tag, inner));
            } else if (segment.type === 'mention') {
                bio.appendWithSpace(DeltaRenderer.mentionNode(segment.username, inner));
            } else {
                bio.appendWithSpace(inner);
            }
        }

        return bio;
    }
}

    return { UserBio };
})();
export const UserBio = UserBioModule.UserBio;

// User.js
const UserModule = (() => {
/** Mirrors User.php: the identity card and the byline header, shared by every
 * user-shaped thing (OtherUser, ReceivedFriendRequest, BannedUser, a report's user
 * target, a message sender). */
class User {
    static fromData(data) {
        const user = new this();
        Object.assign(user, data);
        return user;
    }

    name() {
        return this.title || this.slug;
    }

    /**
     * Mirrors User::header(): the avatar + display name + username block used
     * wherever a message, post, or similar item needs to show who it's from -
     * one clickable link to their profile.
     */
    header() {
        const header = document.createElement('a');
        header.href = ClientConfig.siteURL() + '/users/' + this.slug + '/';
        header.className = 'UserHeaderLink';

        header.appendWithSpace(Avatar.forUser(this).toDOM());

        const info = document.createElement('div');
        info.className = 'UserHeaderInfo';

        const name_line = document.createElement('div');
        name_line.className = 'UserHeaderName';
        name_line.textContent = this.name();
        info.appendWithSpace(name_line);

        const username_line = document.createElement('div');
        username_line.className = 'UserHeaderUsername';
        username_line.textContent = '@' + this.slug;
        info.appendWithSpace(username_line);

        header.appendWithSpace(info);

        return header;
    }

    /**
     * Mirrors User::toDOM(): the full identity card - avatar, name, @username,
     * joined date, and bio, the identity all one link to the profile - wrapped
     * in a .User.Card.
     */
    toElement() {
        const div = document.createElement('div');
        div.className = 'User';

        if (this.slug) {
            div.dataset.username = this.slug;
        }

        const main = document.createElement('div');
        main.className = 'UserMain';

        const link = document.createElement('a');
        link.className = 'UserLink';
        link.href = ClientConfig.siteURL() + '/users/' + this.slug + '/';

        link.appendWithSpace(Avatar.forUser(this).toDOM());

        const info = document.createElement('div');
        info.className = 'UserIdentity';

        const name_heading = document.createElement('h2');
        name_heading.className = 'DisplayName';
        name_heading.textContent = this.name();
        info.appendWithSpace(name_heading);

        const username_line = document.createElement('div');
        username_line.className = 'UserUsername';
        username_line.textContent = '@' + this.slug;
        info.appendWithSpace(username_line);

        if (this.createdAt) {
            const joined = document.createElement('div');
        joined.className = 'UserJoined';
            joined.textContent = (Strings.for('UserClient').joined || '').replace('{date}', RelativeTime.date(this.createdAt));
            info.appendWithSpace(joined);
        }

        link.appendWithSpace(info);
        main.appendWithSpace(link);

        if (this.description && this.description.trim() !== '') {
            main.appendWithSpace(new UserBio(this).toElement());
        }

        div.appendWithSpace(main);

        return div;
    }

    // ----------------------------------------------------------------
    // Static action handlers (profile editing, Google delete, resend verification, revoke session)
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', (event) => {
            // Start profile editing
            const editTrigger = event.target.closest('.User.CurrentUser .DisplayName, .User.CurrentUser .UserBio, .User.CurrentUser .ProfileEditButton');
            if (editTrigger && !editTrigger.closest('a')) {
                // A hashtag or link inside your own bio is text you are about
                // to edit rather than somewhere to go. Without this the click
                // both opens the editor and follows the link, and the
                // navigation wins - so the bio is the one part of your profile
                // you cannot click to edit.
                const link = event.target.closest('a');

                if (link !== null && editTrigger.contains(link)) {
                    event.preventDefault();
                }

                const card = editTrigger.closest('.User.CurrentUser');
                if (card && !card.classList.contains('Editing')) {
                    User.#startEdit(card);
                }
                return;
            }

            // Save profile
            const saveBtn = event.target.closest('.ProfileSaveButton');
            if (saveBtn) {
                User.#save(saveBtn.closest('.User.CurrentUser'));
                return;
            }

            // Google delete
            const googleDelBtn = event.target.closest('.GoogleAccountDeleteButton');
            if (googleDelBtn) {
                User.#confirmGoogleDelete(googleDelBtn);
                return;
            }

            // Resend verification email
            const resendBtn = event.target.closest('.VerificationResendButton');
            if (resendBtn) {
                User.#resendVerification(resendBtn);
                return;
            }

        });
    }

    static #startEdit(card) {
        card.classList.add('Editing');

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'DisplayNameInput';
        nameInput.maxLength = 50;
        nameInput.value = card.dataset.title;
        nameInput.placeholder = Strings.for('UserClient').displayName || '';
        card.querySelector('.DisplayName').replaceWith(nameInput);

        const bioInput = document.createElement('textarea');
        bioInput.className = 'UserBioInput';
        bioInput.maxLength = 500;
        bioInput.value = card.dataset.description;
        bioInput.placeholder = Strings.for('UserClient').bio || '';
        const bio = card.querySelector('.UserBio');
        bio.replaceWith(bioInput);

        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'Button ProfileSaveButton';
        save.textContent = Strings.for('UserClient').save || '';
        bioInput.after(save);

        nameInput.focus();
    }

    static async #save(card) {
        const nameInput = card.querySelector('.DisplayNameInput');
        const bioInput = card.querySelector('.UserBioInput');
        const save = card.querySelector('.ProfileSaveButton');
        Working.start(save);

        const data = await Api.post('/api/update-profile', {
            title: nameInput.value,
            description: bioInput.value,
        });

        if (!data) {
            Working.stop(save);
            return;
        }

        card.dataset.title = data.title || '';
        card.dataset.description = data.description || '';

        const heading = document.createElement('h2');
        heading.className = 'DisplayName';
        heading.textContent = data.title || card.dataset.username;
        nameInput.replaceWith(heading);

        bioInput.replaceWith(new UserBio(data).toElement());

        save.remove();
        card.classList.remove('Editing');
        Toast.show(Strings.for('ClientStatus').profileSaved || '');
    }

    static async #confirmGoogleDelete(button) {
        if (!await Dialog.confirm(Strings.for('UserClient').deleteGoogleConfirm || '')) {
            return;
        }
        window.location = ClientConfig.siteURL() + '/auth-google?intent=delete';
    }

    static async #resendVerification(button) {
        Working.start(button);
        const result = await Api.post('/api/resend-verification');
        if (!result) {
            Working.stop(button);
            return;
        }
        button.textContent = Strings.for('ClientStatus').sent || '';
    }

}

ReadyHandler.add(User.init);

    return { User };
})();
export const User = UserModule.User;

// OtherUser.js
const OtherUserModule = (() => {
class OtherUser extends User {
    userId = null;
    slug = null;
    title = null;
    description = null;
    image = null;
    createdAt = null;
    blockedByViewer = false;
    blockedByOther = false;
    friendshipStatus = null;
    friendshipSentByViewer = null;
    isMod = false;
    // A Fediverse account, and whether the viewer follows it - what the card
    // offers instead of friendship. See toElement().
    remote = false;
    following = false;
    friendshipId = null;
    element = null;

    beforeActions() {
        return [];
    }

    /**
     * Mirrors UserFollowButton and UserModButton. Each is written in two places
     * here - once when the card is built, once when the server answers - so the
     * wording lives in one method rather than being got right twice.
     */
    static followName(following) {
        const words = Strings.for('UserFollowButton', { follow: 'Follow', unfollow: 'Unfollow' });

        return following ? words.unfollow : words.follow;
    }

    static modName(isMod) {
        const words = Strings.for('UserModButton', { make: 'Make Mod', remove: 'Remove Mod' });

        return isMod ? words.remove : words.make;
    }

    toElement() {
        const div = super.toElement();
        div.classList.add('OtherUser');

        if (this.friendshipId) {
            div.dataset.friendshipId = this.friendshipId;
        }

        const is_self = ClientConfig.get('currentUserId') !== null && Number(ClientConfig.get('currentUserId')) === Number(this.userId);

        if (ClientConfig.get('currentUserId') === null || is_self) {
            this.element = div;
            return div;
        }

        if (this.blockedByViewer) {
            const unblock_button = document.createElement('button');
            unblock_button.type = 'button';
        unblock_button.className = 'Button UserUnblockButton';
            unblock_button.dataset.userId = this.userId;
            unblock_button.textContent = Strings.for('UserUnblockButton', { name: 'Unblock' }).name;
            div.appendWithSpace(unblock_button);
        } else if (!this.blockedByOther) {
            const sent_by_viewer = this.friendshipStatus === 'pending' && this.friendshipSentByViewer;

            const actions = document.createElement('div');
        actions.className = 'OtherUserActions';

            this.beforeActions().forEach((button) => actions.appendWithSpace(button));

            // Mirrors OtherUser.php: a Fediverse account can't hold up its end
            // of a friendship - there is no person on this side of it - so the
            // mutual action is replaced by the one-way one that does mean
            // something. Messaging stays, which is the whole point of holding
            // a shadow account for them.
            if (this.remote) {
                const follow_button = document.createElement('button');
                follow_button.type = 'button';
                follow_button.className = this.following
                    ? 'Button UserFollowButton Removing'
                    : 'Button UserFollowButton';
                follow_button.dataset.userId = this.userId;
                follow_button.dataset.following = this.following ? '1' : '0';
                follow_button.textContent = OtherUser.followName(this.following);
                actions.appendWithSpace(follow_button);
            } else if (this.friendshipStatus === null || sent_by_viewer) {
                const friend_button = document.createElement('button');
                friend_button.type = 'button';
                friend_button.className = sent_by_viewer
                    ? 'Button FriendRequestButton Removing'
                    : 'Button FriendRequestButton';
                friend_button.dataset.userId = this.userId;
                friend_button.dataset.sent = sent_by_viewer ? '1' : '0';
                const words = Strings.for('OtherUserClient');
                friend_button.textContent = sent_by_viewer ? words.cancelRequest || '' : words.addFriend || '';
                actions.appendWithSpace(friend_button);
            }

            const message_link = document.createElement('a');
            message_link.className = 'Button';
            message_link.href = ClientConfig.siteURL() + '/messages/' + this.slug;
            message_link.textContent = Strings.for('OtherUser', { message: 'Message' }).message;

            const block_button = document.createElement('button');
            block_button.type = 'button';
            block_button.className = 'Button UserBlockButton';
            block_button.dataset.userId = this.userId;
            block_button.textContent = Strings.for('UserBlockButton', { name: 'Block' }).name;

            let report_or_ban_button = null;

            if (Number(this.userId) !== 1) {
                if (ClientConfig.get('currentUserCanModerate')) {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Button UserBanButton';
                    report_or_ban_button.dataset.userId = this.userId;
                    report_or_ban_button.textContent = Strings.for('OtherUserClient').ban || '';
                } else {
                    report_or_ban_button = document.createElement('button');
                    report_or_ban_button.type = 'button';
                    report_or_ban_button.className = 'Button ReportButton';
                    report_or_ban_button.dataset.targetType = 'user';
                    report_or_ban_button.dataset.targetId = this.userId;
                    report_or_ban_button.textContent = Strings.for('ReportButton', { name: 'Report' }).name;
                }
            }

            actions.appendWithSpace(message_link);

            // Mirrors OtherUser.php: friendship happens here, between two
            // people who both signed up, so a Fediverse account has none on
            // this site to look at.
            if (!this.remote) {
                const friends_link = document.createElement('a');
                friends_link.className = 'Button';
                friends_link.href = ClientConfig.siteURL() + '/users/' + this.slug + '/friends';
                friends_link.textContent = Strings.for('OtherUserClient').viewFriends || '';
                actions.appendWithSpace(friends_link);
            }

            if (this.friendshipStatus === 'accepted') {
                const remove_friend_button = document.createElement('button');
                remove_friend_button.type = 'button';
                remove_friend_button.className = 'Button FriendRemoveButton';
                remove_friend_button.dataset.userId = this.userId;
                remove_friend_button.textContent = Strings.for('FriendRemoveButton', { name: 'Remove Friend' }).name;
                actions.appendWithSpace(remove_friend_button);
            }

            // Members only: moderating happens by signing in here, which
            // nobody on another server can do.
            if (Number(ClientConfig.get('currentUserId')) === 1 && !this.remote) {
                const mod_button = document.createElement('button');
                mod_button.type = 'button';
                mod_button.className = 'Button UserModButton';
                mod_button.dataset.userId = this.userId;
                mod_button.dataset.isMod = this.isMod ? '1' : '0';
                mod_button.textContent = OtherUser.modName(this.isMod);
                actions.appendWithSpace(mod_button);
            }

            actions.appendWithSpace(block_button);

            if (report_or_ban_button !== null) {
                actions.appendWithSpace(report_or_ban_button);
            }

            div.appendWithSpace(actions);
        }

        this.element = div;
        return div;
    }

    // ----------------------------------------------------------------
    // Static action handlers
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', async (event) => {
            const friendBtn = event.target.closest('.FriendRequestButton');
            if (friendBtn) {
                OtherUser.#sendFriendRequest(friendBtn);
                return;
            }

            const followBtn = event.target.closest('.UserFollowButton');
            if (followBtn) {
                OtherUser.#toggleFollow(followBtn);
                return;
            }

            const blockBtn = event.target.closest('.UserBlockButton');
            if (blockBtn) {
                OtherUser.#block(blockBtn);
                return;
            }

            const removeFriendBtn = event.target.closest('.FriendRemoveButton');
            if (removeFriendBtn) {
                OtherUser.#removeFriend(removeFriendBtn);
                return;
            }

            const modBtn = event.target.closest('.UserModButton');
            if (modBtn) {
                OtherUser.#toggleMod(modBtn);
                return;
            }

            const unblockBtn = event.target.closest('.UserUnblockButton');
            if (unblockBtn) {
                OtherUser.#unblock(unblockBtn);
                return;
            }

            const acceptBtn = event.target.closest('.FriendRequestAcceptButton');
            if (acceptBtn) {
                OtherUser.#acceptFriendRequest(acceptBtn);
                return;
            }

            const denyBtn = event.target.closest('.FriendRequestDenyButton');
            if (denyBtn) {
                OtherUser.#denyFriendRequest(denyBtn);
                return;
            }

            const banBtn = event.target.closest('.UserBanButton');
            if (banBtn) {
                OtherUser.#ban(banBtn);
            }
        });
    }

    static async #sendFriendRequest(button) {
        Working.start(button);
        try {
            const result = await Api.post('/api/friend-request', { userId: button.dataset.userId });
            if (!result) return;
            button.dataset.sent = result.sent ? '1' : '0';
            const words = Strings.for('OtherUserClient');
            button.textContent = result.sent ? words.cancelRequest || '' : words.addFriend || '';
            button.classList.toggle('Removing', result.sent);
        } finally {
            Working.stop(button);
        }
    }

    static async #toggleFollow(button) {
        const id = button.dataset.userId;
        const following = button.dataset.following === '1';
        Working.start(button);
        try {
            const result = await Api.post(following ? '/api/unfollow-remote' : '/api/follow-user', { userId: id });
            if (!result) return;
            button.dataset.following = result.following ? '1' : '0';
            button.textContent = OtherUser.followName(result.following);
            button.classList.toggle('Removing', result.following);
        } finally {
            Working.stop(button);
        }
    }

    static async #block(button) {
        if (!await Dialog.confirm(Strings.for('OtherUserClient').blockConfirm || '')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/block', { userId: button.dataset.userId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.OtherUser'));
        } finally {
            Working.stop(button);
        }
    }

    static async #removeFriend(button) {
        if (!await Dialog.confirm(Strings.for('OtherUserClient').removeFriendConfirm || '')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/remove-friend', { userId: button.dataset.userId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.OtherUser'));
        } finally {
            Working.stop(button);
        }
    }

    static async #toggleMod(button) {
        const id = button.dataset.userId;
        const isMod = button.dataset.isMod === '1';
        Working.start(button);
        try {
            const result = await Api.post('/api/set-mod', { userId: id, isMod: !isMod });
            if (!result) return;
            button.dataset.isMod = result.isMod ? '1' : '0';
            button.textContent = OtherUser.modName(result.isMod);
        } finally {
            Working.stop(button);
        }
    }

    static async #unblock(button) {
        const id = button.dataset.userId;
        const card = button.closest('.OtherUser');
        Working.start(button);
        try {
            const result = await Api.post('/api/unblock', { userId: id });
            if (!result) return;
            card.replaceWith(OtherUser.fromData(result).toElement());
        } finally {
            Working.stop(button);
        }
    }

    static async #acceptFriendRequest(button) {
        const friendshipId = button.dataset.friendshipId;
        Working.start(button);
        const result = await Api.post('/api/accept-friend', { friendshipId });
        if (!result) {
            Working.stop(button);
            return;
        }
        const card = button.closest('.OtherUser');
        if (card && result.userId) {
            const newCard = OtherUser.fromData(result).toElement();
            const pendingList = card.closest('.UserList[data-list-type="incoming"]');
            if (pendingList) {
                const friendsList = document.querySelector('.UserList[data-list-type="friends"]');
                if (friendsList) {
                    friendsList.prepend(list_item(newCard));
                }
                DOMUtils.slideOut(card);
                if (pendingList.querySelectorAll('li:not(.SlidingOut) .OtherUser').length === 0) {
                    DOMUtils.slideOut(pendingList.closest('.UserSection') || pendingList);
                }
            } else {
                card.replaceWith(newCard);
            }
        }
    }

    static async #denyFriendRequest(button) {
        Working.start(button);
        const result = await Api.post('/api/deny-friend', { friendshipId: button.dataset.friendshipId });
        if (!result) {
            Working.stop(button);
            return;
        }
        DOMUtils.slideOut(button.closest('.OtherUser'));
    }

    static async #ban(button) {
        const reason = await Dialog.prompt(
            Strings.for('OtherUserClient').banConfirm || '',
            {
                confirmLabel: Strings.for('OtherUserClient').ban || '',
                placeholder: Strings.for('OtherUserClient').banPlaceholder || '',
            }
        );
        if (reason === null) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/ban', { userId: button.dataset.userId, reason });
            if (!result) return;
            button.textContent = Strings.for('OtherUserClient').banned || '';
        } finally {
            Working.stop(button);
        }
    }
}


ReadyHandler.add(OtherUser.init);

    return { OtherUser };
})();
export const OtherUser = OtherUserModule.OtherUser;

// ReceivedFriendRequest.js
const ReceivedFriendRequestModule = (() => {
/**
 * Client twin of ReceivedFriendRequest.php: an incoming request's card, which
 * is the person's ordinary card with Accept and Deny in front of the actions.
 */
class ReceivedFriendRequest extends OtherUser {
    beforeActions() {
        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'Button FriendRequestAcceptButton';
        accept.dataset.friendshipId = this.friendshipId;
        accept.textContent = Strings.for('FriendRequestAcceptButton', { name: 'Accept' }).name;

        const deny = document.createElement('button');
        deny.type = 'button';
        deny.className = 'Button FriendRequestDenyButton';
        deny.dataset.friendshipId = this.friendshipId;
        deny.textContent = Strings.for('FriendRequestDenyButton', { name: 'Deny' }).name;

        return [accept, deny];
    }

    toElement() {
        const div = super.toElement();
        div.classList.add('ReceivedFriendRequest');
        return div;
    }
}

    return { ReceivedFriendRequest };
})();
export const ReceivedFriendRequest = ReceivedFriendRequestModule.ReceivedFriendRequest;

// BannedUser.js
const BannedUserModule = (() => {
/**
 * Client-side mirror of the PHP BannedUser class - one entry on the admin
 * Banned Users page (identity plus an Unban button), used when entries arrive
 * as JSON via infinite scroll or the banned-user search.
 */
class BannedUser extends User {
    userId = null;
    slug = null;
    title = null;
    image = null;

    toElement() {
        const div = document.createElement('div');
        div.className = 'User BannedUser';
        div.dataset.userId = this.userId;

        const row = document.createElement('div');
        row.className = 'BannedUserRow';

        row.appendWithSpace(this.header());

        const unban = document.createElement('button');
        unban.type = 'button';
        unban.className = 'Button UserUnbanButton';
        unban.dataset.userId = this.userId;
        unban.textContent = Strings.for('UserUnbanButton', { name: 'Unban' }).name;
        row.appendWithSpace(unban);

        div.appendWithSpace(row);

        return div;
    }

    // ----------------------------------------------------------------
    // Static action handlers (unban)
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', async (event) => {
            const unbanBtn = event.target.closest('.UserUnbanButton');
            if (unbanBtn) {
                BannedUser.#unban(unbanBtn);
            }
        });
    }

    static async #unban(button) {
        if (!await Dialog.confirm(Strings.for('MiscellaneousClient').unbanUser || '')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/unban', { userId: button.dataset.userId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.BannedUser'));
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(BannedUser.init);

    return { BannedUser };
})();
export const BannedUser = BannedUserModule.BannedUser;

// Poll.js
const PollModule = (() => {
/**
 * Client twin of Poll.php - the same DOM from the same payload, class for
 * class, and the voting that turns one into the other.
 *
 * Whether the controls or the answers are shown is not decided here: it depends
 * on who is asking and whether they have voted, which only the server knows, so
 * showResults arrives already settled.
 */
class Poll {
    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.PollVoteButton');
            if (!button) return;

            const poll = button.closest('.Poll');
            if (!poll) return;

            const chosen = [...poll.querySelectorAll('.PollVoteInput:checked')].map((input) => Number(input.value));

            if (chosen.length === 0) return;

            // Disabled for the round trip rather than after it: a second click
            // while the first is in flight comes back refused as a repeat vote,
            // and the reader would be told their own answer failed.
            Working.start(button);

            const data = await Api.post('/api/poll-vote', {
                pollId: Number(button.dataset.pollId),
                optionIds: chosen,
            });

            if (!data) {
                Working.stop(button);
                return;
            }

            poll.replaceWith(Poll.fromData(data.poll).element());
        });
    }

    pollId = null;
    multiple = false;
    endsAt = null;
    closed = false;
    showResults = false;
    voterCount = 0;
    options = [];

    static fromData(data) {
        const poll = new Poll();
        Object.assign(poll, data);

        return poll;
    }

    element() {
        const poll = document.createElement('section');
        poll.className = 'Poll';

        poll.appendWithSpace(this.optionListElement());

        // The button is what turns a set of ticked boxes into a vote, so it
        // exists only while there is a vote left to cast.
        if (!this.showResults) {
            poll.appendWithSpace(this.voteButtonElement());
        }

        poll.appendWithSpace(this.tallyElement());

        return poll;
    }

    optionListElement() {
        const list = document.createElement('ul');
        list.className = 'PollOptionList';

        for (const option of this.options) {
            const item = document.createElement('li');
            item.appendWithSpace(this.optionElement(option));
            list.appendWithSpace(item);
        }

        return list;
    }

    optionElement(option) {
        const wrapper = document.createElement('div');
        wrapper.className = 'PollOption';
        wrapper.appendWithSpace(this.showResults ? Poll.resultElement(option) : this.controlElement(option));

        return wrapper;
    }

    controlElement(option) {
        const label = document.createElement('label');
        label.className = 'PollOptionControl';

        const input = document.createElement('input');
        input.className = 'PollVoteInput';
        input.type = this.multiple ? 'checkbox' : 'radio';
        // One name across the group, which is what makes a set of radios a
        // single choice rather than several independent ones.
        input.name = 'pollOption';
        input.value = String(option.pollOptionId);

        label.appendWithSpace(input);
        label.appendWithSpace(Poll.titleElement(option.title));

        return label;
    }

    static resultElement(option) {
        const result = document.createElement('div');
        result.className = option.chosen ? 'PollOptionResult Chosen' : 'PollOptionResult';

        result.appendWithSpace(Poll.titleElement(option.title));

        const meter = document.createElement('meter');
        meter.className = 'PollOptionMeter';
        meter.setAttribute('value', String(option.share));
        meter.setAttribute('min', '0');
        meter.setAttribute('max', '100');
        result.appendWithSpace(meter);

        const share = document.createElement('span');
        share.className = 'PollOptionShare';
        // Trailing space, as PollOptionShare.php writes it - the count follows
        // immediately and nothing else separates them.
        share.appendChild(document.createTextNode(option.share + '% '));

        const votes = document.createElement('span');
        votes.className = 'PollOptionVotes';
        votes.textContent = Strings.plural(Strings.for('Poll').votes || {}, option.voteCount);
        share.appendChild(votes);

        result.appendWithSpace(share);

        return result;
    }

    static titleElement(text) {
        const title = document.createElement('span');
        title.className = 'PollOptionTitle';
        title.textContent = text;

        return title;
    }

    voteButtonElement() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button PollVoteButton';
        button.dataset.pollId = String(this.pollId);
        button.textContent = Strings.for('Poll').vote || '';

        return button;
    }

    tallyElement() {
        const tally = document.createElement('footer');
        tally.className = 'PollTally';

        const talliedWords = Strings.for('PollTally', {
            voters: { one: '1 person voted ', other: '{count} people voted ' },
        });
        tally.appendChild(document.createTextNode(Strings.plural(talliedWords.voters, this.voterCount)));

        const deadline = document.createElement('span');
        deadline.className = 'PollDeadline';

        if (this.closed) {
            deadline.textContent = Strings.for('PollDeadline', { final: 'Final result' }).final;
        } else {
            Poll.appendClosesSentence(deadline, this.endsAt);
        }

        tally.appendChild(deadline);

        return tally;
    }

    /**
     * Mirrors PollDeadline::toDOM()'s non-closed branch: the two text nodes
     * either side of a <time> that carries only the remaining time, so a
     * translation can put the deadline anywhere in the sentence.
     */
    static appendClosesSentence(into, endsAt) {
        const words = Strings.for('PollDeadline', { closes: { before: 'Closes ', after: '' } });

        const remaining = document.createElement('time');
        remaining.dateTime = parse_server_date(endsAt).toISOString();
        remaining.textContent = Poll.remaining(endsAt);

        into.append(words.closes.before || '', remaining, words.closes.after || '');
    }

    /** Mirrors PollDeadline::remaining() - the largest unit that still says something useful. */
    static remaining(endsAt) {
        // The house parser and the corrected clock, the same pair every
        // timestamp on the page counts by: a bare "date time" string read by
        // new Date() is local time in some browsers, and the machine in front
        // of the reader is not promised to be set right.
        const seconds = Math.max(0, Math.floor((parse_server_date(endsAt).getTime() - RelativeTime.now()) / 1000));
        const words = Strings.for('PollDeadline', {
            days: { one: 'in {count} day', other: 'in {count} days' },
            hours: { one: 'in {count} hour', other: 'in {count} hours' },
            minutes: { one: 'in {count} minute', other: 'in {count} minutes' },
            underMinute: 'in under a minute',
        });

        for (const [size, key] of [[86400, 'days'], [3600, 'hours'], [60, 'minutes']]) {
            if (seconds >= size) {
                return Strings.plural(words[key], Math.floor(seconds / size));
            }
        }

        return words.underMinute;
    }
}

ReadyHandler.add(Poll.init);

    return { Poll };
})();
export const Poll = PollModule.Poll;

// PostRepostButton.js
const PostRepostButtonModule = (() => {
/**
 * Passing a post on. The button carries its own state and count, so it flips in
 * place - the feed itself is not rebuilt, because reposting reorders what is
 * below and moving the page under the reader is worse than a stale ordering
 * they will see corrected on the next load.
 */
class PostRepostButton {
    /** Mirrors PostRepostButton::label() - the two must agree or the button rewords itself when pressed. */
    /** The arrows, with the count beside them once there is one. Mirrors PostRepostButton.php. */
    static GLYPH = '🔁';

    static label(reposted, count) {
        return count > 0 ? PostRepostButton.GLYPH + ' ' + count : PostRepostButton.GLYPH;
    }

    /** Mirrors PostRepostButton::toDOM() - the accessible name, since the glyph alone does not say it. */
    static name(reposted) {
        const words = Strings.for('PostRepostButton', { undo: 'Undo Repost', repost: 'Repost' });
        return reposted ? words.undo : words.repost;
    }

    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.PostRepostButton');

            if (button) {
                PostRepostButton.#toggle(button);
            }
        });
    }

    static async #toggle(button) {
        const postId = button.closest('.Post')?.dataset.postId;

        if (!postId) return;

        Working.start(button);

        try {
            const result = await Api.post('/api/repost', { postId: Number(postId) });

            if (!result) return;

            button.classList.toggle('Removing', result.reposted);
            button.textContent = PostRepostButton.label(result.reposted, result.count);
            button.setAttribute('aria-pressed', result.reposted ? 'true' : 'false');
            const name = PostRepostButton.name(result.reposted);
            button.setAttribute('aria-label', name);
            button.setAttribute('title', name);
        } finally {
            Working.stop(button);
        }
    }
}

ReadyHandler.add(PostRepostButton.init);

    return { PostRepostButton };
})();
export const PostRepostButton = PostRepostButtonModule.PostRepostButton;

// SkinTone.js
const SkinToneModule = (() => {
/**
 * Client twin of SkinTone.php: the reader's chosen skin tone, applied to an
 * emoji this site shows them.
 *
 * A card can be rendered by either side, so the same thumb has to come out of
 * both - the tone travels to the browser on the config cookie as
 * currentUserSkinTone.
 */
class SkinTone {
    /** The Fitzpatrick modifiers, by the scale the emoji picker reports. */
    static MODIFIERS = {
        1: 0x1f3fb,
        2: 0x1f3fc,
        3: 0x1f3fd,
        4: 0x1f3fe,
        5: 0x1f3ff,
    };

    /** Turns an emoji "text" presentation into its "emoji" one. */
    static VARIATION_SELECTOR = String.fromCodePoint(0xfe0f);

    /**
     * The emoji as this reader should see it. Unchanged where they have chosen
     * nothing, chosen the default, or the emoji is not one that takes a tone.
     */
    static applied(emoji, tone) {
        const modifier = SkinTone.MODIFIERS[parseInt(tone, 10)];

        if (!modifier) return emoji;

        // A modifier replaces the variation selector rather than following it -
        // see SkinTone.php.
        const base = emoji.endsWith(SkinTone.VARIATION_SELECTOR)
            ? emoji.slice(0, -SkinTone.VARIATION_SELECTOR.length)
            : emoji;

        return base + String.fromCodePoint(modifier);
    }

    /** What the reader has chosen, as the config cookie reports it. */
    static forViewer() {
        return ClientConfig.get('currentUserSkinTone');
    }
}

    return { SkinTone };
})();
export const SkinTone = SkinToneModule.SkinTone;

// Post.js
const PostModule = (() => {
class Post {
    postId = null;
    userId = null;
    parentId = null;
    title = null;
    description = null;
    descriptionDelta = null;
    descriptionTruncated = false;
    seeMoreURL = null;
    keywords = null;
    linkURL = null;
    createdAt = null;
    editedAt = null;
    latitude = null;
    longitude = null;
    placeLabel = null;
    translatable = null;
    // What the post says it was written in, when its sender said so. Null for
    // anything written here, since there is no way to declare one yet.
    language = null;
    quotedPost = null;
    poll = null;
    // What this reply answers and where its thread began - null on a post that
    // starts one. Mirrors Post.php's ThreadContext.
    threadContext = null;
    repostedBy = null;
    // A post that came from another server - it carries no share button,
    // because the address worth passing on is the original.
    remote = false;
    reposted = false;
    repostCount = 0;
    rawDescriptionDelta = null;
    items = [];
    imageAltText = null;
    sensitive = false;
    contentWarning = null;
    replyCount = 0;
    likeCount = 0;
    liked = false;
    bookmarked = false;
    author = null;
    element = null;

    static fromData(data) {
        const post = new Post();
        Object.assign(post, data);
        return post;
    }

    /** The glyphs the action bar shows, mirroring the PHP button classes. */
    static GLYPHS = {
        share: '📤',
        reply: '💬',
        translate: '🌐',
        showOriginal: '↩️',
        like: '👍',
        repost: '🔁',
        quote: '✍️',
        bookmark: '🔖',
    };

    /** Mirrors PostLikeButton::label() - the two must agree or the button changes shape when pressed. */
    static likeLabel(liked, count) {
        // The reader's own thumb, whichever tone they chose - mirrors
        // PostLikeButton::label(). The thumb is the same whether or not they
        // have liked it; the colour is what says so.
        const thumb = SkinTone.applied(Post.GLYPHS.like, SkinTone.forViewer());

        return count > 0 ? thumb + ' ' + count : thumb;
    }

    /** Mirrors PostLikeButton's aria-label - the name the glyph does not say. */
    static likeName(liked) {
        const words = Strings.for('PostLikeButton', { like: 'Like', unlike: 'Unlike' });

        return liked ? words.unlike : words.like;
    }

    /** Mirrors PostBookmarkButton::label() - its name, since the glyph never changes. */
    static bookmarkLabel(bookmarked) {
        const words = Strings.for('PostBookmarkButton', { remove: 'Remove Bookmark', add: 'Bookmark' });
        return bookmarked ? words.remove : words.add;
    }

    /** Names a glyph-only control for anything not looking at it. */
    static nameIt(element, name) {
        element.setAttribute('aria-label', name);
        element.setAttribute('title', name);
    }

    threadContextToElement() {
        const line = document.createElement('div');
        line.className = 'ThreadContext';

        const words = Strings.for('ThreadContext', {
            response: { before: 'In response to ', after: '' },
            untitled: 'this post',
            jumpToStart: 'Jump to Start',
        });

        const response = document.createElement('span');
        response.appendWithSpace(document.createTextNode(words.response.before || ''));

        const parent = document.createElement('a');
        parent.href = ClientConfig.siteURL() + '/users/' + this.threadContext.parentUsername + '/' + this.threadContext.parentId;
        // Mirrors ThreadContext::labelFor(): the server ships null rather than
        // baking "this post" in at fetch time, so the fallback words are said
        // here, in whichever language this renderer is running in.
        parent.textContent = this.threadContext.parentLabel ?? words.untitled;
        response.appendWithSpace(parent);
        response.appendWithSpace(document.createTextNode(words.response.after || ''));

        line.appendWithSpace(response);

        // Only where the start is somewhere else - see ThreadContext.php.
        if (this.threadContext.rootId && this.threadContext.rootId !== this.threadContext.parentId) {
            const start = document.createElement('a');
            start.className = 'ThreadStartLink';
            start.href = ClientConfig.siteURL() + '/users/' + this.threadContext.rootUsername + '/' + this.threadContext.rootId;
            start.textContent = words.jumpToStart;
            line.appendWithSpace(start);
        }

        return line;
    }

    repostAttributionToElement() {
        const line = document.createElement('div');
        line.className = 'RepostAttribution';

        const words = Strings.for('RepostAttribution', { attribution: { before: '', after: ' reposted' } });

        line.appendWithSpace(document.createTextNode(words.attribution.before || ''));

        const who = document.createElement('a');
        who.href = ClientConfig.siteURL() + '/users/' + this.repostedBy.slug + '/';
        who.textContent = this.repostedBy.title || this.repostedBy.slug;
        line.appendWithSpace(who);

        line.appendWithSpace(document.createTextNode(words.attribution.after || ''));

        return line;
    }

    authorBylineToElement() {
        const byline = document.createElement('header');
        byline.className = 'PostByline';

        byline.appendWithSpace(User.fromData(this.author).header());

        const meta = document.createElement('div');
        meta.className = 'PostMeta';

        if (this.createdAt) {
            const timestamp_link = document.createElement('a');
        timestamp_link.className = 'TimestampLink';
            timestamp_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;

            const timestamp = new RelativeTime(this.createdAt, 'short').toDOM();
            timestamp_link.appendWithSpace(timestamp);

            meta.appendWithSpace(timestamp_link);
        }

        // Mirrors PostLocationLink.php - the place name the server resolved
        // from its own gazetteer, or coordinates when nowhere is close enough
        // to name, linking to the map opened on where the post was filed.
        if (this.latitude !== null && this.longitude !== null) {
            const location_link = document.createElement('a');
            location_link.className = 'PostLocationLink';
            location_link.href = ClientConfig.siteURL() + '/map?lat=' + encodeURIComponent(this.latitude) + '&lng=' + encodeURIComponent(this.longitude);
            location_link.title = Strings.for('PostClient').mapTitle || '';
            location_link.textContent = this.placeLabel || (this.latitude.toFixed(4) + ', ' + this.longitude.toFixed(4));
            meta.appendWithSpace(location_link);
        }

        if (this.editedAt) {
            const edited_marker = document.createElement('span');
        edited_marker.className = 'PostEditedMarker';
            edited_marker.title = RelativeTime.dateAndTime(this.editedAt);
            edited_marker.textContent = Strings.for('PostEditedMarker', { label: '(edited)' }).label;
            meta.appendWithSpace(edited_marker);
        }

        byline.appendWithSpace(meta);

        return byline;
    }

    linkItemToElement() {
        const wrapper = document.createElement('figure');
        wrapper.className = 'FeedItem LinkItem';

        // Always the anchor with target/rel, as LinkItem.php renders it; only the
        // href is withheld for a scheme we won't link to. Defence in depth either
        // way - create/edit-post already refuse anything but http(s).
        const link = document.createElement('a');
        link.target = '_blank';
        link.rel = 'noopener';

        if (DeltaRenderer.isSafeLink(this.linkURL, DeltaRenderer.ALLOWED_LINK_SCHEMES)) {
            link.href = this.linkURL;
        }

        const link_image = this.items.find((item) => item.itemType === 'ImageItem');

        if (link_image) {
            const image = document.createElement('img');
            image.className = 'LinkItemImage';
            image.src = link_image.image;
            image.alt = Strings.for('LinkItem', { alt: 'Link preview image' }).alt;
            link.appendWithSpace(image);
        }

        const text = document.createElement('div');
        text.className = 'LinkItemText';

        if (this.title) {
            const heading = document.createElement('h3');
            heading.textContent = this.title;
            text.appendWithSpace(heading);
        }

        if (this.description) {
            const body = document.createElement('div');
            body.className = 'PostBody';
            body.textContent = this.description;
            text.appendWithSpace(body);
        }

        text.appendWithSpace(document.createTextNode(this.linkURL));
        link.appendWithSpace(text);
        wrapper.appendWithSpace(link);

        return wrapper;
    }

    itemToElement(item, deferred = false) {
        const wrapper = document.createElement('figure');
        wrapper.className = 'FeedItem ' + item.itemType;

        // Mirrors FeedItem.php/ImageItem.php: the row's identity for the post
        // editor, and the raw alt text - distinct from the img's alt below,
        // which falls back to "Image" and so can't be read back as the
        // author's own words.
        if (item.itemId) {
            wrapper.setAttribute('data-item-id', item.itemId);
        }
        if (item.itemType === 'ImageItem' && item.altText) {
            wrapper.setAttribute('data-alt-text', item.altText);
        }

        if (item.itemType === 'VideoItem') {
            const video = document.createElement('video');
            video.controls = true;

            if (deferred) {
                video.dataset.src = item.src;
                if (item.image) {
                    video.dataset.poster = item.image;
                }
            } else {
                video.src = item.src;
                if (item.image) {
                    video.poster = item.image;
                }
            }

            wrapper.appendWithSpace(video);
        } else if (item.itemType === 'AudioItem') {
            // Mirrors AudioItem.php: the spectrum goes above the controls, and
            // SpectrumAnalyser.js finds it by sitting beside the player.
            const spectrum = document.createElement('canvas');
            spectrum.className = 'SpectrumAnalyser';
            spectrum.width = 600;
            spectrum.height = 192;
            spectrum.setAttribute('aria-hidden', 'true');
            wrapper.appendWithSpace(spectrum);

            // Loaded here rather than only from main.js: a post can scroll in
            // long after page load, and the guard there ran once. Importing a
            // module twice costs nothing - the second call is the cached one.
            import('/scripts/Controllers.js');

            const audio = document.createElement('audio');
            audio.className = 'Audio';
            audio.controls = true;

            if (deferred) {
                audio.dataset.src = item.src;
            } else {
                audio.src = item.src;
            }

            wrapper.appendWithSpace(audio);
        } else {
            const img = document.createElement('img');
            img.loading = 'lazy';
            img.decoding = 'async';

            // A remote attachment describes itself; ours is described by the
            // post it belongs to. Same order of preference as FeedItem's.
            img.alt = item.altText || this.imageAltText || Strings.for('PostClient').image || '';

            // The feed shows the thumbnail and carries the display-size URL for
            // fullscreen to swap in, exactly as ImageItem.php renders it.
            const thumbnail = item.image || item.src;

            if (deferred) {
                img.dataset.src = thumbnail;
            } else {
                img.src = thumbnail;
            }

            img.dataset.fullSrc = item.src;

            wrapper.appendWithSpace(img);
        }

        return wrapper;
    }

    mediaFullscreenButtonElement() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button MediaFullscreenButton';
        button.setAttribute('aria-label', Strings.for('PostClient').fullscreen || '');
        button.textContent = '⛶';
        return button;
    }

    /**
     * The disclosure SensitiveMedia.php renders, built the same way: a real
     * <details>, so opening it needs no script of ours.
     */
    static sensitiveCover(media) {
        const cover = document.createElement('details');
        cover.className = 'SensitiveMedia';

        const summary = document.createElement('summary');
        summary.className = 'SensitiveMediaSummary';
        summary.textContent = Strings.for('PostClient').sensitiveMedia || '';

        cover.appendWithSpace(summary);
        cover.appendWithSpace(media);

        return cover;
    }

    /**
     * The disclosure ContentWarning.php renders. Unlike the media cover there
     * is no reader preference that opens it: a warning is a sentence about
     * this post in particular, so there is nothing to have decided in advance.
     */
    static contentWarningGate(warning) {
        const gate = document.createElement('details');
        gate.className = 'ContentWarning';

        const summary = document.createElement('summary');
        summary.className = 'ContentWarningSummary';
        summary.textContent = warning;

        gate.appendWithSpace(summary);

        return gate;
    }

    itemsToCarousel() {
        const carousel = document.createElement('div');
        carousel.className = 'Carousel';

        const track = document.createElement('div');
        track.className = 'CarouselTrack';

        const initial_eager_items = ClientConfig.get('carouselEagerItems');

        this.items.forEach((item, index) => {
            const slide = document.createElement('div');
            slide.className = 'CarouselSlide' + (index === 0 ? ' Active' : '');
            slide.appendWithSpace(this.itemToElement(item, index > initial_eager_items));
            track.appendWithSpace(slide);
        });

        carousel.appendWithSpace(track);
        carousel.appendWithSpace(this.mediaFullscreenButtonElement());

        if (this.items.length > 1) {
            const prev_button = document.createElement('button');
            prev_button.type = 'button';
            prev_button.className = 'Button CarouselPrevButton';
            prev_button.setAttribute('aria-label', Strings.for('PostClient').previous || '');
            prev_button.textContent = '‹';
            carousel.appendWithSpace(prev_button);

            const next_button = document.createElement('button');
            next_button.type = 'button';
            next_button.className = 'Button CarouselNextButton';
            next_button.setAttribute('aria-label', Strings.for('PostClient').next || '');
            next_button.textContent = '›';
            carousel.appendWithSpace(next_button);

            const counter = document.createElement('div');
            counter.className = 'CarouselCounter';
            counter.textContent = '1 / ' + this.items.length;
            carousel.appendWithSpace(counter);

            const autoplay_button = document.createElement('button');
            autoplay_button.type = 'button';
            autoplay_button.className = 'Button CarouselAutoplayButton';
            autoplay_button.textContent = Strings.for('PostClient').autoplay || '';
            carousel.appendWithSpace(autoplay_button);
        }

        return carousel;
    }

    postElement() {
        const post = document.createElement('div');
        post.className = 'PostContent';

        // Mirrors Post.php - first of all, because a reply read without it is
        // an answer to a question that is not on the page.
        if (this.threadContext) {
            post.appendWithSpace(this.threadContextToElement());
        }

        // Mirrors Post.php - above the byline, because it answers the question
        // the byline raises.
        if (this.repostedBy) {
            post.appendWithSpace(this.repostAttributionToElement());
        }

        if (this.author) {
            post.appendWithSpace(this.authorBylineToElement());
        }

        // Mirrors Post.php: everything the author wrote is built into the
        // warning where there is one, so a spoiler in the words is covered
        // along with one in the pictures. The byline stays outside it.
        const warning = (this.contentWarning || '').trim();
        const target = warning === '' ? post : Post.contentWarningGate(warning);

        if (this.linkURL) {
            target.appendWithSpace(this.linkItemToElement());
        } else {
            if (this.title) {
                const heading = document.createElement('h3');
                heading.textContent = this.title;

                if (this.postId !== null) {
                    const title_link = document.createElement('a');
                    title_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
                    title_link.appendWithSpace(heading);
                    target.appendWithSpace(title_link);
                } else {
                    target.appendWithSpace(heading);
                }
            }

            let media = null;

            if (this.items.length > 1) {
                media = this.itemsToCarousel();
            } else if (this.items.length === 1) {
                media = this.itemToElement(this.items[0]);
                media.appendWithSpace(this.mediaFullscreenButtonElement());
            }

            if (media) {
                // Mirrors Post.php: a reader who has asked to see this media
                // gets it uncovered, the same as the server would have sent it -
                // and a warning already gated it, so a second cover inside the
                // first would only make them ask twice.
                const cover = this.sensitive && warning === '' && !ClientConfig.get('showSensitiveMedia');

                target.appendWithSpace(cover ? Post.sensitiveCover(media) : media);
            }

            if (this.descriptionDelta) {
                const body = DeltaRenderer.render(this.descriptionDelta, this.customEmoji || {}, !this.remote);

                if (this.descriptionTruncated && this.seeMoreURL) {
                    body.appendWithSpace(DeltaRenderer.seeMoreElement(this.seeMoreURL));
                }

                target.appendWithSpace(body);
            }

            // Mirrors Post.php - under the words, since the poll is what the
            // words are asking about.
            if (this.poll) {
                target.appendWithSpace(Poll.fromData(this.poll).element());
            }
        }

        // Mirrors QuotedPost.php: the quoted material under the commentary,
        // byline and a readable slice, linking to the real thing.
        if (this.quotedPost) {
            const quoted = document.createElement('div');
            quoted.className = 'QuotedPost';

            const byline = document.createElement('p');
            byline.className = 'QuotedPostByline';
            byline.textContent = (this.quotedPost.authorTitle || this.quotedPost.slug) + ' · @' + this.quotedPost.slug;
            quoted.appendWithSpace(byline);

            if (this.quotedPost.title) {
                const title = document.createElement('p');
                title.className = 'QuotedPostTitle';
                title.textContent = this.quotedPost.title;
                quoted.appendWithSpace(title);
            }

            if (this.quotedPost.description) {
                const body = document.createElement('p');
                body.textContent = truncate(
                    this.quotedPost.description,
                    ClientConfig.get('quotedPostMaxLength')
                );
                quoted.appendWithSpace(body);
            }

            const link = document.createElement('a');
            link.className = 'QuotedPostLink';
            link.href = ClientConfig.siteURL() + '/users/' + this.quotedPost.slug + '/' + this.quotedPost.postId;
            link.textContent = Strings.for('QuotedPost', { viewLink: 'View the Quoted Post' }).viewLink;
            quoted.appendWithSpace(link);

            target.appendWithSpace(quoted);
        }

        if (target !== post) {
            post.appendWithSpace(target);
        }

        return post;
    }

    toElement() {
        const card = document.createElement('article');
        card.className = 'Post';

        card.dataset.postId = this.postId;
        card.dataset.userId = this.userId;

        if (this.parentId !== null) {
            card.dataset.parentId = this.parentId;
        }

        if (this.keywords) {
            card.dataset.keywords = this.keywords;
        }

        if (this.createdAt) {
            card.dataset.createdAt = parse_server_date(this.createdAt).toISOString().replace(/\.\d{3}Z$/u, '+00:00');
        }

        if (Number(this.userId) === Number(ClientConfig.get('currentUserId'))) {
            card.dataset.descriptionDelta = this.rawDescriptionDelta || '';
            card.dataset.title = this.title || '';
            card.dataset.linkUrl = this.linkURL || '';
            // Mirrors Post.php: a link post's preview picture is an item too,
            // and this asks whether the post is a media post rather than
            // whether it holds one.
            card.dataset.hasMedia = this.items.length > 0 && !this.linkURL ? '1' : '';
            // Mirrors Post.php's data-sensitive - without it the edit form
            // opens unchecked on an AJAX-rendered post and saving a typo fix
            // would silently clear the classification.
            card.dataset.sensitive = this.sensitive ? '1' : '';
            card.dataset.contentWarning = this.contentWarning || '';
        }

        card.appendWithSpace(this.postElement());

        const meta = document.createElement('footer');
        meta.className = 'PostActionBar';

        const actions = document.createElement('div');
        // Mirrors PostActionBar.php - anchored at the start, not the end.
        actions.className = 'PostActionBarActions';

        const logged_in = ClientConfig.get('currentUserId') !== null;

        // Mirrors PostActionBar.php - the share button leads the bar and is
        // visible to everyone, logged in or not, but never on a post from
        // another server: the address worth passing on is the original, not
        // this server's copy of it.
        if (!this.remote) {
            const share_button = document.createElement('button');
            share_button.type = 'button';
            share_button.className = 'Button PostShareButton';
            share_button.dataset.shareUrl = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
            Post.nameIt(share_button, 'Share');
            share_button.textContent = Post.GLYPHS.share;
            actions.appendWithSpace(share_button);
        }

        if (logged_in || this.replyCount > 0) {
            const replies_link = document.createElement('a');
            replies_link.className = 'Button';
            replies_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
            replies_link.textContent = this.replyCount === 0
                ? Post.GLYPHS.reply
                : Post.GLYPHS.reply + ' ' + this.replyCount;
            const reply_words = Strings.for('PostActionBar', {
                reply: 'Reply',
                replies: { one: 'Replies ({count})', other: 'Replies ({count})' },
            });

            Post.nameIt(replies_link, this.replyCount === 0
                ? reply_words.reply
                : Strings.plural(reply_words.replies, this.replyCount));
            actions.appendWithSpace(replies_link);
        }

        if (logged_in) {
            // Mirrors PostActionBar.php: offered only when there is body text
            // to translate and the server has a translator configured.
            if (this.translatable) {
                const translate_button = ToggleButton.build(
                    [Post.GLYPHS.translate, Post.GLYPHS.showOriginal],
                    'PostTranslateButton'
                );
                Post.nameIt(translate_button, 'Translate');
                actions.appendWithSpace(translate_button);
            }

            // Sized by what it says - mirrors PostLikeButton.php.
            const like_button = document.createElement('button');
            like_button.type = 'button';
            like_button.className = 'Button PostLikeButton';
            like_button.textContent = Post.likeLabel(this.liked, this.likeCount);
            if (this.liked) like_button.classList.add('Removing');
            like_button.dataset.liked = this.liked ? '1' : '0';
            like_button.setAttribute('aria-pressed', this.liked ? 'true' : 'false');
            Post.nameIt(like_button, Post.likeName(this.liked));
            actions.appendWithSpace(like_button);

            // Not on your own post - passing on your own writing is what your
            // profile is for, and the bar draws the same line.
            if (Number(this.userId) !== Number(ClientConfig.get('currentUserId'))) {
                const repost_button = document.createElement('button');
                repost_button.type = 'button';
                repost_button.className = 'Button PostRepostButton';
                repost_button.textContent = PostRepostButton.label(this.reposted, this.repostCount);
                if (this.reposted) repost_button.classList.add('Removing');
                repost_button.setAttribute('aria-pressed', this.reposted ? 'true' : 'false');
                Post.nameIt(repost_button, PostRepostButton.name(this.reposted));
                actions.appendWithSpace(repost_button);
            }

            // Mirrors PostActionBar.php - Repost's talkative sibling, a link
            // to the quote-composing page.
            const quote_link = document.createElement('a');
            quote_link.className = 'Button PostQuoteButton';
            quote_link.href = ClientConfig.siteURL() + '/quote/' + this.postId;
            quote_link.textContent = Post.GLYPHS.quote;
            Post.nameIt(quote_link, Strings.for('PostQuoteButton', { name: 'Quote' }).name);
            actions.appendWithSpace(quote_link);

            const bookmark_button = document.createElement('button');
            bookmark_button.type = 'button';
            bookmark_button.className = 'Button PostBookmarkButton';
            bookmark_button.textContent = Post.GLYPHS.bookmark;
            if (this.bookmarked) bookmark_button.classList.add('Removing');
            bookmark_button.dataset.bookmarked = this.bookmarked ? '1' : '0';
            bookmark_button.setAttribute('aria-pressed', this.bookmarked ? 'true' : 'false');
            Post.nameIt(bookmark_button, Post.bookmarkLabel(this.bookmarked));
            actions.appendWithSpace(bookmark_button);

            if (Number(this.userId) === Number(ClientConfig.get('currentUserId'))) {
                const edit_button = document.createElement('button');
                edit_button.type = 'button';
                edit_button.className = 'Button PostEditButton';
                edit_button.textContent = Strings.for('PostEditButton', { name: 'Edit' }).name;
                actions.appendWithSpace(edit_button);

                const delete_button = document.createElement('button');
                delete_button.type = 'button';
                delete_button.className = 'Button PostDeleteButton';
                delete_button.textContent = Strings.for('PostDeleteButton', { name: 'Delete' }).name;
                actions.appendWithSpace(delete_button);
            } else if (Number(this.userId) !== 1) {
                const report_button = document.createElement('button');
                report_button.type = 'button';
                report_button.className = 'Button ReportButton PostReportButton';
                report_button.dataset.targetType = 'post';
                report_button.dataset.targetId = this.postId;
                report_button.textContent = Strings.for('ReportButton', { name: 'Report' }).name;
                actions.appendWithSpace(report_button);
            }
        }

        meta.appendWithSpace(actions);

        card.appendWithSpace(meta);

        this.element = card;

        // The content, not the card: the action bar's buttons are emoji too,
        // and they are furniture sized by their own rules rather than anything
        // somebody wrote.
        this.element.querySelectorAll(EmojiRenderer.CONTENT).forEach(content => EmojiRenderer.render(content));

        const postBody = this.element.querySelector('.PostBody');
        if (postBody && EmojiRenderer.isEmojiOnly(postBody)) {
            this.element.classList.add('emoji-only');
        }

        enhanceCodeBlocks(card);

        return card;
    }

    static init() {
        document.addEventListener('click', (event) => {
            const likeBtn = event.target.closest('.PostLikeButton');
            if (likeBtn) {
                Post.#like(likeBtn);
                return;
            }

            const bookmarkBtn = event.target.closest('.PostBookmarkButton');
            if (bookmarkBtn) {
                Post.#bookmark(bookmarkBtn);
                return;
            }

            const deleteBtn = event.target.closest('.PostDeleteButton');
            if (deleteBtn) {
                Post.#delete(deleteBtn);
                return;
            }

            const translateBtn = event.target.closest('.PostTranslateButton');
            if (translateBtn) {
                Post.#translate(translateBtn);
                return;
            }

        });

        Post.enhanceExisting();
    }

    static enhanceExisting() {
       document.querySelectorAll('.Post').forEach(card => enhanceCodeBlocks(card));
    }

    /**
     * Each translated post's original body element, so "Show original" is a
     * swap back rather than a re-render - and so the state lives here, never
     * in the DOM.
     */
    static #originalBodies = new WeakMap();

    /**
     * One line of translated text, with its links, #tags and @mentions live
     * again. A translation arrives as plain text, so what the body renderer
     * makes clickable has to be found in it a second time - by the same
     * tokenizer, so a tag reads the same translated as it did before.
     */
    static #appendLinkified(paragraph, line) {
        for (const segment of Linkifier.tokenize(line)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                paragraph.appendChild(DeltaRenderer.linkedNode(segment.href, inner));
            } else if (segment.type === 'hashtag') {
                paragraph.appendChild(DeltaRenderer.hashtagNode(segment.tag, inner));
            } else if (segment.type === 'mention') {
                paragraph.appendChild(DeltaRenderer.mentionNode(segment.username, inner));
            } else {
                paragraph.appendChild(inner);
            }
        }
    }

    static async #translate(button) {
        const post = button.closest('.Post');
        const body = post.querySelector('.PostBody');
        if (!body) return;

        // Already translated: swap the original back in place.
        if (Post.#originalBodies.has(post)) {
            body.replaceWith(Post.#originalBodies.get(post));
            Post.#originalBodies.delete(post);
            ToggleButton.select(button, Post.GLYPHS.translate);
            button.classList.remove('Removing');
            Post.nameIt(button, 'Translate');
            return;
        }

        Working.start(button);

        try {
            // The reader's own interface language today; the parameter is
            // what lets a translated interface ask for its language later.
            const result = await Api.post('/api/translate-post', {
                postId: Number(post.dataset.postId),
                language: navigator.language || 'en',
            });

            if (!result) return;

            const translated = document.createElement('div');
            translated.className = 'PostBody MachineTranslation';

            const newline = String.fromCharCode(10);

            for (const paragraph_text of String(result.body).split(/\n{2,}/)) {
                if (paragraph_text.trim() === '') continue;
                const paragraph = document.createElement('p');

                // A blank line started a new paragraph above; a single newline
                // is a break the author made inside one, and setting the whole
                // paragraph as text would collapse it into a space.
                paragraph_text.trim().split(newline).forEach((line, index) => {
                    if (index > 0) {
                        paragraph.appendChild(document.createElement('br'));
                    }

                    Post.#appendLinkified(paragraph, line);
                });

                translated.appendWithSpace(paragraph);
            }

            const label = document.createElement('p');
        label.className = 'MachineTranslationLabel';
            label.textContent = Strings.for('PostClient').machineTranslation || '';
            translated.appendWithSpace(label);

            Post.#originalBodies.set(post, body);
            body.replaceWith(translated);
            ToggleButton.select(button, Post.GLYPHS.showOriginal);
            // Red, like every other button whose next press undoes what the
            // last one did - the glyph alone cannot say the body is no longer
            // what its author wrote.
            button.classList.add('Removing');
            Post.nameIt(button, 'Show original');
        } finally {
            Working.stop(button);
        }
    }

    static async #like(button) {
        const postData = button.closest('.Post').dataset;
        Working.start(button);
        try {
            const result = await Api.post('/api/like', { itemId: postData.postId });
            if (!result) return;
            button.dataset.liked = result.liked ? '1' : '0';
            button.classList.toggle('Removing', result.liked);
            button.setAttribute('aria-pressed', result.liked ? 'true' : 'false');
            Post.nameIt(button, Post.likeName(result.liked));
            button.textContent = Post.likeLabel(result.liked, result.count);
        } finally {
            Working.stop(button);
        }
    }

    static async #bookmark(button) {
        const postData = button.closest('.Post').dataset;
        Working.start(button);
        try {
            const result = await Api.post('/api/bookmark', { itemId: postData.postId });
            if (!result) return;
            button.dataset.bookmarked = result.bookmarked ? '1' : '0';
            button.classList.toggle('Removing', result.bookmarked);
            button.setAttribute('aria-pressed', result.bookmarked ? 'true' : 'false');
            Post.nameIt(button, Post.bookmarkLabel(result.bookmarked));
        } finally {
            Working.stop(button);
        }
    }

    static async #delete(button) {
        if (!await Dialog.confirm(Strings.for('PostClient').deleteConfirm || '')) return;
        const postData = button.closest('.Post').dataset;
        Working.start(button);
        try {
            const result = await Api.post('/api/delete', { itemId: postData.postId });
            if (!result) return;
            if (button.dataset.standalone === '1') {
                window.location.href = ClientConfig.siteURL() + '/';
            } else {
                DOMUtils.slideOut(button.closest('.Post'));
            }
        } finally {
            Working.stop(button);
        }
    }

}

ReadyHandler.add(Post.init);

    return { Post };
})();
export const Post = PostModule.Post;

// MessageCrypto.js
const MessageCryptoModule = (() => {
/**
 * The cryptography behind end-to-end encrypted messages, all WebCrypto.
 *
 * Each member holds a P-256 ECDH keypair (generated here, in the browser).
 * The private key is wrapped under a passphrase-derived key (PBKDF2) and the
 * wrapped blob is stored server-side, which is what lets the same passphrase
 * unlock the history from any browser - the server only ever sees ciphertext.
 *
 * A conversation's key is derived by ECDH against the other person's public
 * key, so both sides compute the same one. Each message is encrypted under
 * its own random key, which travels wrapped under the conversation key inside
 * the envelope (see MessageEnvelope.php) - the shape that makes reporting
 * work: revealing one message's key opens that message and nothing else.
 */

const CURVE = { name: 'ECDH', namedCurve: 'P-256' };
// What it costs to turn a passphrase into the key that unwraps the private
// one, and so what it costs to guess a passphrase against a stolen blob. A
// wrapped key records the count it was made with, so raising this only affects
// keys wrapped from now on and never locks an older one out.
const PBKDF2_ITERATIONS = 1000000;

function to_base64(bytes) {
    return btoa(String.fromCharCode(...new Uint8Array(bytes)));
}

function from_base64(text) {
    return Uint8Array.from(atob(text), (character) => character.charCodeAt(0));
}

class MessageCrypto {
    /** The open conversation's derived AES key, set by MessageUnlockForm.js. */
    static #threadKey = null;

    /** messageId -> envelope JSON, so a report can reveal that message's key. */
    static #envelopes = new Map();

    static async generateKeypair() {
        const pair = await crypto.subtle.generateKey(CURVE, true, ['deriveBits']);

        return {
            publicKey: await crypto.subtle.exportKey('jwk', pair.publicKey),
            privateKey: await crypto.subtle.exportKey('jwk', pair.privateKey),
        };
    }

    static async wrapPrivateKey(private_jwk, passphrase) {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await MessageCrypto.#passphraseKey(passphrase, salt, PBKDF2_ITERATIONS, 'encrypt');

        const ciphertext = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            new TextEncoder().encode(JSON.stringify(private_jwk))
        );

        return {
            salt: to_base64(salt),
            iterations: PBKDF2_ITERATIONS,
            iv: to_base64(iv),
            ciphertext: to_base64(ciphertext),
        };
    }

    /**
     * Null on a wrong passphrase - GCM authenticates as it decrypts, so a bad
     * unwrap is detected rather than yielding garbage.
     */
    static async unwrapPrivateKey(wrapped, passphrase) {
        try {
            const key = await MessageCrypto.#passphraseKey(passphrase, from_base64(wrapped.salt), wrapped.iterations, 'decrypt');

            const plaintext = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: from_base64(wrapped.iv) },
                key,
                from_base64(wrapped.ciphertext)
            );

            return JSON.parse(new TextDecoder().decode(plaintext));
        } catch {
            return null;
        }
    }

    static async conversationKey(private_jwk, other_public_jwk) {
        const private_key = await crypto.subtle.importKey('jwk', private_jwk, CURVE, false, ['deriveBits']);
        const public_key = await crypto.subtle.importKey('jwk', other_public_jwk, CURVE, false, []);

        const shared_secret = await crypto.subtle.deriveBits({ name: 'ECDH', public: public_key }, private_key, 256);
        const hkdf_key = await crypto.subtle.importKey('raw', shared_secret, 'HKDF', false, ['deriveKey']);

        return crypto.subtle.deriveKey(
            { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: new TextEncoder().encode('glommer-message-v1') },
            hkdf_key,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
    }

    /**
     * A short code standing for the pair of public keys this conversation is
     * encrypted with. Both sides compute the same one - the keys are sorted
     * before hashing, so it does not matter who is reading - and two people
     * who read it to each other over any other channel can tell whether they
     * are really talking to each other.
     *
     * It is the answer to the one thing encryption here cannot check by
     * itself: the server is what tells each browser the other's key, so a
     * server that handed out a key of its own would sit in the middle
     * undetected. It cannot make both codes agree, because it does not get to
     * choose either real key - so the codes differ, and that is the tell.
     */
    static async fingerprint(one_jwk, other_jwk) {
        const material = [one_jwk, other_jwk]
            .map((jwk) => jwk.x + '.' + jwk.y)
            .sort()
            .join('|');

        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(material));
        const hex = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');

        return (hex.slice(0, 20).toUpperCase().match(/.{4}/g) ?? []).join(' ');
    }

    /** Builds the envelope JSON api/send-message.php takes. */
    static async encrypt(conversation_key, text) {
        const message_key_bytes = crypto.getRandomValues(new Uint8Array(32));
        const message_key = await crypto.subtle.importKey('raw', message_key_bytes, 'AES-GCM', false, ['encrypt']);

        const iv = crypto.getRandomValues(new Uint8Array(12));
        const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, message_key, new TextEncoder().encode(text));

        const key_iv = crypto.getRandomValues(new Uint8Array(12));
        const wrapped_key = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: key_iv }, conversation_key, message_key_bytes);

        return JSON.stringify({
            v: 1,
            iv: to_base64(iv),
            ct: to_base64(ciphertext),
            kiv: to_base64(key_iv),
            wk: to_base64(wrapped_key),
        });
    }

    /** Null when the envelope doesn't open under this conversation key. */
    static async decrypt(conversation_key, envelope) {
        try {
            const fields = JSON.parse(envelope);
            const message_key = await crypto.subtle.importKey('raw', await MessageCrypto.#messageKeyBytes(conversation_key, fields), 'AES-GCM', false, ['decrypt']);

            const plaintext = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: from_base64(fields.iv) },
                message_key,
                from_base64(fields.ct)
            );

            return new TextDecoder().decode(plaintext);
        } catch {
            return null;
        }
    }

    // --- The open thread's state, shared between the unlock form, the
    // composer, live messages, and the report flow ---

    static setThreadKey(key) {
        MessageCrypto.#threadKey = key;
    }

    static threadKey() {
        return MessageCrypto.#threadKey;
    }

    static rememberEnvelope(message_id, envelope) {
        MessageCrypto.#envelopes.set(Number(message_id), envelope);
    }

    /**
     * The revealed per-message key a report of an encrypted message carries
     * (base64), or null when this message isn't a decryptable encrypted one.
     */
    static async revealKeyForMessage(message_id) {
        const envelope = MessageCrypto.#envelopes.get(Number(message_id));

        if (envelope === undefined || MessageCrypto.#threadKey === null) {
            return null;
        }

        try {
            return to_base64(await MessageCrypto.#messageKeyBytes(MessageCrypto.#threadKey, JSON.parse(envelope)));
        } catch {
            return null;
        }
    }

    // --- The tab's unlocked private key. sessionStorage on purpose: it is
    // what makes every page load in the tab not re-ask the passphrase, it
    // dies with the tab, and a script that could read it could equally read
    // the decrypted messages out of the DOM - it guards against the server
    // and the database, not against the page itself. ---

    static storeUnlocked(private_jwk) {
        sessionStorage.setItem('messagePrivateKey', JSON.stringify(private_jwk));
    }

    static loadUnlocked() {
        const stored = sessionStorage.getItem('messagePrivateKey');

        return stored === null ? null : JSON.parse(stored);
    }

    static clearUnlocked() {
        sessionStorage.removeItem('messagePrivateKey');
    }

    static async #passphraseKey(passphrase, salt, iterations, usage) {
        const passphrase_key = await crypto.subtle.importKey('raw', new TextEncoder().encode(passphrase), 'PBKDF2', false, ['deriveKey']);

        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
            passphrase_key,
            { name: 'AES-GCM', length: 256 },
            false,
            [usage]
        );
    }

    static async #messageKeyBytes(conversation_key, fields) {
        return crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: from_base64(fields.kiv) },
            conversation_key,
            from_base64(fields.wk)
        );
    }
}

    return { MessageCrypto };
})();
export const MessageCrypto = MessageCryptoModule.MessageCrypto;

// Message.js
const MessageModule = (() => {
class Message {
    messageId = null;
    senderId = null;
    recipientId = null;
    body = null;
    bodyCiphertext = null;
    createdAt = null;
    sender = null;
    element = null;

    static fromData(data) {
        const message = new Message();
        Object.assign(message, data);
        return message;
    }

    toElement() {
        const div = document.createElement('article');
        div.className = 'Message';

        if (Number(this.senderId) === Number(ClientConfig.get('currentUserId'))) {
            div.className += ' Own';
        }

        const byline = document.createElement('div');
        byline.className = 'MessageByline';

        if (this.sender) {
            byline.appendWithSpace(this.senderHeader(this.sender, this.senderId));
        }

        const meta = new RelativeTime(this.createdAt).toDOM();
        byline.appendWithSpace(meta);

        div.appendWithSpace(byline);

        const line = document.createElement('div');
        line.className = 'MessageLine';

        const body = document.createElement('pre');
        body.className = 'MessageBody';

        if (this.bodyCiphertext !== null) {
            div.className += ' Encrypted Locked';
            div.dataset.cipherEnvelope = this.bodyCiphertext;
            div.dataset.messageId = this.messageId;
            body.textContent = Strings.for('Message', { encrypted: 'Encrypted message' }).encrypted;
        } else {
            body.textContent = expand(this.body);
        }

        line.appendWithSpace(body);

        if (ClientConfig.get('currentUserId') !== null
            && Number(this.senderId) !== Number(ClientConfig.get('currentUserId'))
            && Number(this.senderId) !== 1) {
            const report_button = document.createElement('button');
            report_button.type = 'button';
            report_button.className = 'Button ReportButton';
            report_button.dataset.targetType = 'message';
            report_button.dataset.targetId = this.messageId;
            report_button.textContent = Strings.for('MiscellaneousClient').report || '';
            line.appendWithSpace(report_button);
        }

        div.appendWithSpace(line);

        this.element = div;

        // The written line only - the byline and the timestamp beside it are
        // not somebody's writing.
        this.element.querySelectorAll(EmojiRenderer.CONTENT).forEach(content => EmojiRenderer.render(content));

        const messageBody = div.querySelector('.MessageBody');
        if (messageBody && EmojiRenderer.isEmojiOnly(messageBody)) {
            div.classList.add('emoji-only');
        }

        if (this.bodyCiphertext !== null) {
            Message.decryptInto(div);
        }

        return div;
    }

    senderHeader(sender, sender_id) {
        return User.fromData({ userId: sender_id, ...sender }).header();
    }

    /**
     * Opens one rendered message's envelope in place, once the thread key is
     * available (MessageUnlockForm.js). Registering the envelope first is what
     * lets a report of this message reveal its key later - see ReportButton.js.
     */
    static async decryptInto(article) {
        const envelope = article.dataset.cipherEnvelope;
        if (!envelope) return;

        MessageCrypto.rememberEnvelope(article.dataset.messageId, envelope);

        const thread_key = MessageCrypto.threadKey();
        if (thread_key === null) return;

        const body = article.querySelector('.MessageBody');
        const text = await MessageCrypto.decrypt(thread_key, envelope);

        if (text === null) {
            // An envelope the current keys don't open - sent under keys that
            // have since been reset. Honest and final; nothing can read it now.
            body.textContent = Strings.for('Message').decryptionFailed || '';
            return;
        }

        body.textContent = expand(text);
        article.classList.remove('Locked');

        EmojiRenderer.render(body);
        if (EmojiRenderer.isEmojiOnly(body)) {
            article.classList.add('emoji-only');
        }
        render_math(article);
    }

    /**
     * Scroll the message list to the bottom on page load.
     */
    static init() {
        if (document.querySelector('.MessageComposer')) {
            window.addEventListener('load', () => {
                window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
                const composerTextarea = document.querySelector('.MessageComposer textarea');
                if (composerTextarea) composerTextarea.focus();
            });
        }
    }
}

document.addEventListener('ws:message', (event) => {
    const data = event.detail;

    // Who this thread is with, read off the composer: a thread nobody has
    // written in yet has no list to ask.
    const recipient = document.querySelector('.MessageComposer [name="recipientId"]');

    if (!recipient || Number(recipient.value) !== Number(data.senderId)) {
        return;
    }

    const was_near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;
    const list = list_in(document.querySelector('main'), 'MessageList');

    if (!list) return;

    const message = Message.fromData(data);
    const element = message.toElement();
    RelativeTime.refresh(element);
    list.appendWithSpace(list_item(element));
    render_math(element);

    if (was_near_bottom) {
        window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
    }
});

ReadyHandler.add(Message.init);

    return { Message };
})();
export const Message = MessageModule.Message;

// Notification.js
const NotificationModule = (() => {
class Notification {
    notificationId = null;
    userId = null;
    type = null;
    postId = null;
    createdAt = null;
    actor = null;
    element = null;

    static fromData(data) {
        const notification = new Notification();
        Object.assign(notification, data);
        return notification;
    }

    /**
     * Mirrors Notification::nameFor - the name and always the username too,
     * since a display name is neither unique nor the account's own. The
     * username is written with its @ wherever it is shown.
     */
    actorName() {
        const slug = this.actor.slug || '';
        const handle = '@' + slug;
        const name = this.actor.title || '';

        if (name === '' || name === slug) {
            return handle;
        }

        return name + ' (' + handle + ')';
    }

    /** Mirrors Notification::textFor() - see MessagingExtras.php for the words. */
    text() {
        const words = Strings.for('Notification');

        const phrase = words[this.type] ?? words.default;

        return phrase.replace('{name}', this.actorName());
    }

    targetURL() {
        switch (this.type) {
            case 'like':
            case 'repost':
            case 'reply':
            case 'postReady':
            case 'scheduledPostLive':
            case 'uploadPartlyFailed':
                return ClientConfig.siteURL() + '/users/' + ClientConfig.get('currentUserUsername') + '/' + this.postId;
            case 'friendRequest':
                return ClientConfig.siteURL() + '/users/' + ClientConfig.get('currentUserUsername') + '/friends';
            case 'friendAccepted':
            case 'follow':
                return ClientConfig.siteURL() + '/users/' + this.actor.slug + '/';
            case 'message':
                return ClientConfig.siteURL() + '/messages/' + this.actor.slug;
            case 'mention':
                return ClientConfig.siteURL() + '/users/' + this.actor.slug + '/' + this.postId;
            case 'passwordRemovedGoogle':
                return ClientConfig.siteURL() + '/forgot-password';
            default:
                return null;
        }
    }

    toElement() {
        const div = document.createElement('article');
        div.className = 'Notification';
        div.dataset.notificationId = this.notificationId;

        const target = this.targetURL();
        const container = document.createElement(target === null ? 'div' : 'a');
        container.className = target === null ? 'NotificationContainer' : 'NotificationLink';
        if (target !== null) {
            container.href = target;
        }

        container.appendWithSpace(Avatar.forUser(this.actor).toDOM());

        const info = document.createElement('div');

        const text = document.createElement('div');
        text.textContent = this.text();
        info.appendWithSpace(text);

        const meta = document.createElement('div');
        meta.className = 'NotificationMeta';
        meta.appendWithSpace(new RelativeTime(this.createdAt).toDOM());
        info.appendWithSpace(meta);

        container.appendWithSpace(info);
        div.appendWithSpace(container);

        this.element = div;

        return div;
    }
}

    return { Notification };
})();
export const Notification = NotificationModule.Notification;

// Report.js
const ReportModule = (() => {
/**
 * Client-side mirror of the PHP Report (src/classes/Report.php) - the
 * moderation card the admin reports page appends on scroll from the data
 * api/report-history.php returns. Left column: who reported what, the reported
 * content itself (a bare post, a message body, a user's profile card, or a
 * "no longer exists" notice), the reason, and when. Right column: the same ban
 * / delete / dismiss buttons the server renders, whose delegated handlers live
 * in main.js.
 */
class Report {
    reportId = null;
    reporterId = null;
    reporterUsername = null;
    targetType = null;
    targetId = null;
    reason = null;
    createdAt = null;
    targetUserId = null;
    targetUsername = null;
    targetLive = false;
    target = null;
    element = null;

    static fromData(data) {
        const card = new Report();
        Object.assign(card, data);
        return card;
    }

    /** Mirrors Report::targetContentElement - the reported item itself. */
    targetContentElement(words) {
        const target = this.target || { kind: 'missing' };

        if (target.kind === 'post' && target.post) {
            const post = Post.fromData(target.post).postElement();

            // A deleted post's reported media, streamed from the kept originals
            // via the mod-only passthrough (mediaType already resolved server-side).
            if (Array.isArray(target.attachments) && target.attachments.length > 0) {
                const media = document.createElement('div');
                media.className = 'ReportedAttachments';
                target.attachments.forEach((attachment) => media.appendWithSpace(forensic_attachment_element(attachment, words)));
                post.appendWithSpace(media);
            }

            return post;
        }

        if (target.kind === 'message') {
            const quote = document.createElement('blockquote');
            quote.className = 'ReportedContent';
            quote.textContent = target.body || '';
            return quote;
        }

        if (target.kind === 'user' && target.user) {
            return User.fromData(target.user).toElement();
        }

        // missing / unknown - a muted notice (mirrors the PHP Notice element).
        // The server already resolved and localized target.message; the
        // fallback here is only for a payload that somehow carries neither.
        const notice = document.createElement('p');
        notice.className = 'Notice';
        notice.textContent = target.message ?? words.missing.unknownType;
        return notice;
    }

    toElement() {
        const words = Strings.for('Report');
        const type_label = words.targetTypes[this.targetType] || capitalize(this.targetType);

        const card = document.createElement('article');
        card.className = 'Report';

        const details = document.createElement('div');
        details.className = 'ReportDetails';

        const summary = document.createElement('div');
        summary.appendWithSpace(document.createTextNode(
            words.summary.before.replace('{type}', type_label).replace('{id}', this.targetId)
        ));

        const reporter_link = document.createElement('a');
        reporter_link.href = ClientConfig.siteURL() + '/users/' + this.reporterUsername + '/';
        reporter_link.textContent = this.reporterUsername;
        summary.appendWithSpace(reporter_link);
        summary.appendWithSpace(document.createTextNode(words.summary.after));
        details.appendWithSpace(summary);

        details.appendWithSpace(this.targetContentElement(words));

        if (this.reason !== null && this.reason !== undefined) {
            const reason_line = document.createElement('p');
            reason_line.textContent = words.reasonLine.replace('{reason}', this.reason);
            details.appendWithSpace(reason_line);
        }

        if (this.createdAt) {
            const meta = new RelativeTime(this.createdAt).toDOM();
            details.appendWithSpace(meta);
        }

        card.appendWithSpace(details);

        const actions = document.createElement('div');
        actions.className = 'ReportActions';

        // The admin (userId 1) can't be banned, so no Ban Reporter when the
        // admin filed the report. (The reported user is never the admin - the
        // report API rejects reports about admin content.)
        if (Number(this.reporterId) !== 1) {
            actions.appendWithSpace(this.banButton(this.reporterId, words.banReporterLabel));
        }

        if (this.targetUserId !== null && this.targetUserId !== undefined
            && this.targetUsername !== null && this.targetUsername !== undefined
            && Number(this.targetUserId) !== Number(this.reporterId)) {
            actions.appendWithSpace(this.banButton(this.targetUserId, words.banReportedUserLabel));
        }

        // Only offer Delete when the live post/message still exists (a snapshot
        // of already-deleted content still shows, but has nothing to delete).
        if (this.targetLive && (this.targetType === 'post' || this.targetType === 'message')) {
            const delete_button = document.createElement('button');
            delete_button.type = 'button';
            delete_button.className = 'Button ReportedContentDeleteButton';
            delete_button.dataset.reportId = this.reportId;
            delete_button.textContent = words.deleteLabel.replace('{type}', type_label);
            actions.appendWithSpace(delete_button);
        }

        // Only a post has media to put behind a cover.
        if (this.targetLive && this.targetType === 'post') {
            const classify_button = document.createElement('button');
            classify_button.type = 'button';
            classify_button.className = 'Button ReportedContentClassifyButton';
            classify_button.dataset.reportId = this.reportId;
            classify_button.textContent = Strings.for('ReportedContentClassifyButton', { name: 'Mark Sensitive' }).name;
            actions.appendWithSpace(classify_button);
        }

        const dismiss_button = document.createElement('button');
        dismiss_button.type = 'button';
        dismiss_button.className = 'Button ReportDismissButton';
        dismiss_button.dataset.reportId = this.reportId;
        dismiss_button.textContent = Strings.for('ReportDismissButton', { name: 'Dismiss' }).name;
        actions.appendWithSpace(dismiss_button);

        card.appendWithSpace(actions);

        this.element = card;

        return card;
    }

    banButton(user_id, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button UserBanButton';
        button.dataset.userId = user_id;
        button.textContent = label;

        return button;
    }

    // ----------------------------------------------------------------
    // Static action handlers (dismiss, delete reported content)
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', async (event) => {
            const dismissBtn = event.target.closest('.ReportDismissButton');
            if (dismissBtn) {
                Report.#dismiss(dismissBtn);
                return;
            }

            const deleteBtn = event.target.closest('.ReportedContentDeleteButton');
            if (deleteBtn) {
                Report.#deleteContent(deleteBtn);
                return;
            }

            const classifyBtn = event.target.closest('.ReportedContentClassifyButton');
            if (classifyBtn) {
                Report.#classifyContent(classifyBtn);
            }
        });
    }

    static async #classifyContent(button) {
        Working.start(button);
        try {
            const result = await Api.post('/api/classify-reported-content', { reportId: button.dataset.reportId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Report'));
        } finally {
            Working.stop(button);
        }
    }

    static async #dismiss(button) {
        Working.start(button);
        try {
            const result = await Api.post('/api/dismiss-report', { reportId: button.dataset.reportId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Report'));
        } finally {
            Working.stop(button);
        }
    }

    static async #deleteContent(button) {
        if (!await Dialog.confirm(Strings.for('MiscellaneousClient').deleteReportedContent || '')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/delete-reported-content', { reportId: button.dataset.reportId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Report'));
        } finally {
            Working.stop(button);
        }
    }
}

/** Uppercases the first character - the JS side of PHP's ucfirst(). */
function capitalize(text) {
    const value = text || '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

/**
 * One reported attachment of a deleted post (mirrors Report::forensicAttachmentElement):
 * an img/video/audio pointed at the mod-only passthrough, a notice when the
 * original is gone, or a link for any other type. mediaType is resolved server-side.
 */
function forensic_attachment_element(attachment, words) {
    if (attachment.mediaType === 'image') {
        const image = document.createElement('img');
        image.className = 'ReportedMedia';
        image.src = attachment.url;
        image.alt = words.reportedImageAlt;
        return image;
    }

    if (attachment.mediaType === 'video') {
        const video = document.createElement('video');
        video.className = 'ReportedMedia';
        video.controls = true;
        video.src = attachment.url;
        return video;
    }

    if (attachment.mediaType === 'audio') {
        const audio = document.createElement('audio');
        audio.controls = true;
        audio.src = attachment.url;
        return audio;
    }

    if (attachment.mediaType === null || attachment.mediaType === undefined) {
        const notice = document.createElement('p');
        notice.className = 'Notice';
        notice.textContent = words.attachmentUnavailable;
        return notice;
    }

    const link = document.createElement('a');
    link.href = attachment.url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = words.viewAttachment;
    return link;
}

ReadyHandler.add(Report.init);

    return { Report };
})();
export const Report = ReportModule.Report;
