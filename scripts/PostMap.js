import { ClientConfig } from '/scripts/ClientConfig.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The /map view: a Leaflet map of every geotagged post, clustered. Reads the
 * tile source from the container's data attributes (set server-side from the
 * admin Map settings) and fetches the points from /api/map-posts. Leaflet and
 * its markercluster plugin are plain global scripts loaded via MapAssets, so
 * this reads window.L rather than importing anything.
 *
 * For a logged-in viewer, clicking the map picks a spot to post from: it drops a
 * pending pin and reveals the composer underneath with those coordinates filled
 * in, so a post can be filed at a place being looked at rather than only at the
 * browser's own location. Clicking the pending pin again cancels.
 */
export class PostMap {
    static #pendingMarker = null;

    static init() {
        const container = document.querySelector('.PostMap');

        if (!container || typeof L === 'undefined') {
            return;
        }

        const map = L.map(container).setView([20, 0], 2);

        L.tileLayer(container.dataset.tileUrl, {
            attribution: container.dataset.tileAttribution,
            maxZoom: 19,
        }).addTo(map);

        PostMap.#loadPosts(map);
        PostMap.#bindComposer(map);
    }

    /**
     * Wires map clicks to the composer below it. Only a logged-in viewer gets a
     * composer rendered, so this is a no-op for everyone else.
     */
    static async #bindComposer(map) {
        const form = document.querySelector('.MapComposer');

        if (!form) {
            return;
        }

        // Imported here rather than at the top so a logged-out viewer - who has
        // no composer to fill in - doesn't pull the whole editor chain down
        // just to look at the map.
        const { Composer } = await import('/scripts/Composer.js');

        map.on('click', (event) => PostMap.#choosePoint(Composer, map, form, event.latlng));

        // A successful post clears the pending pin and leaves a permanent one
        // where it landed, so the map reflects the new post without a reload.
        form.addEventListener('composer:posted', (event) => {
            const { latitude, longitude } = event.detail;

            PostMap.#clearPending(map);
            form.classList.remove('Active');

            if (latitude !== '' && longitude !== '') {
                L.marker([Number(latitude), Number(longitude)]).addTo(map);
            }
        });
    }

    static #choosePoint(Composer, map, form, latlng) {
        const composer = Composer.getInstance(form);

        // Composer.js mounts on DOM ready; if it somehow hasn't, there's nowhere
        // to put the coordinates, so leave the map alone rather than show an
        // empty panel.
        if (composer === null) {
            return;
        }

        PostMap.#clearPending(map);

        PostMap.#pendingMarker = L.marker(latlng, { opacity: 0.7 })
            .addTo(map)
            .bindTooltip('Posting here - click to cancel')
            .on('click', () => {
                PostMap.#clearPending(map);
                composer.setLocation(null, null);
                form.classList.remove('Active');
            });

        composer.setLocation(latlng.lat, latlng.lng);
        form.classList.add('Active');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    static #clearPending(map) {
        if (PostMap.#pendingMarker !== null) {
            map.removeLayer(PostMap.#pendingMarker);
            PostMap.#pendingMarker = null;
        }
    }

    static async #loadPosts(map) {
        let data;

        try {
            const response = await fetch(ClientConfig.siteURL() + '/api/map-posts', {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({}),
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            return;
        }

        const posts = data.response.posts;
        const cluster = L.markerClusterGroup();

        for (const post of posts) {
            const marker = L.marker([post.latitude, post.longitude]);
            marker.bindPopup(PostMap.#popupElement(post));
            cluster.addLayer(marker);
        }

        map.addLayer(cluster);

        if (posts.length > 0) {
            map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 12 });
        }
    }

    static #popupElement(post) {
        const wrapper = document.createElement('div');
        wrapper.className = 'MapPopup';

        const link = document.createElement('a');
        link.href = post.url;
        link.textContent = post.title || 'View post';
        wrapper.appendWithSpace(link);

        const author = document.createElement('div');
        author.className = 'muted';
        author.textContent = 'by ' + post.authorName;
        wrapper.appendWithSpace(author);

        return wrapper;
    }
}

ReadyHandler.add(PostMap.init);
