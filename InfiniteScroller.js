import { csrf_headers, list_item } from '/utils.js';
import { render_math } from '/math.js';

export class InfiniteScroller {
    /**
     * @param {HTMLElement|string} container
     * @param {object}              opts
     * @param {string|function}     opts.endpoint   - API path, or function returning the API path (receives offset)
     * @param {function}            opts.buildRequest
     * @param {function}            opts.countOffset
     * @param {function}            opts.renderItem
     * @param {number}              [opts.threshold=150]
     * @param {function}            [opts.wrapper]
     * @param {function}            [opts.onAfterInsert]
     * @param {string}              [opts.hasMoreAttr='hasMore']
     */
    constructor(container, opts) {
        this.list = typeof container === 'string' ? document.querySelector(container) : container;
        if (!this.list) throw new Error('InfiniteScroller: container not found');

        this._resolveEndpoint = typeof opts.endpoint === 'function'
            ? opts.endpoint
            : () => opts.endpoint;

        this.buildReq   = opts.buildRequest;
        this.countOff   = opts.countOffset;
        this.renderItem = opts.renderItem;
        this.threshold  = opts.threshold ?? 150;
        this.wrapper    = opts.wrapper ?? list_item;
        this.onAfterInsert = opts.onAfterInsert || null;
        this.hasMoreAttr   = opts.hasMoreAttr ?? 'hasMore';

        this.loading = false;
        this._onScroll = this._onScroll.bind(this);
        window.addEventListener('scroll', this._onScroll, { passive: true });
    }

    destroy() {
        window.removeEventListener('scroll', this._onScroll);
        this.list = null;
    }

    _nearBottom() {
        return window.innerHeight + window.scrollY >= document.body.scrollHeight - this.threshold;
    }

    async _onScroll() {
        if (this.loading) return;
        if (!this.list || this.list.dataset[this.hasMoreAttr] !== '1') return;
        if (!this._nearBottom()) return;

        this.loading = true;

        const spinner = document.createElement('li');
        spinner.className = 'LoadingSpinner';
        spinner.setAttribute('aria-label', 'Loading');
        this.list.appendChild(spinner);

        try {
            const offset = this.countOff(this.list);
            const endpoint = this._resolveEndpoint(offset);
            const response = await fetch(window.siteURL + endpoint, {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(this.buildReq(offset)),
            });

            if (!response.ok) return;

            const data = await response.json();
            const { hasMore, items } = this._extractItems(data);

            this.list.dataset[this.hasMoreAttr] = hasMore ? '1' : '0';

            if (items && items.length > 0) {
                for (const itemData of items) {
                    const el = this.renderItem(itemData);
                    const wrapped = this.wrapper(el);
                    this.list.insertBefore(wrapped, spinner);
                    render_math(el);
                }
                if (this.onAfterInsert) this.onAfterInsert(this.list, spinner);
            }
        } catch (e) {
            console.error('InfiniteScroller error:', e);
        } finally {
            spinner.remove();
            this.loading = false;
        }
    }

    _extractItems(data) {
        const resp = data.response || data;
        const items = resp.items || resp.posts || resp.messages || resp.notifications || resp.reports || resp.users || [];
        return { hasMore: resp.hasMore, items };
    }
}
