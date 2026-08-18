import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { list_item } from '/scripts/utils.js';
import { render_math } from '/scripts/MathRenderer.js';
import { Post } from '/scripts/Post.js';
import { Message } from '/scripts/Message.js';
import { OtherUser } from '/scripts/OtherUser.js';
import { ReceivedFriendRequest } from '/scripts/ReceivedFriendRequest.js';
import { Notification } from '/scripts/Notification.js';
import { Report } from '/scripts/Report.js';
import { Entity } from '/scripts/Entity.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

const REGISTRY = {};

export class InfiniteScroller {
    static register(type, renderItem, countOffset) {
        REGISTRY[type] = { renderItem, countOffset };
    }

    static init() {
        document.querySelectorAll('[data-infinite-scroll]').forEach(el => {
            new InfiniteScroller(el);
        });
    }

    static create(list, overrides) {
        return new InfiniteScroller(list, overrides);
    }

    #list;
    #loading = false;

    /** The live region that says what arrived, made on first use. */
    #announcer = null;

    /** The last payload, so the announcement can name what it held. */
    #lastResponse = null;
    #active = true;
    static #THRESHOLD = 150;

    /**
     * How long the view has to be still before its position is acted on. Short
     * enough that flicking to the end of a feed still loads the moment it
     * lands, long enough that the whole of a smooth scroll counts as one
     * arrival rather than a hundred.
     */
    static #SETTLE_MS = 120;

    #scrollTimer;
    #onScroll;
    #onResize;

