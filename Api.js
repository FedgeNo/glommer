import { ClientConfig } from '/ClientConfig.js';
import { Toast } from '/Toast.js';
import { csrf_headers } from '/utils.js';

export class Api {
    /**
     * Make a POST request to the Glommer API.
     *
     * @param {string} path – API path, e.g. '/api/create-post'
     * @param {*} [payload] – JSON‑serialisable request body
     * @param {{ signal?: AbortSignal }} [options]
     * @returns {Promise<object|null>} – the parsed JSON response, or null on error
     */
    static async post(path, payload, { signal } = {}) {
        let response;
        try {
            response = await fetch(ClientConfig.siteURL() + path, {
                method: 'POST',
                headers: csrf_headers(
                    payload !== undefined
                        ? { 'Content-Type': 'application/json' }
                        : undefined
                ),
                body: payload === undefined ? undefined : JSON.stringify(payload),
                signal,
            });
        } catch (error) {
            if (error.name !== 'AbortError') {
                Toast.show('Network error. Please check your connection and try again.');
            }
            return null;
        }

        let data = null;
        try {
            data = await response.json();
        } catch (_) {
            // Not JSON – handled below
        }

        if (!response.ok || data === null) {
            Toast.show((data && data.error) || 'Something went wrong. Please try again.');
            return null;
        }

        return data.response;
    }
}
