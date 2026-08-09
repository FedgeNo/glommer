import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { csrf_headers } from '/scripts/utils.js';
import { FormErrors } from '/scripts/FormErrors.js';

export class Api {
    /**
     * Make a POST request to the Glommer API.
     *
     * @param {string} path – API path, e.g. '/api/create-post'
     * @param {*} [payload] – JSON‑serialisable request body
     * @param {{ signal?: AbortSignal, form?: Element }} [options] – pass the
     *   form and a refusal that names its fields is written under those fields
     *   instead of thrown at the corner of the screen as a toast
     * @returns {Promise<object|null>} – the parsed JSON response, or null on error
     */
    static async post(path, payload, { signal, form } = {}) {
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
            // A refusal that names the inputs it is about belongs on those
            // inputs. The toast is the fallback for everything else - and for
            // a named field this form does not actually have, which would
            // otherwise be a complaint nobody could see.
            if (form && data?.fields && FormErrors.show(form, data.fields)) {
                return null;
            }

            Toast.show((data && data.error) || 'Something went wrong. Please try again.');
            return null;
        }

        // Whatever the last attempt complained about is answered.
        if (form) FormErrors.clear(form);

        return data.response;
    }
}
