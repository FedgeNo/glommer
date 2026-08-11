import { Api } from '/scripts/Api.js';
import { list_in, list_item } from '/scripts/utils.js';
import { render_math } from '/scripts/MathRenderer.js';
import { InfiniteScroller } from '/scripts/InfiniteScroller.js';
import { OtherUser } from '/scripts/OtherUser.js';
import { Post } from '/scripts/Post.js';
import { BannedUser } from '/scripts/BannedUser.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Strings } from '/scripts/Strings.js';

export class Search {
    constructor(input, options) {
        this.input = input;
        this._resolveEndpoint = typeof options.endpoint === 'function'
            ? options.endpoint
            : () => options.endpoint;
        this.buildRequest = options.buildRequest;
        // Either the list itself or, for a list that may not be on the page at
        // all - an empty one renders only its notice - something that builds it
        // on demand. Resolved per render rather than once here, so the first
        // results are what bring the list into being.
        this._resolveContainer = typeof options.resultsContainer === 'function'
            ? options.resultsContainer
            : () => options.resultsContainer;
        this.renderItem = options.renderItem;
        this.delay = options.delay ?? 300;
        this.onBeforeFetch = options.onBeforeFetch || null;
        this._originalOnResponse = options.onResponse || null;
        this._extractItems = options.extractItems || defaultExtractItems;

        this.abortController = null;
        this.debounceId = null;

        this._handleInput = this._handleInput.bind(this);
        input.addEventListener('input', this._handleInput);

        // A function resolver is NOT called here: it may build the list over
        // an empty-state notice, and construction happens on page load, when
        // that notice is exactly what should be showing. The list is built
        // when the first results arrive to go in it (_performSearch).
        this.resultsContainer = typeof options.resultsContainer === 'function' ? null : options.resultsContainer;
        this._scrollerOptions = options.enableInfiniteScroll ? options : null;

        if (options.enableInfiniteScroll && !options.countOffset) {
            throw new Error('Search: countOffset is required when enableInfiniteScroll is true');
        }

        if (this.resultsContainer) {
            this._ensureScroller();
        }
    }

    /** Binds the scroller to the container, once there is one. */
    _ensureScroller() {
        if (this.scroller || this._scrollerOptions === null || !this.resultsContainer) return;

        const options = this._scrollerOptions;

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
        this.input.closest('.SearchBox')?.classList.toggle('HasQuery', query !== '');
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

        // Quiet: a search runs on every keystroke and cancels the one before
        // it, so a failure is not worth interrupting somebody's typing over.
        const data = await Api.post(this._resolveEndpoint(query), this.buildRequest(query), {
            signal: this.abortController.signal,
            quiet: true,
        });

        if (!data) return;

        if (this.input.value.trim() !== query) return;

        this.resultsContainer = this.resultsContainer ?? this._resolveContainer();

        if (!this.resultsContainer) return;

        // The list may have only just been built; bind the scroller to it now.
        this._ensureScroller();

        this.resultsContainer.replaceChildren();

        // Extracted before the callback runs, and handed to it: every endpoint
        // names its results something slightly different (items, users, posts)
        // and a callback that guessed wrong used to throw here - after the
        // list had been emptied and before anything was rendered into it, so
        // the search simply went blank.
        const items = this._extractItems(data);

        if (this._originalOnResponse) {
            this._originalOnResponse(this.input, data, items);
        }

        items.forEach(item => {
            const el = this.renderItem(item);
            this.resultsContainer.appendWithSpace(list_item(el));
            render_math(el);
        });

        // Enable the scroller if there are more pages
        if (this.scroller && data.hasMore) {
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

        Search.#initUsers();
        const posts = Search.#initPosts();
        Search.#initFriends();
        Search.#initBannedUsers();

        Search.#searchFromURL(posts);
    }

    /**
     * Arriving with ?q= should actually search, not just fill the box.
     *
     * This runs after the initialisers on purpose. It used to fill the input
     * and dispatch an input event first, but the listener that would react to
     * one does not exist until #initPosts has constructed the Search - so the
     * event fired into nothing and a linked search landed on an empty page.
     * Calling trigger() also skips the debounce, which there is no reason to
     * wait out when the query arrived with the page.
     */
    static #searchFromURL(search) {
        if (search === null) {
            return;
        }

        const query = new URLSearchParams(window.location.search).get('q');

        // An empty q= is the same as no q= - searching for nothing would just
        // hide the default feed behind an empty result list.
        if (query === null || query.trim() === '') {
            return;
        }

        search.input.value = query;

        // Typing sets this from _handleInput, and it is what reveals the clear
        // button. Arriving with a query has to leave the box in the same state
        // as typing one, or there is no way to get back out of the search.
        search.input.closest('.SearchBox')?.classList.add('HasQuery');

        search.trigger();
    }

    static #initUsers() {
        const input = document.querySelector('.UserSearchInput');
        if (!input) return;
        const section = input.closest('.UserSearch').querySelector('.UserSearchSection');
        new Search(input, {
            endpoint: '/api/search-users',
            buildRequest: query => ({ q: query }),
            resultsContainer: () => list_in(section, 'UserList UserSearchList d-flex flex-column'),
            renderItem: userData => OtherUser.fromData(userData).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.OtherUser').length,
            onResponse: (input, data, items) => {
                const searching = input.value.trim() !== '';
                section.querySelector('h2').textContent = searching ? 'User Search Results' : 'Suggested Users';

                // Mirrors UserSearchList's two empty notices. Without it the
                // list just empties and the heading is the only thing left,
                // which reads as the page having failed rather than answered.
                if (items.length === 0) {
                    const words = Strings.for('UserSearchList', {
                        noSuggestions: 'No suggestions right now - suggestions come from the friends of people you are already friends with. Search above to find someone by name.',
                        noMatches: 'Nobody here matches that.',
                    });
                    const notice = document.createElement('p');
                    notice.className = 'muted Notice';
                    notice.textContent = searching ? words.noMatches : words.noSuggestions;
                    section.querySelector('.UserList')?.appendWithSpace(list_item(notice));
                }
            }
        });
    }

    static #initPosts() {
        const input = document.querySelector('.PostSearchInput');
        if (!input) return null;
        const container = document.querySelector('.SearchFeedList');
        return new Search(input, {
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
                document.querySelector('.PinnedPostSection')?.classList.toggle('Searching', searching);
            },
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
                document.querySelector('.ReceivedFriendRequestSection')?.classList.toggle('Searching', searching);
                document.querySelector('.FriendSection')?.classList.toggle('Searching', searching);
                document.querySelector('.SentFriendRequestSection')?.classList.toggle('Searching', searching);
            },
        });
    }

    static #initBannedUsers() {
        const input = document.querySelector('.BannedUserSearchInput');
        if (!input) return;
        const container = document.querySelector('.BannedUserList');
        new Search(input, {
            endpoint: query => query ? '/api/search-banned-users' : '/api/banned-history',
            buildRequest: query => query ? { q: query } : {},
            resultsContainer: container,
            renderItem: data => BannedUser.fromData(data).toElement(),
            enableInfiniteScroll: true,
            countOffset: list => list.querySelectorAll('.BannedUser').length,
            onResponse: (input, data, items) => {
                if (items.length === 0) {
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
