import { ClientConfig, ReadyHandler, Strings, sync_theme_color } from '/scripts/Runtime.js';

// Before anything renders, so a twin can read its words synchronously. A
// renderer that had to await its own text would be async all the way up, and
// the words are one cached module rather than something worth restructuring
// the whole client around.
await Strings.load();

const { EmojiRenderer, RelativeTime } = await import('/scripts/HTMLObjects.js');
const { CarouselController, ScrollToTop, WebSocketManager } = await import('/scripts/Controllers.js');

ReadyHandler.add(RelativeTime.init);
ReadyHandler.add(ScrollToTop.init);
ReadyHandler.add(EmojiRenderer.init);
ReadyHandler.add(sync_theme_color);

document.addEventListener('error', function(event) {
    const img = event.target;
    if (img instanceof HTMLImageElement && img.dataset.fullSrc && img.src !== img.dataset.fullSrc) {
        img.src = img.dataset.fullSrc;
        img.removeAttribute('data-full-src');
    }
}, true);

const wsManager = new WebSocketManager();
wsManager.init();

const carousel = new CarouselController();
carousel.init();

// Register the service worker so push (and installability) can work at all -
// only for a signed-in visitor, since there is nothing to be notified about
// otherwise, and only where the browser supports it.
if ('serviceWorker' in navigator && ClientConfig.get('currentUserId') !== null) {
    navigator.serviceWorker.register(ClientConfig.siteURL() + '/sw.js').catch(() => {});
}
