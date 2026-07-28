// UsernameValidation.js
import { ClientConfig } from '/ClientConfig.js';
import { csrf_headers } from '/utils.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class UsernameValidation {
    static init() {
        const sanitize = (input) => {
            input.value = input.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 32);
        };

        document.addEventListener('input', (event) => {
            const input = event.target.closest('.SignupForm [name="username"]');
            if (!input) return;
            sanitize(input);
            UsernameValidation.#checkAvailability(input);
        });

        document.addEventListener('change', (event) => {
            const input = event.target.closest('.SignupForm [name="username"]');
            if (!input) return;
            sanitize(input);
        });
    }

    static #checkAvailability(input) {
        const status = input.closest('.SignupForm').querySelector('.UsernameAvailability');
        if (!status) return;
        clearTimeout(input.dataset.debounceId);
        const requested = input.value;
        if (requested === '') {
            status.textContent = '';
            status.classList.remove('Error', 'muted');
            return;
        }
        const debounce_id = setTimeout(async () => {
            input.availabilityAbortController?.abort();
            const controller = new AbortController();
            input.availabilityAbortController = controller;
            let data;
            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/username-available', {
                    method: 'POST',
                    headers: csrf_headers({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ username: requested }),
                    signal: controller.signal,
                });
                if (!response.ok) return;
                data = await response.json();
            } catch (error) {
                return;
            }
            if (input.value !== requested) return;
            status.classList.toggle('Error', !data.response.available);
            status.classList.toggle('muted', data.response.available);
            status.textContent = data.response.available
                ? `${data.response.username} is available.`
                : `${data.response.username} is already taken.`;
        }, 300);
        input.dataset.debounceId = debounce_id;
    }
}

ReadyHandler.add(UsernameValidation.init);
