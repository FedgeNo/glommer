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
