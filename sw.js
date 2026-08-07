// The service worker: the one piece of this site that runs with no tab open,
// so it exists for exactly that - a push arriving, and the tap that follows.
// Deliberately no offline caching: Glommer serves everything live, and a
// stale cached shell would be worse than the browser's own "you're offline".

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch (_) {
        return;
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Glommer', {
            body: payload.text || '',
            // Where a tap goes, read back in the click handler below.
            data: { url: payload.url || '/' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data && event.notification.data.url;
    if (!target) return;

    // Focus an already-open tab on the same site rather than piling up new
    // ones; open a tab only when none is there to reuse.
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (client.url === target && 'focus' in client) {
                return client.focus();
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(target);
        }
    })());
});
