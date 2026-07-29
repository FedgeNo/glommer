import { ClientConfig } from '/scripts/ClientConfig.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Coordinates } from '/scripts/Coordinates.js';

/**
 * The /map view: a Leaflet map of every geotagged post, clustered. Reads the
 * tile source from the container's data attributes (set server-side from the
 * admin Map settings) and fetches the points from /api/map-posts. Leaflet and
 * its markercluster plugin are plain global scripts loaded via MapAssets, so
 * this reads window.L rather than importing anything.
 *
 * Clicking the map drops a pin and opens a small menu out of it, because a click
 * is ambiguous - it might mean "post here" or "show me what's here" - so it asks
 * rather than assuming. Browsing what's nearby needs no account; a logged-in
 * viewer additionally gets "Post here", which fills the composer below the map
 * with those coordinates, so a post can be filed at a place being looked at
 * rather than only where the browser happens to be.
 */
export class PostMap {
    static #pendingMarker = null;

    static init() {
        const container = document.querySelector('.PostMap');

        if (!container || typeof L === 'undefined') {
            return;
        }

        // Linked to a point (from a post's place line) opens there, zoomed in
        // enough to read a neighbourhood; otherwise the whole world.
        const centre = Coordinates.parse(container.dataset.centerLatitude, container.dataset.centerLongitude);
        const map = centre === null
            ? L.map(container).setView([20, 0], 2)
            : L.map(container).setView([centre.latitude, centre.longitude], 13);

        L.tileLayer(container.dataset.tileUrl, {
            attribution: container.dataset.tileAttribution,
            maxZoom: 19,
        }).addTo(map);

        // An explicit centre wins: fitting the pins would immediately pull the
        // view back off the point that was linked to.
        PostMap.#loadPosts(map, centre === null);
        PostMap.#bindComposer(map);
    }

    /**
     * Wires clicking the map to dropping a pin. Everyone gets the pin and its
     * menu - browsing what's near a point needs no account - but only a
     * logged-in viewer has a composer to hand the coordinates to.
     */
    static async #bindComposer(map) {
        const form = document.querySelector('.MapComposer');

        // Imported only when there's a composer to drive, so a logged-out
        // viewer doesn't pull the whole editor chain down just to look.
        const { Composer } = form ? await import('/scripts/Composer.js') : { Composer: null };

        // With a composer, clicks route through it and the pin is placed by the
        // locationchange event below - so the map and the form can't disagree,
        // whether a point was picked, the pin dragged, the Add/Remove Location
        // button used, or a post submitted. Without one, the pin is placed
        // directly since there's nothing to keep in step with.
        map.on('click', (event) => {
            const composer = form ? Composer.getInstance(form) : null;

            if (composer === null) {
                PostMap.#placePending(Composer, map, form, event.latlng.lat, event.latlng.lng);
                return;
            }

            composer.setLocation(event.latlng.lat, event.latlng.lng);
        });

        if (!form) {
            return;
        }

        form.addEventListener('composer:locationchange', (event) => {
            const { latitude, longitude } = event.detail;

            if (latitude === null) {
                // Cleared - by the Remove Location button, or by the pin's own
                // menu. The composer stays open so nothing typed is lost; the
                // post simply carries no location unless another point is picked.
                PostMap.#clearPending(map);
                return;
            }

            PostMap.#placePending(Composer, map, form, latitude, longitude);
        });

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

    /**
     * Places the pending pin, or moves the existing one. Moving rather than
     * recreating is what lets a drag update the form without the pin being torn
     * out from under the cursor mid-gesture.
     */
    static #placePending(Composer, map, form, latitude, longitude) {
        if (PostMap.#pendingMarker !== null) {
            PostMap.#pendingMarker.setLatLng([latitude, longitude]);
            // The menu carries the coordinates and the nearby link, so it has to
            // be rebuilt when the pin moves or a drag leaves it pointing at
            // where the pin used to be.
            PostMap.#pendingMarker.setPopupContent(PostMap.#pinMenu(Composer, map, form, latitude, longitude));
            return;
        }

        PostMap.#pendingMarker = L.marker([latitude, longitude], { draggable: true, opacity: 0.7 })
            .addTo(map)
            .bindPopup(PostMap.#pinMenu(Composer, map, form, latitude, longitude))
            .on('dragend', (event) => {
                const position = event.target.getLatLng();
                const composer = form ? Composer.getInstance(form) : null;

                if (composer !== null) {
                    composer.setLocation(position.lat, position.lng);
                    return;
                }

                PostMap.#pendingMarker.setPopupContent(PostMap.#pinMenu(Composer, map, form, position.lat, position.lng));
            })
            .openPopup();
    }

    /**
     * The little menu that opens out of a dropped pin. A click on the map is
     * ambiguous - it might mean "post here" or "show me what's here" - so it
     * asks instead of assuming, and nothing scrolls until a choice is made.
     */
    static #pinMenu(Composer, map, form, latitude, longitude) {
        const menu = document.createElement('div');
        menu.className = 'MapPinMenu d-flex flex-column gap-1';

        const position = document.createElement('div');
        position.className = 'muted';
        position.textContent = latitude.toFixed(4) + ', ' + longitude.toFixed(4);
        menu.appendWithSpace(position);

        // Only offered when there's somewhere to post from - a logged-out
        // viewer still gets the pin and the nearby link.
        if (form) {
            const post_here = document.createElement('button');
            post_here.type = 'button';
            post_here.className = 'Button';
            post_here.textContent = 'Post here';
            post_here.addEventListener('click', () => {
                form.classList.add('Active');
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
            menu.appendWithSpace(post_here);
        }

        const nearby = document.createElement('a');
        nearby.className = 'Button';
        nearby.href = ClientConfig.siteURL() + '/nearby?lat=' + encodeURIComponent(latitude) + '&lng=' + encodeURIComponent(longitude);
        nearby.textContent = 'Posts nearby';
        menu.appendWithSpace(nearby);

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'Button';
        clear.textContent = 'Clear pin';
        clear.addEventListener('click', () => {
            const composer = form ? Composer.getInstance(form) : null;

            if (composer !== null) {
                composer.setLocation(null, null);
                return;
            }

            PostMap.#clearPending(map);
        });
        menu.appendWithSpace(clear);

        return menu;
    }

    static #clearPending(map) {
        if (PostMap.#pendingMarker !== null) {
            map.removeLayer(PostMap.#pendingMarker);
            PostMap.#pendingMarker = null;
        }
    }

    static async #loadPosts(map, fitToPins) {
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

        if (fitToPins && posts.length > 0) {
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
