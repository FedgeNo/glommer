export class Dialog {
    // Currently active cancel callback – set by confirm/prompt, cleared on close
    static #activeCancel = null;

    /**
     * Show a confirmation dialog with OK / Cancel buttons.
     * @param {string} message
     * @returns {Promise<boolean>} – resolves true for OK, false for Cancel/Escape
     */
    static confirm(message) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions d-flex gap-2';

            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'Button ConfirmDialogCancelButton';
            cancelButton.textContent = 'Cancel';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = 'OK';

            actions.appendWithSpace(cancelButton);
            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const finish = (confirmed) => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                overlay.remove();
                resolve(confirmed);
            };

            Dialog.#activeCancel = () => finish(false);

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(false);
                }
            };

            cancelButton.addEventListener('click', () => finish(false));
            confirmButton.addEventListener('click', () => finish(true));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish(false);
                }
            });
            document.addEventListener('keydown', onKeydown);

            cancelButton.focus();
        });
    }

    /**
     * Show a message with a single OK button.
     * @param {string} message
     * @returns {Promise<void>} – resolves when dismissed
     */
    static alert(message) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions d-flex gap-2';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = 'OK';

            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const finish = () => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                overlay.remove();
                resolve();
            };

            Dialog.#activeCancel = finish;

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish();
                }
            };

            confirmButton.addEventListener('click', finish);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish();
                }
            });
            document.addEventListener('keydown', onKeydown);

            confirmButton.focus();
        });
    }

    /**
     * Show a prompt dialog with a textarea and a confirm button.
     * @param {string} message
     * @param {object} [options]
     * @param {string} [options.placeholder] – placeholder text for the textarea
     * @param {string} [options.confirmLabel='OK'] – confirm button label
     * @returns {Promise<string|null>} – resolves with the input value, or null for Cancel/Escape
     */
    static prompt(message, options = {}) {
        Dialog.#activeCancel?.();

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'ConfirmDialogOverlay';

            const card = document.createElement('div');
            card.className = 'ConfirmDialogCard';

            const text = document.createElement('div');
            text.className = 'ConfirmDialogMessage';
            text.textContent = message;
            card.appendWithSpace(text);

            const input = document.createElement('textarea');
            input.className = 'ConfirmDialogInput';
            input.rows = 3;

            if (options.placeholder) {
                input.placeholder = options.placeholder;
            }

            card.appendWithSpace(input);

            const actions = document.createElement('div');
            actions.className = 'ConfirmDialogActions d-flex gap-2';

            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'Button ConfirmDialogCancelButton';
            cancelButton.textContent = 'Cancel';

            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'Button ConfirmDialogConfirmButton';
            confirmButton.textContent = options.confirmLabel || 'OK';
            // Off until the box has something in it - a rule about the input,
            // not a wait, so it does not throb.
            confirmButton.disabled = true;

            actions.appendWithSpace(cancelButton);
            actions.appendWithSpace(confirmButton);
            card.appendWithSpace(actions);
            overlay.appendWithSpace(card);
            document.body.appendWithSpace(overlay);

            const finish = (value) => {
                Dialog.#activeCancel = null;
                document.removeEventListener('keydown', onKeydown);
                overlay.remove();
                resolve(value);
            };

            Dialog.#activeCancel = () => finish(null);

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    finish(null);
                }
            };

            input.addEventListener('input', () => {
                confirmButton.disabled = input.value.trim() === '';
            });

            cancelButton.addEventListener('click', () => finish(null));
            confirmButton.addEventListener('click', () => {
                const value = input.value.trim();
                if (value !== '') {
                    finish(value);
                }
            });
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    finish(null);
                }
            });
            document.addEventListener('keydown', onKeydown);

            input.focus();
        });
    }
}
