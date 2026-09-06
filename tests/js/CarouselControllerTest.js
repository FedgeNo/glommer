import { TestCase } from './TestCase.js';
import { CarouselController } from '../../scripts/Controllers.js';

export default {
    suite: 'CarouselController',
    tests: {
        'initial observation runs immediately when the controller starts'() {
            const realIntersectionObserver = globalThis.IntersectionObserver;
            const realMutationObserver = globalThis.MutationObserver;
            const observed = [];
            let mutationTarget = null;
            let mutationOptions = null;

            globalThis.IntersectionObserver = class {
                observe(element) { observed.push(element); }
                unobserve() {}
            };
            globalThis.MutationObserver = class {
                constructor() {}
                observe(target, options) {
                    mutationTarget = target;
                    mutationOptions = options;
                }
            };

            const carousel = document.createElement('div');
            carousel.className = 'Carousel';
            document.body.appendChild(carousel);

            try {
                new CarouselController().init();

                TestCase.assertTrue(observed.includes(carousel), 'existing carousels should be observed during init');
                TestCase.assertEquals(document.body, mutationTarget);
                TestCase.assertTrue(mutationOptions.childList);
                TestCase.assertTrue(mutationOptions.subtree);
            } finally {
                carousel.remove();
                globalThis.IntersectionObserver = realIntersectionObserver;
                globalThis.MutationObserver = realMutationObserver;
            }
        },
        'clicking an image enters the same fullscreen overlay as its button'() {
            const realIntersectionObserver = globalThis.IntersectionObserver;
            const realMutationObserver = globalThis.MutationObserver;
            globalThis.IntersectionObserver = class {
                observe() {}
                unobserve() {}
            };
            globalThis.MutationObserver = class {
                observe() {}
            };

            const item = document.createElement('div');
            item.className = 'FeedItem';
            const imageItem = document.createElement('div');
            imageItem.className = 'ImageItem';
            const image = document.createElement('img');
            imageItem.appendChild(image);
            item.appendChild(imageItem);
            document.body.appendChild(item);

            try {
                new CarouselController().init();
                const click = image.ownerDocument.createEvent('Event');
                click.initEvent('click', true, true);
                image.dispatchEvent(click);

                TestCase.assertTrue(item.classList.contains('InFullscreen'));
                TestCase.assertEquals('MediaFullscreenOverlay', item.parentElement.className);
            } finally {
                item.closest('.MediaFullscreenOverlay')?.remove();
                item.remove();
                globalThis.IntersectionObserver = realIntersectionObserver;
                globalThis.MutationObserver = realMutationObserver;
            }
        },
    }
};
