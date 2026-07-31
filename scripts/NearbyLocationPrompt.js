import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The "Use my location" button on /nearby. Asks the browser where it is, then
 * reloads with the coordinates in the query string - the page renders the feed
 * server-side from there, so the result is shareable and bookmarkable rather
 * than trapped in client state.
 */
export class NearbyLocationPrompt {
    static init() {
        document.addEventListener('click', (event) => {
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
}

ReadyHandler.add(NearbyLocationPrompt.init);
