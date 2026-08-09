/**
 * A button whose words change, that does not change size when they do.
 *
 * A row of buttons where one relabels itself shoves every neighbour along -
 * "Post" becoming "Schedule Post" moves the whole row, and the eye loses where
 * it was. So every label a button can show is built into it at once, stacked
 * in one grid cell with the inactive ones hidden but still measured. The
 * button is then as wide as its longest wording from the start and stays
 * there, whatever it currently says.
 *
 * Reserving each button's own longest label rather than giving them all one
 * width: a common width has to fit the longest label anywhere in the row, and
 * turns "Post" into a slab. The widths are never written down, so relabelling
 * or translating a button needs nothing here.
 */
export class ToggleButton {
    /** The stand-in a count-carrying label reserves room for. Mirrors ToggleButton.php. */
    static RESERVED_COUNT = '(XXX)';

    /**
     * @param {string[]} labels every wording it can show, the first to start with
     * @param {string} className its own identity, beside Button and ToggleButton
     * @param {?string} reserve a wording never shown, there only to be measured -
     *   what a label carrying a count uses, since the count is not known in
     *   advance and changes under the reader
     */
    static build(labels, className, reserve = null) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button ToggleButton ' + className;

        for (const text of labels) {
            const label = document.createElement('span');
            label.className = 'ToggleButtonLabel';
            label.textContent = text;
            button.appendChild(label);
        }

        ToggleButton.select(button, labels[0]);

        if (reserve !== null) {
            const reservation = document.createElement('span');
            reservation.className = 'ToggleButtonLabel Inactive ToggleButtonReservation';
            // Furniture holding a width, not a second thing the button says.
            reservation.setAttribute('aria-hidden', 'true');
            reservation.textContent = reserve;
            button.appendChild(reservation);
        }

        return button;
    }

    /**
     * Rewrites what a one-label button says, leaving its reserved width alone.
     *
     * For the buttons whose wording carries a count: there is nothing to
     * select between, because the new wording was never one of the choices.
     */
    static setLabel(button, text) {
        const live = button.querySelector('.ToggleButtonLabel:not(.ToggleButtonReservation)');

        if (live) live.textContent = text;
    }

    /** Shows this wording. The rest stay where they are, holding the width. */
    static select(button, text) {
        for (const label of button.querySelectorAll('.ToggleButtonLabel')) {
            label.classList.toggle('Inactive', label.textContent !== text);
        }
    }

    /** What it currently says - its textContent is every label at once. */
    static selected(button) {
        const showing = button.querySelector('.ToggleButtonLabel:not(.Inactive)');

        return showing === null ? '' : showing.textContent;
    }
}
