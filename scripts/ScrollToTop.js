export class ScrollToTop {
    static #THRESHOLD = 600;

    static init() {
        // Toggle the button's visibility
        window.addEventListener('scroll', () => {
            const button = document.querySelector('.ScrollToTopButton');
            if (button) {
                button.classList.toggle('Scrolled', window.scrollY > ScrollToTop.#THRESHOLD);
            }
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ScrollToTopButton');
            if (!button) return;

            // Somebody who has asked for less movement gets none of the
            // journey: straight to a pixel from the top, then that last pixel
            // scrolled. The pixel is not decoration - a list that loads as it
            // reaches its end is watching for the view to arrive, and a jump
            // that lands exactly on zero never gives it anything to notice.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                window.scrollTo({ top: 1, behavior: 'instant' });
                window.scrollTo({ top: 0, behavior: 'smooth' });

                return;
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}

