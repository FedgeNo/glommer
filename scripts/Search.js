import { ClientConfig } from '/scripts/ClientConfig.js';
import { csrf_headers, list_item } from '/scripts/utils.js';
import { InfiniteScroller } from '/scripts/InfiniteScroller.js';
import { OtherUser } from '/scripts/OtherUser.js';
import { Post } from '/scripts/Post.js';
import { BannedUser } from '/scripts/BannedUser.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

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

        if (options.enableInfiniteScroll) {
            if (!options.countOffset) {
                throw new Error('Search: countOffset is required when enableInfiniteScroll is true');
            }
            this.scroller = InfiniteScroller.create(this.resultsContainer, {
                endpoint: () => this._resolveEndpoint(this.input.value.trim()),
                buildRequest: offset => {
                    const query = this.input.value.trim();
                    const req = options.buildRequest(query);
                    req.offset = offset;
                    return req;
                },
                countOffset: options.countOffset,
                renderItem: options.renderItem,
                active: false,
            });
        }
    }

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
            const response = await fetch(ClientConfig.siteURL() + endpoint, {
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

        if (this.input.value.trim() !== query) return;

        this.resultsContainer.replaceChildren();

        if (this._originalOnResponse) {
            this._originalOnResponse(this.input, data);
        }

        this.resultsContainer.dataset.query = query;

        const items = this._extractItems(data);
        items.forEach(item => {
            const el = this.renderItem(item);
            this.resultsContainer.appendWithSpace(list_item(el));
            if (typeof render_math === 'function') render_math(el);
        });

        // Enable the scroller if there are more pages
        if (this.scroller && data.response.hasMore) {
            this.scroller.setActive(true);
        }
    }

    // ----------------------------------------------------------------
    // Static initialisation
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', (event) => {
            const clearBtn = event.target.closest('.SearchClearButton');
            if (clearBtn) {
                const input = clearBtn.closest('.SearchBox')?.querySelector('.SearchInput');
                if (input) {
                    input.value = '';
                    input.focus();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });

        const query = new URLSearchParams(window.location.search).get('q');
        const prefillInput = document.querySelector('.PostSearchInput');
        if (query !== null && prefillInput) {
            prefillInput.value = query;
            prefillInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        Search.#initUsers();
        Search.#initPosts();
        Search.#initFriends();
        Search.#initBannedUsers();
    }

    static #initUsers() {
        const input = document.querySelector('.UserSearchInput');
        if (!input) return;
        const container = input.closest('.UserSearch').querySelector('.UserSearchSection .UserList');
        new Search(input, {
            endpoint: '/api/search-users',
            buildRequest: query => ({ q: query }),
            resultsContainer: container,
            renderItem: userData => OtherUser.fromData(userData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.OtherUser').length,
            onResponse: (input, data) => {
                const section = input.closest('.UserSearch').querySelector('.UserSearchSection');
                section.querySelector('h2').textContent = input.value.trim() === '' ? 'Suggested Users' : 'User Search Results';
                section.dataset.query = input.value.trim();
                section.dataset.offset = String(data.response.users.length);
            }
        });
    }

    static #initPosts() {
        const input = document.querySelector('.PostSearchInput');
        if (!input) return;
        const container = document.querySelector('.SearchFeedList');
        new Search(input, {
            endpoint: '/api/search-posts',
            buildRequest: query => ({
                q: query,
                userId: input.closest('.PostSearch').dataset.userId || ''
            }),
            resultsContainer: container,
            renderItem: postData => Post.fromData(postData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.Post').length,
            onBeforeFetch: (input, query) => {
                const searching = query !== '';
                document.querySelector('.SearchFeedSection')?.classList.toggle('Searching', searching);
                document.querySelector('.ProfileFeedSection')?.classList.toggle('Searching', searching);
            },
            onResponse: (input, data) => {
                container.dataset.query = input.value.trim();
                container.dataset.userId = input.closest('.PostSearch').dataset.userId || '';
            }
        });
    }

    static #initFriends() {
        const input = document.querySelector('.FriendSearchInput');
        if (!input) return;
        const container = document.querySelector('.FriendSearchList');
        new Search(input, {
            endpoint: '/api/search-friends',
            buildRequest: query => ({
                q: query,
                userId: input.closest('.FriendSearch').dataset.userId
            }),
            resultsContainer: container,
            renderItem: userData => OtherUser.fromData(userData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.OtherUser').length,
            onBeforeFetch: (input, query) => {
                const searching = query !== '';
                document.querySelector('.FriendSearchSection')?.classList.toggle('Searching', searching);
                document.querySelector('.PendingFriendRequestSection')?.classList.toggle('Searching', searching);
                document.querySelector('.FriendSection')?.classList.toggle('Searching', searching);
                document.querySelector('.OutgoingFriendRequestSection')?.classList.toggle('Searching', searching);
            },
            onResponse: (input, data) => {
                container.dataset.query = input.value.trim();
            }
        });
    }

    static #initBannedUsers() {
        const input = document.querySelector('.BannedUserSearchInput');
        if (!input) return;
        const container = document.querySelector('.BannedUserList');
        new Search(input, {
            endpoint: query => query ? '/api/search-banned-users' : '/api/banned-history',
            buildRequest: query => {
                if (container) container.dataset.searchQuery = query;
                return query ? { q: query } : {};
            },
            resultsContainer: container,
            renderItem: data => BannedUser.fromData(data).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.BannedUser').length,
            onResponse: (input, data) => {
                if (container) container.dataset.hasMore = data.response.hasMore ? '1' : '0';
                if (data.response.items.length === 0) {
                    const notice = document.createElement('p');
                    notice.className = 'muted Notice';
                    notice.textContent = input.value.trim() === '' ? 'No banned users.' : 'No banned users match that search.';
                    container.appendWithSpace(list_item(notice));
                }
            }
        });
    }
}

function defaultExtractItems(data) {
    const resp = data.response || data;
    return resp.items || resp.users || resp.posts || resp.articles || [];
}

ReadyHandler.add(Search.init);
