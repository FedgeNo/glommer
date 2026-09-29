import { ReadyHandler } from '/scripts/Runtime.js';

export class NavDropdown {
    static init() {
        document.querySelectorAll('.MainNavigation .NavDropdown').forEach(dropdown => {
            const trigger = dropdown.querySelector(':scope > .NavDropdownTrigger');
            const menu = dropdown.querySelector(':scope > .NavDropdownMenu');
            if (!trigger || !menu) return;

            const measure = () => {
                // offsetWidth excludes the dropdown's opening transform.
                const overhang = trigger.offsetWidth - menu.offsetWidth;
                const right_gap = dropdown.clientWidth - menu.offsetLeft - menu.offsetWidth;
                dropdown.classList.toggle('RightAlignedMenu', Math.abs(right_gap) < Math.abs(menu.offsetLeft));
                dropdown.classList.toggle('NarrowerMenu', menu.offsetWidth > 0 && overhang > 0);
                dropdown.style.setProperty('--nav-menu-width', menu.offsetWidth + 'px');
                dropdown.style.setProperty('--nav-tab-overhang', Math.max(0, overhang) + 'px');
            };
            const observer = new ResizeObserver(measure);
            observer.observe(trigger);
            observer.observe(menu);
            measure();
        });
    }
}

ReadyHandler.add(NavDropdown.init);
