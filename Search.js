import { csrf_headers, list_item } from '/utils.js';
import { InfiniteScroller } from '/InfiniteScroller.js';

/**
 * A debounced search input that fetches results from an API endpoint,
 * renders them, and optionally handles infinite scrolling.
 *
 * @param {HTMLInputElement} input
 * @param {object}           options
 * @param {string|function}  options.endpoint   – API path, or a function that receives the current query
 *                                                and returns the API path (e.g. for switching endpoints)
 * @param {function}         options.buildRequest – (query: string) => object sent as JSON body
 * @param {HTMLElement}      options.resultsContainer – element where results are rendered
 * @param {function}         options.renderItem – (data: any) => HTMLElement
 * @param {number}           [options.delay=300] – debounce delay in ms
 * @param {function}         [options.onBeforeFetch] – called before fetch, receives (input, query)
 * @param {function}         [options.onResponse] – called after successful fetch, receives (input, data)
 * @param {function}         [options.extractItems] – receives the parsed JSON and returns an array of items;
 *                                                    defaults to looking for `data.response.items`, then
 *                                                    `data.response.users`, `posts`, etc.
 * @param {boolean}          [options.enableInfiniteScroll=false]
 * @param {function}         [options.countOffset] – required if enableInfiniteScroll is true
 */
export class Search {
    constructor(input, options) {
        this.input = input;
        this._resolveEndpoint = typeof options.endpoint === 'function'
            ? options.endpoint
            : () => options.endpoint;
        this.buildRequest = options.buildRequest;
        this.resultsContainer = options.resultsContainer;
        this.renderItem = options.renderItem;
        this.delay = options.delay ?? 300;
        this.onBeforeFetch = options.onBeforeFetch || null;
        this._originalOnResponse = options.onResponse || null;
        this._extractItems = options.extractItems || defaultExtractItems;

        this.abortController = null;
        this.debounceId = null;

        this._handleInput = this._handleInput.bind(this);
        input.addEventListener('input', this._handleInput);
        // Optionally attach an InfiniteScroller to the same container
        if (options.enableInfiniteScroll) {
            if (!options.countOffset) {
                throw new Error('Search: countOffset is required when enableInfiniteScroll is true');
            }
            this.scroller = new InfiniteScroller(this.resultsContainer, {
                endpoint: () => this._resolveEndpoint(this.input.value.trim()),
                buildRequest: offset => {
                    const query = this.input.value.trim();
                    const req = options.buildRequest(query);
                    req.offset = offset;
                    return req;
                },
                countOffset: options.countOffset,
                renderItem: options.renderItem,
                hasMoreAttr: 'hasMore',
            });
        }
    }

    /** Force a search immediately (e.g. for a pre-filled query). */
    trigger(queryOverride) {
        clearTimeout(this.debounceId);
        this._performSearch(queryOverride ?? this.input.value.trim());
    }

    destroy() {
        this.input.removeEventListener('input', this._handleInput);
        clearTimeout(this.debounceId);
        this.abortController?.abort();
        if (this.scroller) this.scroller.destroy();
    }

    _handleInput() {
        clearTimeout(this.debounceId);
        const query = this.input.value.trim();
        this.debounceId = setTimeout(() => {
            this._performSearch(query);
        }, this.delay);
    }

    async _performSearch(query) {
        this.abortController?.abort();
        this.abortController = new AbortController();

        if (this.onBeforeFetch) {
            this.onBeforeFetch(this.input, query);
        }

        let data;
        try {
            const endpoint = this._resolveEndpoint(query);
            const response = await fetch(window.siteURL + endpoint, {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(this.buildRequest(query)),
                signal: this.abortController.signal,
            });

            if (!response.ok) return;

            data = await response.json();
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Search fetch error:', error);
            }
            return;
        }

        // If the query has changed since we started, discard
        if (this.input.value.trim() !== query) return;

        // Clear previous results
        this.resultsContainer.replaceChildren();

        // Call the original onResponse for external metadata updates
        if (this._originalOnResponse) {
            this._originalOnResponse(this.input, data);
        }

        // Update the list's dataset for the embedded scroller
        this.resultsContainer.dataset.hasMore = data.response.hasMore ? '1' : '0';
        this.resultsContainer.dataset.query = query;

        // Render initial items
        const items = this._extractItems(data);
        items.forEach(item => {
            const el = this.renderItem(item);
            this.resultsContainer.appendChild(list_item(el));
            if (typeof render_math === 'function') render_math(el);
        });
    }
}

function defaultExtractItems(data) {
    const resp = data.response || data;
    return resp.items || resp.users || resp.posts || resp.articles || [];
}
