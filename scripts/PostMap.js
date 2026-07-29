import { ClientConfig } from '/scripts/ClientConfig.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The /map view: a Leaflet map of every geotagged post, clustered. Reads the
 * tile source from the container's data attributes (set server-side from the
 * admin Map settings) and fetches the points from /api/map-posts. Leaflet and
 * its markercluster plugin are plain global scripts loaded via MapAssets, so
 * this reads window.L rather than importing anything.
 */
export class PostMap {
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
