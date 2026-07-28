import { ClientConfig } from '/ClientConfig.js';

export class CarouselController {
    static AUTOPLAY_IMAGE_DELAY = 3000;

    constructor() {
        this._autoplayMap = new WeakMap();
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

            if (media.closest('.MediaFullscreenOverlay')) {
                if (media instanceof HTMLImageElement && media.dataset.fullSrc) {
                    media.src = media.dataset.fullSrc;
                    delete media.dataset.fullSrc;
                } else if (media instanceof HTMLVideoElement && media.dataset.posterFullSrc) {
                    media.poster = media.dataset.posterFullSrc;
                    delete media.dataset.posterFullSrc;
                }
            }
        });
    }

    _advance(carousel, direction) {
        const slides = Array.from(carousel.querySelectorAll('.CarouselSlide'));
        const currentIndex = slides.findIndex((slide) => slide.classList.contains('Active'));
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
            carousel.dataset.autoplayStartedPlay = '1';
            media.play().catch(() => {
                delete carousel.dataset.autoplayStartedPlay;
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
        const toggle = carousel.querySelector('.CarouselAutoplay');
        if (toggle) toggle.textContent = 'Stop Autoplay';
    }

    _stopAutoplay(carousel) {
        if (!this._autoplayMap.has(carousel)) return;
        const pendingTimeout = this._autoplayMap.get(carousel);
        if (pendingTimeout) clearTimeout(pendingTimeout);
        this._autoplayMap.delete(carousel);
        const toggle = carousel.querySelector('.CarouselAutoplay');
        if (toggle) toggle.textContent = 'Autoplay';
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

        container.querySelectorAll('video[data-poster-full-src]').forEach(video => {
            if (video.poster !== video.dataset.posterFullSrc) {
                video.poster = video.dataset.posterFullSrc;
                video.removeAttribute('data-poster-full-src');
            }
        });

        const button = container.querySelector(':scope > .MediaFullscreen');
        if (button) {
            button.textContent = '×';
            button.setAttribute('aria-label', 'Exit fullscreen');
        }

        this._fullscreenState = { container, overlay, originalParent, originalNextSibling };
    }

    _exitFullscreen() {
        if (!this._fullscreenState) return;
        const { container, overlay, originalParent, originalNextSibling } = this._fullscreenState;
        container.classList.remove('InFullscreen');
        originalParent.insertBefore(container, originalNextSibling);
        overlay.remove();
        const button = container.querySelector(':scope > .MediaFullscreen');
        if (button) {
            button.textContent = '⛶';
            button.setAttribute('aria-label', 'Fullscreen');
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
        const prevNext = event.target.closest('.CarouselPrev, .CarouselNext');
        if (prevNext) {
            const carousel = prevNext.closest('.Carousel');
            this._stopAutoplay(carousel);
            this._advance(carousel, prevNext.classList.contains('CarouselNext') ? 1 : -1);
            return;
        }

        const autoplayBtn = event.target.closest('.CarouselAutoplay');
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

        const fullscreenBtn = event.target.closest('.MediaFullscreen');
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
        if (carousel.dataset.autoplayStartedPlay === '1') {
            delete carousel.dataset.autoplayStartedPlay;
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

