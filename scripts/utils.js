import { Cookie } from '/scripts/Cookie.js';

// ----------------------------------------------------------------
// Date parsing
// ----------------------------------------------------------------
export function parse_server_date(dateString) {
    const normalized = dateString.includes('T')
        ? dateString
        : dateString.replace(' ', 'T');
    return new Date(
        /Z|[+-]\d\d:\d\d$/.test(normalized)
            ? normalized
            : normalized + 'Z'
    );
}

// ----------------------------------------------------------------
// CSRF headers
// ----------------------------------------------------------------
export function csrf_headers(extra = {}) {
    const token = Cookie.get('CSRF-TOKEN');
    return Object.assign({ 'X-CSRF-Token': token || '' }, extra);
}

// ----------------------------------------------------------------
// DOM helpers
// ----------------------------------------------------------------
export function list_item(child) {
    const item = document.createElement('li');
    item.appendWithSpace(child);
    return item;
}

/**
 * Keeps the browser chrome's own colour in step with the active theme -
 * read from the live --paper token, so every theme (and Match System) is
 * covered without a second list of colours anywhere.
 */
export function sync_theme_color() {
    const paper = getComputedStyle(document.documentElement).getPropertyValue('--paper').trim();

    if (paper === '') return;

    let meta = document.querySelector('meta[name="theme-color"]');

    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'theme-color';
        document.head.appendChild(meta);
    }

    meta.content = paper;
}
