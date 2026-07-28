import { ClientConfig } from '/ClientConfig.js';
import { csrf_headers, list_item } from '/utils.js';
import { render_math } from '/math.js';
import { Post } from '/Post.js';
import { Message } from '/Message.js';
import { OtherUser } from '/OtherUser.js';
import { FriendRequest } from '/OtherUser.js';
import { Notification } from '/Notification.js';
import { ReportCard } from '/ReportCard.js';
import { ReadyHandler } from '/ReadyHandler.js';

const REGISTRY = {};

export class InfiniteScroller {
    static register(type, renderItem, countOffset, buildReq = null) {
        REGISTRY[type] = { renderItem, countOffset, buildReq };
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
    #active = true;
    static #THRESHOLD = 150;
    #onScroll;

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

            const extraFields = { ...config };
            delete extraFields.endpoint;
            delete extraFields.itemType;
            delete extraFields.direction;

            buildReq = entry.buildReq
                ? offset => {
                    const custom = entry.buildReq(list, offset);
                    return { ...extraFields, ...custom, offset };
                  }
                : offset => ({ ...extraFields, offset });

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

        this.#onScroll = () => this.#handleScroll();
        window.addEventListener('scroll', this.#onScroll, { passive: true });
    }

    setActive(active) {
        this.#active = active;
    }

    destroy() {
        this.#active = false;
        if (this.#onScroll) {
            window.removeEventListener('scroll', this.#onScroll);
            this.#onScroll = null;
        }
    }

    #nearEdge() {
        if (this._direction === 'up') return window.scrollY <= InfiniteScroller.#THRESHOLD;
        return window.innerHeight + window.scrollY >= document.body.scrollHeight - InfiniteScroller.#THRESHOLD;
    }

    async #handleScroll() {
        if (this.#loading) return;
        if (!this.#list || !this.#active) return;
        if (!this.#nearEdge()) return;

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
            const response = await fetch(ClientConfig.siteURL() + url, {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(this._buildReq(offset)),
            });
            if (!response.ok) return;

            const data = await response.json();
            const { hasMore, items } = this.#extractItems(data);

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
        } catch (e) {
            console.error('InfiniteScroller error:', e);
        } finally {
            spinner.remove();
            this.#loading = false;
        }
    }

    #extractItems(data) {
        const resp = data.response || data;
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
    list => list.querySelectorAll('.Post').length,
    (list, offset) => {
        const feedType = list.dataset.feedType;
        if (feedType) {
            const req = { feedType, offset };
            if (feedType === 'user') req.userId = list.dataset.userId;
            else if (feedType === 'tag') req.tag = list.dataset.tag;
            return req;
        }
        return { offset };
    }
);

InfiniteScroller.register('Message',
    data => Message.fromData(data).toElement(),
    list => list.querySelectorAll('.Message').length
);

InfiniteScroller.register('OtherUser',
    data => OtherUser.fromData(data).toElement(),
    list => list.querySelectorAll('.OtherUser').length
);

InfiniteScroller.register('FriendRequest',
    data => FriendRequest.fromData(data).toElement(),
    list => list.querySelectorAll('.OtherUser').length
);

InfiniteScroller.register('Notification',
    data => Notification.fromData(data).toElement(),
    list => list.querySelectorAll('.Notification').length
);

InfiniteScroller.register('ReportCard',
    data => ReportCard.fromData(data).toElement(),
    list => list.querySelectorAll('.ReportCard').length
);

ReadyHandler.add(InfiniteScroller.init);

