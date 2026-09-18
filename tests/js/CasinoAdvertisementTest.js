import { TestCase } from './TestCase.js';
import { CasinoAdvertisement } from '../../games/casino.js';

export default {
    suite: 'CasinoAdvertisement',
    tests: {
        'one iframe selects the correct creative at phone tablet and desktop boundaries'() {
            const original = window.matchMedia;
            let width = 320;
            const listeners = [];
            window.matchMedia = query => {
                TestCase.assertTrue(['(min-width: 940px)', '(min-width: 768px)'].includes(query));
                const minimum = Number(query.match(/\d+/)[0]);
                return {
                    get matches() { return width >= minimum; },
                    addEventListener(type, listener) { listeners.push(listener); },
                };
            };
            try {
                const frame = document.createElement('iframe');
                frame.dataset.desktopAd = 'https://a.magsrv.com/iframe.php?idzone=6032896&size=900x250';
                frame.dataset.mobileAd = 'https://a.magsrv.com/iframe.php?idzone=6032898&size=300x250';
                frame.dataset.tabletAd = 'https://a.magsrv.com/iframe.php?idzone=6032906&size=728x90';
                new CasinoAdvertisement(frame);
                TestCase.assertEquals(frame.dataset.mobileAd, frame.src);
                TestCase.assertEquals('300', frame.width);
                for (const [viewport, slot, adWidth, adHeight] of [
                    [767, 'mobile', '300', '250'],
                    [768, 'tablet', '728', '90'],
                    [939, 'tablet', '728', '90'],
                    [940, 'desktop', '900', '250'],
                    [1200, 'desktop', '900', '250'],
                    [800, 'tablet', '728', '90'],
                    [375, 'mobile', '300', '250'],
                ]) {
                    width = viewport;
                    listeners.forEach(change => change());
                    TestCase.assertEquals(frame.dataset[slot + 'Ad'], frame.src);
                    TestCase.assertEquals(adWidth, frame.width);
                    TestCase.assertEquals(adHeight, frame.height);
                }
                const observer = new window.MutationObserver(() => {});
                observer.observe(frame, { attributes: true, attributeFilter: ['src'] });
                listeners.forEach(change => change());
                TestCase.assertEquals(0, observer.takeRecords().length);
                observer.disconnect();
            } finally {
                window.matchMedia = original;
            }
        },
    },
};