    constructor(list, overrides) {
        this.#list = list;

        let endpoint, direction, buildReq, renderItem, countOffset, wrapper;
        let resolveEndpoint = null;

        if (overrides) {
            if (typeof overrides.endpoint === 'function') {
                resolveEndpoint = overrides.endpoint;
                endpoint = resolveEndpoint();
            } else {
                endpoint = overrides.endpoint;
            }
            direction   = overrides.direction ?? 'down';
            renderItem  = overrides.renderItem;
            countOffset = overrides.countOffset;
            buildReq    = overrides.buildRequest || (offset => ({ offset }));
            wrapper     = overrides.wrapper ?? list_item;

            if (overrides.active === false) {
                this.#active = false;
            }
        } else {
            const config = JSON.parse(list.dataset.infiniteScroll);
            const type = config.itemType;
            const entry = REGISTRY[type];
            if (!entry) throw new Error(`InfiniteScroller: unknown item type "${type}"`);

            endpoint  = config.endpoint;
            direction = config.direction ?? 'down';

            // Everything the list's own config names is part of the request,
            // so a feed selected by more than its type (one profile's posts,
            // one tag's) pages correctly with no per-type special casing here.
            const extraFields = { ...config };
            delete extraFields.endpoint;
            delete extraFields.itemType;
            delete extraFields.direction;

            buildReq = offset => ({ ...extraFields, offset });

            renderItem  = entry.renderItem;
            countOffset = entry.countOffset;
            wrapper     = list_item;
        }

        this._endpoint        = endpoint;
        this._resolveEndpoint = resolveEndpoint;
        this._direction       = direction;
        this._buildReq        = buildReq;
        this._renderItem      = renderItem;
        this._countOffset     = countOffset;
        this._wrapper         = wrapper;

        // Debounced, because a smooth scroll is not one scroll event but a
        // stream of them all the way down. Reacting to each would load a page
        // of messages for every frame of the journey; waiting until the view
        // has come to rest asks once, about where it actually stopped.
        this.#onScroll = () => {
            clearTimeout(this.#scrollTimer);
            this.#scrollTimer = setTimeout(() => this.#handleScroll(), InfiniteScroller.#SETTLE_MS);
        };

        this.#onResize = () => this.#fill();

        window.addEventListener('scroll', this.#onScroll, { passive: true });
        window.addEventListener('resize', this.#onResize, { passive: true });

        this.#fill();
    }

    setActive(active) {
        this.#active = active;

        if (active) this.#fill();
    }

    destroy() {
        this.#active = false;
        clearTimeout(this.#scrollTimer);

        if (this.#onScroll) {
            window.removeEventListener('scroll', this.#onScroll);
            this.#onScroll = null;
        }

        if (this.#onResize) {
            window.removeEventListener('resize', this.#onResize);
            this.#onResize = null;
        }
    }

    #nearEdge() {
        if (this._direction === 'up') return window.scrollY <= InfiniteScroller.#THRESHOLD;
        return window.innerHeight + window.scrollY >= document.body.scrollHeight - InfiniteScroller.#THRESHOLD;
    }

    #scrollable() {
        return document.body.scrollHeight > window.innerHeight;
    }

    /**
     * Keeps asking for pages until the page is long enough to scroll, because
     * a page that fits the window never fires a scroll event: the reader is
     * left with one page and no way to ask for the next.
     *
     * The stop is a page that made the document no taller, not a try count. A
     * page that added no height will not add any next time, so nothing spins;
     * a count would still spend its whole budget on every short list.
     */
    async #fill() {
        while (this.#active && !this.#scrollable()) {
            const height = document.body.scrollHeight;

            await this.#load();

            if (document.body.scrollHeight <= height) return;
        }
    }

    async #handleScroll() {
        if (this.#loading) return;
        if (!this.#list || !this.#active) return;
        if (!this.#nearEdge()) return;

        await this.#load();
    }

    async #load() {
        if (this.#loading) return;
        if (!this.#list || !this.#active) return;

        this.#loading = true;

        const spinner = document.createElement('li');
        spinner.className = 'LoadingSpinner';
        spinner.setAttribute('aria-label', 'Loading');

        if (this._direction === 'up') {
            this.#list.insertBeforeWithSpace(spinner, this.#list.firstChild);
        } else {
            this.#list.appendWithSpace(spinner);
        }

        try {
            const url = this._resolveEndpoint ? this._resolveEndpoint() : this._endpoint;
            const offset = this._countOffset(this.#list);

            // request() rather than post(), because what to do next turns on
            // the status: it says nothing itself, and the wording below is the
            // scroller's own.
            const result = await Api.request(url, this._buildReq(offset));

            // A refused page won't start working on the next scroll event, so
            // stop asking and say so - silently returning leaves the reader
            // looking at what appears to be the end of the feed while every
            // further scroll re-sends the same doomed request. Being throttled
            // is the exception: that one really is worth retrying.
            if (!result.ok) {
                if (result.status !== 429) {
                    this.#active = false;
                    Toast.show('Could not load more. Please reload the page.');
                }

                return;
            }

            const { hasMore, items } = this.#extractItems(result.data);

            if (!hasMore) {
                this.#active = false;
            }

            if (items?.length) {
                if (this._direction === 'up') {
                    const prevH = document.body.scrollHeight;
                    const prevY = window.scrollY;

                    for (const item of items) {
                        const el = this._renderItem(item);
                        this.#list.insertBeforeWithSpace(this._wrapper(el), spinner);
                        render_math(el);
                    }

                    const newH = document.body.scrollHeight;
                    window.scrollTo({ top: prevY + (newH - prevH), behavior: 'instant' });
                } else {
                    for (const item of items) {
                        const el = this._renderItem(item);
                        this.#list.insertBeforeWithSpace(this._wrapper(el), spinner);
                        render_math(el);
                    }
                }
            }
            if (items?.length) {
                this.#announce(items.length + ' more ' + this.#noun(items.length) + ' loaded.');
            } else if (!hasMore) {
                this.#announce('You have reached the end.');
            }
        } catch (e) {
            console.error('InfiniteScroller error:', e);
        } finally {
            spinner.remove();
            this.#loading = false;
        }
    }

    /** What this list is a list of, for the announcement. */
    #noun(count) {
        const resp = this.#lastResponse || {};

        for (const [key, word] of [
            ['posts', 'post'], ['messages', 'message'], ['notifications', 'notification'],
            ['reports', 'report'], ['users', 'person'],
        ]) {
            if (Array.isArray(resp[key])) {
                return count === 1 ? word : (word === 'person' ? 'people' : word + 's');
            }
        }

        return count === 1 ? 'item' : 'items';
    }

    /**
     * Says what just arrived, for somebody who cannot see it arrive.
     *
     * A feed that grows as you scroll is silent: more posts appear below and
     * nothing tells a screen reader they are there, so the reader has no
     * reason to go looking. Polite, because this is never urgent - it waits
     * for whatever is being read to finish.
     */
    #announce(text) {
        if (!this.#announcer) {
            this.#announcer = document.createElement('div');
            this.#announcer.className = 'visually-hidden';
            this.#announcer.setAttribute('role', 'status');
            this.#announcer.setAttribute('aria-live', 'polite');
            this.#list.parentNode?.insertBefore(this.#announcer, this.#list.nextSibling);
        }

        this.#announcer.textContent = text;
    }

    #extractItems(data) {
        const resp = data.response || data;
        this.#lastResponse = resp;
        const items = resp.items || resp.posts || resp.messages ||
                      resp.notifications || resp.reports || resp.users || [];
        return { hasMore: resp.hasMore, items };
    }
}

// ----------------------------------------------------------------
// Centralised type registrations
// ----------------------------------------------------------------

InfiniteScroller.register('Post',
    data => Post.fromData(data).toElement(),
    list => list.querySelectorAll('.Post').length
);

InfiniteScroller.register('Message',
    data => Message.fromData(data).toElement(),
    list => list.querySelectorAll('.Message').length
);

InfiniteScroller.register('OtherUser',
    data => OtherUser.fromData(data).toElement(),
    list => list.querySelectorAll('.OtherUser').length
);

InfiniteScroller.register('ReceivedFriendRequest',
    data => ReceivedFriendRequest.fromData(data).toElement(),
    list => list.querySelectorAll('.OtherUser').length
);

InfiniteScroller.register('Notification',
    data => Notification.fromData(data).toElement(),
    list => list.querySelectorAll('.Notification').length
);

InfiniteScroller.register('Report',
    data => Report.fromData(data).toElement(),
    list => list.querySelectorAll('.Report').length
);

InfiniteScroller.register('Entity',
    data => Entity.fromData(data).toElement(),
    list => list.querySelectorAll('.Entity').length
);

ReadyHandler.add(InfiniteScroller.init);

