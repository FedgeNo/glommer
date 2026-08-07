import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The ways /nearby learns where "near" is: the "Use my location" button, and
 * a place search fed by the local gazetteer. Both end the same way - a
 * reload with coordinates in the query string, so the result is shareable
 * and bookmarkable rather than trapped in client state. The search commits
 * ONLY when a suggestion is clicked; typing alone never navigates.
 */
export class NearbyLocationPrompt {
    static #debounceId = null;

    static init() {
        const input = document.querySelector('.NearbyPlaceSearchInput');

        if (input) {
            input.addEventListener('input', () => {
                clearTimeout(NearbyLocationPrompt.#debounceId);
                NearbyLocationPrompt.#debounceId = setTimeout(() => NearbyLocationPrompt.#suggest(input), 250);
            });
        }

        document.addEventListener('click', (event) => {
            const suggestion = event.target.closest('.NearbyPlaceSuggestion');

            if (suggestion) {
                const parameters = new URLSearchParams({
                    lat: suggestion.dataset.latitude,
                    lng: suggestion.dataset.longitude,
                });

                window.location.search = parameters.toString();
                return;
            }

            // A click anywhere else puts the suggestions away.
            if (!event.target.closest('.NearbyPlaceSearch')) {
                document.querySelector('.NearbyPlaceSuggestions')?.remove();
            }

            const button = event.target.closest('.NearbyLocationButton');

            if (!button) {
                return;
            }

            if (!navigator.geolocation) {
                Toast.show('Your browser can\'t share a location.');
                return;
            }

            button.disabled = true;
            button.textContent = 'Locating…';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const parameters = new URLSearchParams({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    });

                    window.location.search = parameters.toString();
                },
                () => {
                    button.disabled = false;
                    button.textContent = 'Use my location';
                    Toast.show('Could not get your location. Check your browser\'s location permission.');
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    /**
     * Asks the gazetteer what the typed prefix could mean and lays the
     * answers under the box. The list exists only while it has entries.
     */
    static async #suggest(input) {
        const query = input.value.trim();
        document.querySelector('.NearbyPlaceSuggestions')?.remove();

        if (query.length < 2) return;

        const result = await Api.post('/api/search-places', { q: query });

        if (!result || result.places.length === 0) return;
        if (input.value.trim() !== query) return;

        const list = document.createElement('ul');
        list.className = 'NearbyPlaceSuggestions';

        for (const place of result.places) {
            const item = document.createElement('li');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'NearbyPlaceSuggestion';
            button.textContent = place.label;
            button.dataset.latitude = place.latitude;
            button.dataset.longitude = place.longitude;
            item.appendWithSpace(button);

            list.appendWithSpace(item);
        }

        input.closest('.NearbyPlaceSearch').appendWithSpace(list);
    }
}

ReadyHandler.add(NearbyLocationPrompt.init);
