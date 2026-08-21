import { Strings } from '/scripts/Strings.js';
import { ClientConfig } from '/scripts/ClientConfig.js';

export class CarouselController {
    static AUTOPLAY_IMAGE_DELAY = 3000;

    constructor() {
        this._autoplayMap = new WeakMap();
        // Carousels whose current media autoplay started playing itself, so the
        // resulting 'play' event isn't mistaken for the viewer taking over and
        // stopping autoplay. The distinction is this controller's own state, so
        // it lives here beside the autoplay map rather than on the element.
        this._autoplayStartedPlay = new WeakSet();
        this._fullscreenState = null;

        this._offScreenObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) return;
                    if (entry.target.matches('video, audio')) {
                        if (!entry.target.paused) entry.target.pause();
                    } else {
                        this._stopAutoplay(entry.target);
                    }
                });
            },
            { rootMargin: '50% 0px' }
        );

        this._onClick = this._onClick.bind(this);
        this._onMediaPlay = this._onMediaPlay.bind(this);
        this._onMediaPause = this._onMediaPause.bind(this);
        this._onMediaEnded = this._onMediaEnded.bind(this);
    }

    init() {
        document.addEventListener('click', this._onClick);
        document.addEventListener('play', this._onMediaPlay, true);
        document.addEventListener('pause', this._onMediaPause, true);
        document.addEventListener('ended', this._onMediaEnded, true);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this._fullscreenState) {
                this._exitFullscreen();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            this._observeOffScreen(document.body);

            new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) this._observeOffScreen(node);
                    });
                    mutation.removedNodes.forEach((node) => {
                        if (node.nodeType === 1) this._unobserveOffScreen(node);
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        });
    }

    _loadSlide(slide) {
        if (!slide) return;
        slide.querySelectorAll('[data-src]').forEach((media) => {
            if (media.dataset.poster) {
                media.poster = media.dataset.poster;
                delete media.dataset.poster;
            }
            media.src = media.dataset.src;
            delete media.dataset.src;

            if (media.closest('.MediaFullscreenOverlay') && media instanceof HTMLImageElement && media.dataset.fullSrc) {
                media.src = media.dataset.fullSrc;
                delete media.dataset.fullSrc;
            }
        });
    }

    _advance(carousel, direction) {
        const slides = Array.from(carousel.querySelectorAll('.CarouselSlide'));
        const currentIndex = slides.findIndex((slide) => slide.classList.contains('Active'));

        // A carousel always arrives with one slide showing. If it somehow does
        // not, findIndex says -1 and the arithmetic below lands on the last
        // slide - which is a stranger place to start than the first, and the
        // line after would throw and take every later click with it.
        if (slides.length === 0 || currentIndex === -1) {
            slides[0]?.classList.add('Active');

            return;
        }

        const nextIndex = (currentIndex + direction + slides.length) % slides.length;

        slides[currentIndex].classList.remove('Active');
        slides[nextIndex].classList.add('Active');

        carousel.querySelectorAll('video, audio').forEach((media) => media.pause());

        this._loadSlide(slides[nextIndex]);

        for (let i = nextIndex + 1; i <= nextIndex + ClientConfig.get('carouselEagerItems') && i < slides.length; i++) {
            this._loadSlide(slides[i]);
        }

        const counter = carousel.querySelector('.CarouselCounter');
        if (counter) counter.textContent = (nextIndex + 1) + ' / ' + slides.length;
    }

    _scheduleAutoplayAdvance(carousel) {
        if (!this._autoplayMap.has(carousel)) return;

        const media = carousel.querySelector('.CarouselSlide.Active video, .CarouselSlide.Active audio');
        if (media) {
            this._autoplayMap.set(carousel, null);
            this._autoplayStartedPlay.add(carousel);
            media.play().catch(() => {
                this._autoplayStartedPlay.delete(carousel);
            });
            return;
        }

        const timeoutId = setTimeout(() => {
            this._advance(carousel, 1);
            this._scheduleAutoplayAdvance(carousel);
        }, CarouselController.AUTOPLAY_IMAGE_DELAY);

        this._autoplayMap.set(carousel, timeoutId);
    }

    _startAutoplay(carousel) {
        if (this._autoplayMap.has(carousel)) return;
        this._autoplayMap.set(carousel, null);
        this._scheduleAutoplayAdvance(carousel);
        const toggle = carousel.querySelector('.CarouselAutoplayButton');
        if (toggle) toggle.textContent = Strings.for('CarouselController').stopAutoplay || '';
    }

    _stopAutoplay(carousel) {
        if (!this._autoplayMap.has(carousel)) return;
        const pendingTimeout = this._autoplayMap.get(carousel);
        if (pendingTimeout) clearTimeout(pendingTimeout);
        this._autoplayMap.delete(carousel);
        const toggle = carousel.querySelector('.CarouselAutoplayButton');
        if (toggle) toggle.textContent = Strings.for('CarouselController').autoplay || '';
    }

    _enterFullscreen(container) {
        if (this._fullscreenState) return;

        const originalParent = container.parentNode;
        const originalNextSibling = container.nextSibling;

        const overlay = document.createElement('div');
        overlay.className = 'MediaFullscreenOverlay';
        document.body.appendWithSpace(overlay);
        overlay.appendWithSpace(container);
        container.classList.add('InFullscreen');

        container.querySelectorAll('img[data-full-src]').forEach(img => {
            if (img.src !== img.dataset.fullSrc) {
                img.src = img.dataset.fullSrc;
                img.removeAttribute('data-full-src');
            }
        });

        const button = container.querySelector(':scope > .MediaFullscreenButton');
        if (button) {
            button.textContent = '×';
            button.setAttribute('aria-label', Strings.for('CarouselController').exitFullscreen || '');
        }

        this._fullscreenState = { container, overlay, originalParent, originalNextSibling };
    }

    _exitFullscreen() {
        if (!this._fullscreenState) return;
        const { container, overlay, originalParent, originalNextSibling } = this._fullscreenState;
        container.classList.remove('InFullscreen');
        originalParent.insertBefore(container, originalNextSibling);
        overlay.remove();
        const button = container.querySelector(':scope > .MediaFullscreenButton');
        if (button) {
            button.textContent = '⛶';
            button.setAttribute('aria-label', Strings.for('CarouselController').fullscreen || '');
        }
        this._fullscreenState = null;
    }

    _observeOffScreen(root) {
        if (root.matches?.('video, audio, .Carousel')) {
            this._offScreenObserver.observe(root);
        }
        root.querySelectorAll?.('video, audio, .Carousel').forEach((el) =>
            this._offScreenObserver.observe(el)
        );
    }

    _unobserveOffScreen(root) {
        if (root.matches?.('video, audio, .Carousel')) {
            this._offScreenObserver.unobserve(root);
        }
        root.querySelectorAll?.('video, audio, .Carousel').forEach((el) =>
            this._offScreenObserver.unobserve(el)
        );
    }

    _onClick(event) {
        const prevNext = event.target.closest('.CarouselPrevButton, .CarouselNextButton');
        if (prevNext) {
            const carousel = prevNext.closest('.Carousel');
            this._stopAutoplay(carousel);
            this._advance(carousel, prevNext.classList.contains('CarouselNextButton') ? 1 : -1);
            return;
        }

        const autoplayBtn = event.target.closest('.CarouselAutoplayButton');
        if (autoplayBtn) {
            const carousel = autoplayBtn.closest('.Carousel');
            if (this._autoplayMap.has(carousel)) {
                this._stopAutoplay(carousel);
            } else {
                this._startAutoplay(carousel);
            }
            return;
        }

        const img = event.target.closest('.Carousel .ImageItem img');
        if (img) {
            this._stopAutoplay(img.closest('.Carousel'));
            return;
        }

        const fullscreenBtn = event.target.closest('.MediaFullscreenButton');
        if (fullscreenBtn) {
            if (this._fullscreenState) {
                this._exitFullscreen();
            } else {
                const container = fullscreenBtn.closest('.Carousel, .FeedItem');
                if (container) this._enterFullscreen(container);
            }
            return;
        }
    }

    _onMediaPlay(event) {
        const media = event.target.closest('.Carousel video, .Carousel audio');
        if (!media) return;
        const carousel = media.closest('.Carousel');
        if (this._autoplayStartedPlay.has(carousel)) {
            this._autoplayStartedPlay.delete(carousel);
            return;
        }
        this._stopAutoplay(carousel);
    }

    _onMediaPause(event) {
        const media = event.target.closest?.('.Carousel video, .Carousel audio');
        if (!media || media.ended) return;
        this._stopAutoplay(media.closest('.Carousel'));
    }

    _onMediaEnded(event) {
        const media = event.target.closest('.Carousel video, .Carousel audio');
        if (!media) return;
        const carousel = media.closest('.Carousel');
        if (!this._autoplayMap.has(carousel)) return;
        this._advance(carousel, 1);
        this._scheduleAutoplayAdvance(carousel);
    }
}
