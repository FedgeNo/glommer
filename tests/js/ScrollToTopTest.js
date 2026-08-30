import { TestCase } from './TestCase.js';
import { ScrollToTop } from '../../scripts/Controllers.js';

/**
 * How the button gets back to the top, which is two different journeys.
 *
 * Smoothly, for somebody who wants to see where they are going - and for
 * somebody who has asked for less movement, a jump to a pixel from the top and
 * then that pixel. The pixel is the point: a list that loads more as the view
 * reaches its end is watching for an arrival, and a jump landing exactly on
 * zero never gives it one.
 */
function withMotionPreference(reduced, body) {
    const real_matchMedia = window.matchMedia;
    const real_scrollTo = window.scrollTo;
    const scrolls = [];

    window.matchMedia = (query) => ({
        matches: reduced && query.includes('reduce'),
        addEventListener() {},
        removeEventListener() {},
    });

    window.scrollTo = (options) => scrolls.push(options);

    try {
        body(scrolls);
    } finally {
        window.matchMedia = real_matchMedia;
        window.scrollTo = real_scrollTo;
    }
}

function pressTheButton() {
    const button = document.createElement('button');
    button.className = 'ScrollToTopButton';
    document.body.appendChild(button);
    button.click();
    button.remove();
}

export default {
    suite: 'ScrollToTop',
    tests: {
        'somebody who wants the movement gets one smooth scroll'() {
            ScrollToTop.init();

            withMotionPreference(false, (scrolls) => {
                pressTheButton();

                TestCase.assertEquals(1, scrolls.length);
                TestCase.assertEquals(0, scrolls[0].top);
                TestCase.assertEquals('smooth', scrolls[0].behavior);
            });
        },

        'somebody who asked for less movement is put a pixel away and scrolled that'() {
            withMotionPreference(true, (scrolls) => {
                pressTheButton();

                TestCase.assertEquals(2, scrolls.length);
                TestCase.assertEquals(1, scrolls[0].top);
                TestCase.assertEquals('instant', scrolls[0].behavior);
                TestCase.assertEquals(0, scrolls[1].top);
                TestCase.assertEquals('smooth', scrolls[1].behavior, 'the last pixel is what a list notices');
            });
        },
    }
};
