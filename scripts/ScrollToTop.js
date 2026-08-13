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

        // Click handler
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ScrollToTopButton');
            if (!button) return;

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}

