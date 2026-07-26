const server_time_offset = typeof window.serverTime === 'number' ? window.serverTime - Date.now() : 0;

export function corrected_now() {
    return Date.now() + server_time_offset;
}

export function parse_server_date(date_string) {
    const normalized = date_string.includes('T') ? date_string : date_string.replace(' ', 'T');
    return new Date(/Z|[+-]\d\d:\d\d$/.test(normalized) ? normalized : normalized + 'Z');
}

export function format_relative_time(date_string) {
    const target = parse_server_date(date_string);
    const diff_seconds = Math.round((corrected_now() - target.getTime()) / 1000);

    if (diff_seconds < 60) return 'just now';

    const diff_minutes = Math.round(diff_seconds / 60);
    if (diff_minutes < 60) return diff_minutes + 'm ago';

    const diff_hours = Math.round(diff_minutes / 60);
    if (diff_hours < 24) return diff_hours + 'h ago';

    const diff_days = Math.round(diff_hours / 24);
    if (diff_days < 7) return diff_days + 'd ago';

    return target.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export function csrf_headers(extra) {
    return Object.assign({ 'X-CSRF-Token': window.CSRFToken }, extra || {});
}

export function list_item(child) {
    const item = document.createElement('li');
    item.appendChild(child);
    return item;
}
