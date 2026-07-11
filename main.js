function csrf_headers(extra) {
    return Object.assign({ 'X-CSRF-Token': window.CSRFToken }, extra || {});
}

/**
 * Mirrors Avatar.php: an <img> when the user has one, otherwise a pure
 * CSS/text fallback circle in a color derived from their userId, showing
 * the first letter of their name.
 */
function avatar_element(has_image, image_url, name, user_id) {
    if (has_image && image_url) {
        const image = document.createElement('img');
        image.className = 'Avatar';
        image.src = image_url;
        image.alt = (name || '') + '\'s avatar';
        return image;
    }

    const fallback = document.createElement('div');
    fallback.className = 'Avatar AvatarInitial';
    fallback.setAttribute('aria-hidden', 'true');
    fallback.style.setProperty('--avatar-hue', ((Number(user_id) * 137) % 360) + 'deg');

    // Array.from splits on code points, not UTF-16 units - .charAt(0) on a
    // name starting with an emoji or other astral character would produce a
    // lone surrogate half instead of the character.
    const first_char = Array.from(name || '')[0];
    fallback.textContent = first_char ? first_char.toUpperCase() : '?';

    return fallback;
}

/**
 * Mirrors User::header(): the avatar + display name + username block used
 * wherever a message, post, or similar item needs to show who it's from -
 * one clickable link to their profile.
 */
function user_header_element(username, display_name, has_image, image_url, user_id) {
    const name = display_name || username;

    const header = document.createElement('a');
    header.href = window.siteURL + '/users/' + username + '/';
    header.className = 'd-flex align-items-center gap-3';

    header.appendChild(avatar_element(has_image, image_url, name, user_id));

    const info = document.createElement('div');

    const name_line = document.createElement('div');
    name_line.className = 'fw-semibold';
    name_line.textContent = name;
    info.appendChild(name_line);

    const username_line = document.createElement('div');
    username_line.className = 'Muted text-sm';
    username_line.textContent = '@' + username;
    info.appendChild(username_line);

    header.appendChild(info);

    return header;
}

/**
 * The viewer's own clock can't be trusted to be in sync with the server
 * (especially once this runs on machines we don't control), so relative
 * timestamps are computed against the server's clock, corrected for
 * whatever drift exists between it and the viewer's clock at page load.
 */
const server_time_offset = typeof window.serverTime === 'number' ? window.serverTime - Date.now() : 0;

function corrected_now() {
    return Date.now() + server_time_offset;
}

/**
 * Parses a server-sent date string as UTC. The server's clock is UTC
 * everywhere (PHP and MySQL both), but its MySQL-style "Y-m-d H:i:s"
 * strings carry no timezone marker, and JavaScript parses a bare
 * date-time as *local* time - which would shift every timestamp by the
 * viewer's UTC offset. Appending Z pins the interpretation to UTC.
 * Strings that already carry a marker (Z or +hh:mm) parse as-is.
 */
function parse_server_date(date_string) {
    const normalized = date_string.includes('T') ? date_string : date_string.replace(' ', 'T');

    return new Date(/Z|[+-]\d\d:\d\d$/.test(normalized) ? normalized : normalized + 'Z');
}

/**
 * Formats a MySQL-style "Y-m-d H:i:s" (or ISO) date string as a relative
 * time ("5m ago"), falling back to an absolute date once it's a week old,
 * since "23d ago" is less useful than the actual date at that point.
 */
function format_relative_time(date_string) {
    const target = parse_server_date(date_string);
    const diff_seconds = Math.round((corrected_now() - target.getTime()) / 1000);

    if (diff_seconds < 60) {
        return 'just now';
    }

    const diff_minutes = Math.round(diff_seconds / 60);

    if (diff_minutes < 60) {
        return diff_minutes + 'm ago';
    }

    const diff_hours = Math.round(diff_minutes / 60);

    if (diff_hours < 24) {
        return diff_hours + 'h ago';
    }

    const diff_days = Math.round(diff_hours / 24);

    if (diff_days < 7) {
        return diff_days + 'd ago';
    }

    return target.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function refresh_relative_times(root) {
    (root || document).querySelectorAll('.RelativeTime').forEach((time_element) => {
        time_element.textContent = format_relative_time(time_element.getAttribute('datetime'));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    refresh_relative_times();
    setInterval(() => refresh_relative_times(), 60000);
});

/**
 * Merges any <p>/<br> block breaks that fall inside a $$...$$ or \[...\] run
 * back into plain text. Quill turns Enter into a new <p> element (and
 * Shift+Enter into a <br>) rather than a plain "\n" character, which splits
 * a multi-line formula's source across separate DOM text nodes - auto-render
 * only matches a delimiter pair within a single text node, so a matrix or
 * \begin{align} block typed across multiple lines would otherwise silently
 * fail to render. This restores the source to what it would look like coming
 * from a plain-text input, without touching line breaks outside a formula.
 */
function unwrap_math_line_breaks(html) {
    const flattened = html.replace(/<\/p>\s*<p>/gi, '<br>');

    const strip_breaks = (full_match, open_delim, inner, close_delim) =>
        open_delim + inner.replace(/<br\s*\/?>/gi, '') + close_delim;

    return flattened
        .replace(/(\$\$)([\s\S]*?)(\$\$)/g, strip_breaks)
        .replace(/(\\\[)([\s\S]*?)(\\\])/g, strip_breaks);
}

/**
 * Renders LaTeX source found within an element via KaTeX's auto-render
 * extension, if it's loaded on this page (only pages that show post content
 * load it - see Page::create()'s needsMath flag). Display math must use
 * $$...$$ or \[...\]; inline math must use \(...\) - bare single $...$ is
 * deliberately not treated as math, since it's too likely to collide with a
 * literal dollar amount in ordinary post text.
 */
function render_math(element) {
    if (typeof renderMathInElement !== 'function') {
        return;
    }

    element.querySelectorAll('.PostBody').forEach((post_body) => {
        if (post_body.innerHTML.includes('$$') || post_body.innerHTML.includes('\\[')) {
            post_body.innerHTML = unwrap_math_line_breaks(post_body.innerHTML);
        }
    });

    renderMathInElement(element, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '\\[', right: '\\]', display: true },
            { left: '\\(', right: '\\)', display: false },
        ],
        throwOnError: false,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    render_math(document.body);
});

/**
 * HTML-escapes text for safe interpolation into a show_toast() call - needed
 * for anything containing user-controlled content (a notification's actor
 * name, for instance), since show_toast() renders its argument as markup.
 */
function escape_html(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Like escape_html(), but also safe inside a double-quoted HTML attribute
 * value - the textContent/innerHTML round-trip escapes &, <, > but not ",
 * confirmed empirically (a real browser's innerHTML leaves a literal " in
 * place), so escape_html() alone isn't enough for a href="..." string.
 */
function escape_attribute(text) {
    return escape_html(text).replace(/"/g, '&quot;');
}

/**
 * Shows a transient, non-blocking error notification in the bottom-right
 * corner, in place of window.alert() - which is jarring and blocks the whole
 * page. Auto-dismisses after a few seconds, or immediately via its close
 * button (handled by the delegated .ToastCloseButton click listener below).
 *
 * `html` is rendered as markup, not escaped text - every current caller only
 * ever passes a static string written by our own server code (JSONResponse
 * error messages, none of which interpolate user input), never raw user
 * input, so this is safe. A future caller that wants to show user-controlled
 * text would need to escape it itself before calling this (see escape_html
 * above).
 */
function show_toast(html) {
    let container = document.querySelector('.ToastContainer');

    if (!container) {
        container = document.createElement('div');
        container.className = 'ToastContainer';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'Toast';
    toast.setAttribute('role', 'alert');

    const text = document.createElement('div');
    text.className = 'ToastMessage';
    text.innerHTML = html;
    toast.appendChild(text);

    const close_button = document.createElement('button');
    close_button.type = 'button';
    close_button.className = 'ToastCloseButton';
    close_button.setAttribute('aria-label', 'Dismiss');
    close_button.textContent = '×';
    toast.appendChild(close_button);

    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('Active');
    });

    setTimeout(() => dismiss_toast(toast), 6000);
}

function dismiss_toast(toast) {
    if (!toast.classList.contains('Active')) {
        return;
    }

    toast.classList.remove('Active');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });

    // Under prefers-reduced-motion the transition is disabled, so
    // transitionend never fires - without this the invisible toast would sit
    // in the DOM forever. Removing an already-removed node is a no-op, so
    // both paths firing is fine.
    setTimeout(() => toast.remove(), 300);
}

document.addEventListener('click', (event) => {
    const close_button = event.target.closest('.ToastCloseButton');

    if (!close_button) {
        return;
    }

    dismiss_toast(close_button.closest('.Toast'));
});

/**
 * Shows a confirmation dialog we control, in place of window.confirm() -
 * browsers let a user tick "prevent this page from creating additional
 * dialogs" on the native one, which would silently disable it (and every
 * confirmation gating a destructive action) for the rest of the page's
 * lifetime with no way for us to detect or recover from it. Resolves true if
 * the user confirms, false if they cancel (including via the overlay,
 * Escape, or navigating away).
 */
function show_confirm(message) {
    document.querySelector('.ConfirmDialogOverlay') ?.remove();

    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'ConfirmDialogOverlay';

        const card = document.createElement('div');
        card.className = 'ConfirmDialogCard Card';

        const text = document.createElement('div');
        text.className = 'ConfirmDialogMessage';
        text.textContent = message;
        card.appendChild(text);

        const actions = document.createElement('div');
        actions.className = 'ConfirmDialogActions d-flex gap-2';

        const cancel_button = document.createElement('button');
        cancel_button.type = 'button';
        cancel_button.className = 'Btn ConfirmDialogCancelButton';
        cancel_button.textContent = 'Cancel';

        const confirm_button = document.createElement('button');
        confirm_button.type = 'button';
        confirm_button.className = 'Btn ConfirmDialogConfirmButton';
        confirm_button.textContent = 'OK';

        actions.appendChild(cancel_button);
        actions.appendChild(confirm_button);
        card.appendChild(actions);
        overlay.appendChild(card);
        document.body.appendChild(overlay);

        const finish = (confirmed) => {
            document.removeEventListener('keydown', on_keydown);
            overlay.remove();
            resolve(confirmed);
        };

        const on_keydown = (event) => {
            if (event.key === 'Escape') {
                finish(false);
            }
        };

        cancel_button.addEventListener('click', () => finish(false));
        confirm_button.addEventListener('click', () => finish(true));
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                finish(false);
            }
        });
        document.addEventListener('keydown', on_keydown);

        cancel_button.focus();
    });
}

/**
 * POSTs JSON to an API endpoint and returns the parsed response value, or null
 * on any failure (after telling the user what went wrong). Callers only need
 * to handle the success path.
 */
async function api_post(path, payload) {
    let response;

    try {
        response = await fetch(window.siteURL + path, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: payload === undefined ? undefined : JSON.stringify(payload),
        });
    } catch (error) {
        show_toast('Network error. Please check your connection and try again.');
        return null;
    }

    let data = null;

    try {
        data = await response.json();
    } catch (error) {
        // fall through - handled below via response.ok
    }

    if (!response.ok || data === null) {
        show_toast((data && data.error) || 'Something went wrong. Please try again.');
        return null;
    }

    return data.response;
}

document.addEventListener('DOMContentLoaded', () => {
    const nav = document.querySelector('.MainNavigation');
    const page_title = document.querySelector('.PageTitle');

    if (nav) {
        const update_layout = () => {
            const nav_height = nav.offsetHeight;

            if (page_title) {
                page_title.style.top = nav_height + 'px';
            }

            const title_height = page_title ? page_title.offsetHeight : 0;
            document.body.style.paddingTop = (nav_height + title_height) + 'px';
        };

        update_layout();
        new ResizeObserver(update_layout).observe(nav);

        if (page_title) {
            new ResizeObserver(update_layout).observe(page_title);
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const composer = document.querySelector('.MessageComposer');
    const messages_page = document.querySelector('.MessagesPage');

    if (!composer) {
        return;
    }

    if (messages_page) {
        const update_padding = () => {
            messages_page.style.paddingBottom = (composer.offsetHeight + 32) + 'px';
        };

        update_padding();
        new ResizeObserver(update_padding).observe(composer);
    }

    // The initial scroll-to-bottom for a conversation happens later, on
    // 'load' rather than here - see the comment above the
    // message_history_ready declaration below for why.
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    const textarea = event.target.closest('.MessageComposer textarea');

    if (!textarea) {
        return;
    }

    event.preventDefault();
    textarea.closest('form').requestSubmit();
});

document.addEventListener('input', (event) => {
    const input = event.target.closest('.UserSearchInput');

    if (!input) {
        return;
    }

    clearTimeout(input.dataset.debounceId);

    const debounce_id = setTimeout(async () => {
        const query = input.value.trim();
        const results = input.closest('.UserSearch').querySelector('.UserSearchResults');

        results.innerHTML = '';

        const response = await fetch(window.siteURL + '/api/search-users?q=' + encodeURIComponent(query));
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        data.response.users.forEach((user_data) => {
            const user = OtherUser.fromData(user_data);
            results.appendChild(user.toElement());
        });
    }, 300);

    input.dataset.debounceId = debounce_id;
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.FriendRequestButton');

    if (!button) {
        return;
    }

    const user_id = button.dataset.userId;

    button.disabled = true;

    try {
        const result = await api_post('/api/friend-request', { userId: user_id });

        if (result === null) {
            return;
        }

        button.dataset.sent = result.sent ? '1' : '0';
        button.textContent = result.sent ? 'Cancel' : 'Add Friend';
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.BlockUserButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Block this user? This will remove any existing friendship.')) {
        return;
    }

    const user_id = button.dataset.userId;

    button.disabled = true;

    try {
        const result = await api_post('/api/block', { userId: user_id });

        if (result === null) {
            return;
        }

        const user = button.closest('.OtherUser');

        if (user) {
            user.remove();
        }
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.RemoveFriendButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Remove this friend?')) {
        return;
    }

    const user_id = button.dataset.userId;

    button.disabled = true;

    try {
        const result = await api_post('/api/remove-friend', { userId: user_id });

        if (result === null) {
            return;
        }

        const user = button.closest('.OtherUser');

        if (user) {
            user.remove();
        }
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.ModButton');

    if (!button) {
        return;
    }

    const user_id = button.dataset.userId;
    const is_mod = button.dataset.isMod === '1';

    button.disabled = true;

    try {
        const result = await api_post('/api/set-mod', { userId: user_id, isMod: !is_mod });

        if (result === null) {
            return;
        }

        button.dataset.isMod = result.isMod ? '1' : '0';
        button.textContent = result.isMod ? 'Remove Mod' : 'Make Mod';
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.UnblockUserButton');

    if (!button) {
        return;
    }

    const user_id = button.dataset.userId;
    const username = button.closest('.OtherUser').dataset.username;

    button.disabled = true;

    const result = await api_post('/api/unblock', { userId: user_id });

    if (result === null) {
        button.disabled = false;
        return;
    }

    const friend_button = document.createElement('button');
    friend_button.type = 'button';
    friend_button.className = 'Btn FriendRequestButton';
    friend_button.dataset.userId = user_id;
    friend_button.dataset.sent = '0';
    friend_button.textContent = 'Add Friend';

    const message_link = document.createElement('a');
    message_link.className = 'Btn';
    message_link.href = window.siteURL + '/messages/' + username;
    message_link.textContent = 'Message';

    const block_button = document.createElement('button');
    block_button.type = 'button';
    block_button.className = 'Btn BlockUserButton';
    block_button.dataset.userId = user_id;
    block_button.textContent = 'Block';

    const actions = document.createElement('div');
    actions.className = 'd-flex flex-column gap-2 ms-auto';
    actions.appendChild(friend_button);
    actions.appendChild(message_link);
    actions.appendChild(block_button);

    button.replaceWith(actions);
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.LikeButton');

    if (!button) {
        return;
    }

    const item_id = button.dataset.itemId;

    button.disabled = true;

    try {
        const result = await api_post('/api/like', { itemId: item_id });

        if (result === null) {
            return;
        }

        button.dataset.liked = result.liked ? '1' : '0';
        button.textContent = (result.liked ? 'Unlike' : 'Like') + ' (' + result.count + ')';
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.DeleteButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Delete this post?')) {
        return;
    }

    const item_id = button.dataset.itemId;

    button.disabled = true;

    const result = await api_post('/api/delete', { itemId: item_id });

    if (result === null) {
        button.disabled = false;
        return;
    }

    if (button.dataset.standalone === '1') {
        window.location.href = window.siteURL + '/';
        return;
    }

    const thread = button.closest('.Thread');

    if (thread) {
        thread.remove();
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.AcceptFriendButton');

    if (!button) {
        return;
    }

    const friendship_id = button.dataset.friendshipId;

    button.disabled = true;

    const result = await api_post('/api/accept-friend', { friendshipId: friendship_id });

    if (result === null) {
        button.disabled = false;
        return;
    }

    // They're a friend now - swap the Accept/Deny pair for the Remove Friend
    // button an accepted friendship's card carries, right below Message.
    const card = button.closest('.OtherUser');

    if (card) {
        const user_id = card.querySelector('.BlockUserButton')?.dataset.userId;
        const message_link = card.querySelector('a.Btn[href*="/messages/"]');

        card.querySelectorAll('.AcceptFriendButton, .DenyFriendButton').forEach((response_button) => response_button.remove());

        if (user_id && message_link) {
            const remove_friend_button = document.createElement('button');
            remove_friend_button.type = 'button';
            remove_friend_button.className = 'Btn RemoveFriendButton';
            remove_friend_button.dataset.userId = user_id;
            remove_friend_button.textContent = 'Remove Friend';
            message_link.after(remove_friend_button);
        }
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.DenyFriendButton');

    if (!button) {
        return;
    }

    const friendship_id = button.dataset.friendshipId;

    button.disabled = true;

    const result = await api_post('/api/deny-friend', { friendshipId: friendship_id });

    if (result === null) {
        button.disabled = false;
        return;
    }

    const request = button.closest('.OtherUser');

    if (request) {
        request.remove();
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.ReportButton');

    if (!button) {
        return;
    }

    const reason = window.prompt('Why are you reporting this?');

    if (reason === null) {
        return;
    }

    const target_type = button.dataset.targetType;
    const target_id = button.dataset.targetId;

    button.disabled = true;

    const result = await api_post('/api/report', { targetType: target_type, targetId: target_id, reason });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.textContent = 'Reported';
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.BanButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Ban this user? This hides all their content and blocks their login.')) {
        return;
    }

    const user_id = button.dataset.userId;

    button.disabled = true;

    const result = await api_post('/api/ban', { userId: user_id });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.textContent = 'Banned';
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.DismissReportButton');

    if (!button) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/dismiss-report', { reportId: button.dataset.reportId });

    if (result === null) {
        button.disabled = false;
        return;
    }

    const card = button.closest('.ReportCard');

    if (card) {
        card.remove();
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.DeleteReportedContentButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Delete this content permanently? Deleting a post also removes all its replies.')) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/delete-reported-content', { reportId: button.dataset.reportId });

    if (result === null) {
        button.disabled = false;
        return;
    }

    const card = button.closest('.ReportCard');

    if (card) {
        card.remove();
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.ResendVerificationButton');

    if (!button) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/resend-verification');

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.textContent = 'Sent!';
});

// Running slideshows, keyed by their carousel element (interval id per carousel).
const carousel_slideshows = new WeakMap();
const CAROUSEL_SLIDESHOW_INTERVAL = 3500;

// Promotes a slide's deferred media (data-src/data-poster left by the server or
// post.js) to real src/poster so the browser fetches it. A no-op once loaded.
function carousel_load_slide(slide) {
    if (!slide) {
        return;
    }

    slide.querySelectorAll('[data-src]').forEach((media) => {
        if (media.dataset.poster) {
            media.poster = media.dataset.poster;
            delete media.dataset.poster;
        }

        media.src = media.dataset.src;
        delete media.dataset.src;
    });
}

function carousel_advance(carousel, direction) {
    const slides = Array.from(carousel.querySelectorAll('.CarouselSlide'));
    const current_index = slides.findIndex((slide) => slide.classList.contains('Active'));
    const next_index = (current_index + direction + slides.length) % slides.length;

    slides[current_index].classList.remove('Active');
    slides[next_index].classList.add('Active');

    // A video playing on the slide we just left would keep going (and stay
    // audible) while hidden - pause every video in the carousel so nothing
    // plays off-screen once the displayed item changes.
    carousel.querySelectorAll('video').forEach((video) => video.pause());

    // Load the slide now on screen (covers a backward wrap onto one we hadn't
    // reached yet), and on each forward step pull in one more slide beyond the
    // ones already loaded, so a big gallery loads gradually instead of at once.
    carousel_load_slide(slides[next_index]);

    if (direction > 0) {
        const next_deferred = carousel.querySelector('.CarouselSlide [data-src]');

        if (next_deferred) {
            carousel_load_slide(next_deferred.closest('.CarouselSlide'));
        }
    }

    const counter = carousel.querySelector('.CarouselCounter');

    if (counter) {
        counter.textContent = (next_index + 1) + ' / ' + slides.length;
    }
}

function start_carousel_slideshow(carousel) {
    if (carousel_slideshows.has(carousel)) {
        return;
    }

    const interval_id = setInterval(() => carousel_advance(carousel, 1), CAROUSEL_SLIDESHOW_INTERVAL);
    carousel_slideshows.set(carousel, interval_id);

    const toggle = carousel.querySelector('.CarouselSlideshow');

    if (toggle) {
        toggle.textContent = 'Stop Slideshow';
    }
}

function stop_carousel_slideshow(carousel) {
    if (!carousel_slideshows.has(carousel)) {
        return;
    }

    clearInterval(carousel_slideshows.get(carousel));
    carousel_slideshows.delete(carousel);

    const toggle = carousel.querySelector('.CarouselSlideshow');

    if (toggle) {
        toggle.textContent = 'Slideshow';
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.CarouselPrev, .CarouselNext');

    if (!button) {
        return;
    }

    const carousel = button.closest('.Carousel');

    // Stepping through by hand stops the slideshow.
    stop_carousel_slideshow(carousel);
    carousel_advance(carousel, button.classList.contains('CarouselNext') ? 1 : -1);
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.CarouselSlideshow');

    if (!toggle) {
        return;
    }

    const carousel = toggle.closest('.Carousel');

    if (carousel_slideshows.has(carousel)) {
        stop_carousel_slideshow(carousel);
    } else {
        start_carousel_slideshow(carousel);
    }
});

// Clicking an image in the carousel stops the slideshow.
document.addEventListener('click', (event) => {
    const image = event.target.closest('.Carousel .ImageItem img');

    if (!image) {
        return;
    }

    stop_carousel_slideshow(image.closest('.Carousel'));
});

// Playing a video in the carousel stops the slideshow. The play event doesn't
// bubble, so this listens in the capture phase to catch it via delegation.
document.addEventListener('play', (event) => {
    const video = event.target.closest('.Carousel video');

    if (!video) {
        return;
    }

    stop_carousel_slideshow(video.closest('.Carousel'));
}, true);

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.AvatarUploader');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: csrf_headers(),
            body: new FormData(form),
        });

        const data = await response.json();

        if (!response.ok) {
            show_toast(data.error || 'Could not upload the image. Please try again.');
            return;
        }

        let avatar = form.parentElement.querySelector('img.Avatar');

        if (!avatar) {
            avatar = document.createElement('img');
            avatar.className = 'Avatar';
            avatar.alt = 'Your avatar';
            form.parentElement.insertBefore(avatar, form.parentElement.firstChild);
        }

        avatar.src = data.response.image + '?t=' + Date.now();

        const fallback = form.parentElement.querySelector('.AvatarInitial');

        if (fallback) {
            fallback.remove();
        }
    } finally {
        submit_button.disabled = false;
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.MessageComposer');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    const body_input = form.querySelector('[name=\'body\']');
    const recipient_id = form.querySelector('[name=\'recipientId\']').value;

    submit_button.disabled = true;

    try {
        const result = await api_post('/api/send-message', { recipientId: recipient_id, body: body_input.value });

        if (result === null) {
            return;
        }

        const list = document.querySelector('.MessageList');
        const placeholder = list.querySelector(':scope > .Muted');

        if (placeholder) {
            placeholder.remove();
        }

        const message = Message.fromData(result);
        const element = message.toElement();
        list.appendChild(element);
        render_math(element);

        body_input.value = '';
        // behavior: 'instant' - see the comment on the initial-load
        // scrollTo() below for why: html has scroll-behavior: smooth, so a
        // plain scrollTo() animates and fires intermediate 'scroll' events
        // with partial scrollY values along the way, which the "near the
        // top -> load older messages" listener can misread as the user
        // having scrolled up if this conversation still has more history to
        // page in (hasMore=true) when a message is sent.
        window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
    } finally {
        submit_button.disabled = false;
    }
});

// A conversation loads with its whole initial history already in the DOM
// (oldest first), so it has to be scrolled to the bottom before the
// "near the top -> load older messages" listener is allowed to act -
// otherwise the page starts at scrollY 0 (the top, by definition "near the
// top") and the very first scroll fires the older-messages loader before
// the initial view has even been positioned, prepending more content and
// leaving the eventual scroll-to-bottom target wrong since the page grew
// out from under it.
let message_history_ready = false;

// The 'load' event, not DOMContentLoaded - external CSS (Bootstrap/Quill/
// KaTeX from a CDN) doesn't block DOMContentLoaded, only 'load', so
// measuring scrollHeight at DOMContentLoaded can catch the page still
// unstyled/short, landing short of the true bottom once that CSS finishes
// applying and the page grows.
//
// behavior: 'instant' matters here beyond just avoiding an unwanted
// animation on every page load - html has scroll-behavior: smooth in
// style.css, and a smooth scrollTo() fires a whole stream of intermediate
// 'scroll' events as it animates toward the target, each with whatever
// partial scrollY the animation has reached so far. Confirmed live with an
// instrumented trace: one of those early, still-near-zero intermediate
// events satisfied the "near the top" guard below before the animation
// ever reached the bottom, firing the older-messages loader mid-scroll.
window.addEventListener('load', () => {
    if (!document.querySelector('.MessageList')) {
        return;
    }

    window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
    message_history_ready = true;

    document.querySelector('.MessageComposer textarea') ?.focus();
});

let loading_older_messages = false;

window.addEventListener('scroll', async () => {
    if (!message_history_ready || window.scrollY > 150 || loading_older_messages) {
        return;
    }

    // A short conversation (its whole initial history barely taller than
    // the viewport) puts "the bottom" and "near the top" in the same 150px
    // band - without this, the scroll-to-bottom above would itself satisfy
    // the "near the top" check and immediately fire the loader anyway.
    // Confirmed live: exactly this happened before this check existed.
    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (near_bottom) {
        return;
    }

    const list = document.querySelector('.MessageList');

    if (!list || list.dataset.hasMore !== '1') {
        return;
    }

    loading_older_messages = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    const height_before_spinner = document.body.scrollHeight;
    list.insertBefore(spinner, list.firstChild);
    window.scrollTo(0, window.scrollY + (document.body.scrollHeight - height_before_spinner));

    try {
        const other_user_id = list.dataset.otherUserId;
        const before_message_id = list.dataset.oldestMessageId;

        const response = await fetch(
            `${window.siteURL}/api/message-history?otherUserId=${other_user_id}&beforeMessageId=${before_message_id}`
        );
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        const { messages, hasMore: has_more } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (messages.length === 0) {
            return;
        }

        const previous_scroll_height = document.body.scrollHeight;

        for (const message_data of messages) {
            const element = Message.fromData(message_data).toElement();
            list.insertBefore(element, spinner);
            render_math(element);
        }

        list.dataset.oldestMessageId = messages[0].messageId;

        window.scrollTo(0, window.scrollY + (document.body.scrollHeight - previous_scroll_height));
    } finally {
        const height_before_cleanup = document.body.scrollHeight;
        spinner.remove();
        window.scrollTo(0, window.scrollY - (height_before_cleanup - document.body.scrollHeight));
        loading_older_messages = false;
    }
});

let loading_older_notifications = false;

window.addEventListener('scroll', async () => {
    const list = document.querySelector('.NotificationList');

    if (!list || list.dataset.hasMore !== '1' || loading_older_notifications) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_notifications = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    list.appendChild(spinner);

    try {
        const before_notification_id = list.dataset.oldestNotificationId;

        const response = await fetch(`${window.siteURL}/api/notification-history?beforeNotificationId=${before_notification_id}`);
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        const { notifications, hasMore: has_more } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (notifications.length === 0) {
            return;
        }

        notifications.forEach((notification_data) => {
            list.insertBefore(Notification.fromData(notification_data).toElement(), spinner);
        });

        list.dataset.oldestNotificationId = notifications[notifications.length - 1].notificationId;
    } finally {
        spinner.remove();
        loading_older_notifications = false;
    }
});

const WS_RECONNECT_DELAY_MS = 3000;

/**
 * Handles one freshly-pushed notification: toasts it, prepends it to the
 * nav dropdown's list (the dropdown only ever shows 5 - drop the bottom one
 * before prepending so a burst of new notifications can't grow it), and -
 * if the full /notifications/ page itself is open - prepends it there too,
 * uncapped, same as the dropdown and the full page both exist in the DOM
 * simultaneously while viewing that page. Lights up every .NotificationDot
 * on the page (normally just the one in the nav) so the user still has a
 * way to notice if they miss the toast (tab not focused, etc).
 */
function handle_incoming_notification(notification_data) {
    const notification = Notification.fromData(notification_data);

    show_toast('<a href="' + escape_attribute(notification.targetURL()) + '">' + escape_html(notification.text()) + '</a>');

    const dropdown_list = document.querySelector('.NotificationDropdown .NotificationList');

    if (dropdown_list) {
        const placeholder = dropdown_list.querySelector(':scope > .Muted');

        if (placeholder) {
            placeholder.remove();
        }

        const existing = dropdown_list.querySelectorAll(':scope > .Notification');

        if (existing.length >= 5) {
            existing[existing.length - 1].remove();
        }

        dropdown_list.insertBefore(notification.toElement(), dropdown_list.firstChild);
    }

    const page_list = Array.from(document.querySelectorAll('.NotificationList')).find((list) => !list.closest('.NotificationDropdown'));

    if (page_list) {
        const page_placeholder = page_list.querySelector(':scope > .Muted');

        if (page_placeholder) {
            page_placeholder.remove();
        }

        page_list.insertBefore(notification.toElement(), page_list.firstChild);
    }

    document.querySelectorAll('.NotificationDot').forEach((dot) => {
        dot.classList.add('Active');
    });
}

/**
 * Opens the persistent WebSocket connection to bin/websocket-server.php
 * that live notifications and (via a 'ws:message' CustomEvent picked up by
 * message.js) live conversation messages both ride on - a separate
 * long-running process from Apache/PHP-FPM, since a normal request can't
 * hold a connection open across requests. Authenticated with a short-lived
 * token fetched fresh on every (re)connect attempt, since the token expires
 * quickly and the daemon doesn't share PHP's session handling. Reconnects
 * on any drop (server restart, network blip) rather than leaving the user
 * silently back on no live updates at all.
 */
async function connect_websocket() {
    let token;

    try {
        const response = await fetch(`${window.siteURL}/api/ws-token`);

        if (!response.ok) {
            throw new Error('token fetch failed');
        }

        ({ token } = (await response.json()).response);
    } catch (error) {
        setTimeout(connect_websocket, WS_RECONNECT_DELAY_MS);
        return;
    }

    const scheme = window.location.protocol === 'https:' ? 'wss' : 'ws';
    const socket = new WebSocket(`${scheme}://${window.location.hostname}:${window.WSPort}/?token=${encodeURIComponent(token)}`);
    let reconnecting = false;

    const reconnect = () => {
        if (reconnecting) {
            return;
        }

        reconnecting = true;
        setTimeout(connect_websocket, WS_RECONNECT_DELAY_MS);
    };

    socket.addEventListener('message', (event) => {
        let data;

        try {
            data = JSON.parse(event.data);
        } catch (error) {
            return;
        }

        if (data.event === 'notification') {
            handle_incoming_notification(data.notification);
        } else if (data.event === 'message') {
            document.dispatchEvent(new CustomEvent('ws:message', { detail: data.message }));
        }
    });

    socket.addEventListener('close', reconnect);
    socket.addEventListener('error', () => socket.close());
}

document.addEventListener('DOMContentLoaded', () => {
    const nav_link = document.querySelector('.NotificationsNavLink');

    if (!nav_link || window.currentUserId === null) {
        return;
    }

    nav_link.addEventListener('mouseenter', async () => {
        const dot = nav_link.querySelector('.NotificationDot');

        if (!dot || !dot.classList.contains('Active')) {
            return;
        }

        dot.classList.remove('Active');
        await api_post('/api/mark-notifications-seen');
    });

    connect_websocket();
});

let loading_older_feed_items = false;

window.addEventListener('scroll', async () => {
    const list = document.querySelector('.FeedList');

    if (!list || list.dataset.hasMore !== '1' || loading_older_feed_items) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_feed_items = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    list.appendChild(spinner);

    try {
        const feed_type = list.dataset.feedType;
        const before_post_id = list.dataset.oldestPostId;

        const response = await fetch(`${window.siteURL}/api/feed-history?feedType=${feed_type}&beforePostId=${before_post_id}`);
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        const { posts, hasMore: has_more } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (posts.length === 0) {
            return;
        }

        posts.forEach((post_data) => {
            const element = Post.fromData(post_data).toElement();
            list.insertBefore(element, spinner);
            render_math(element);
        });

        list.dataset.oldestPostId = posts[posts.length - 1].postId;
    } finally {
        spinner.remove();
        loading_older_feed_items = false;
    }
});

// Friend-page sections (friends, incoming requests, sent requests) are each
// their own infinite scroll, stacked vertically. A single handler advances
// only the topmost section the reader has actually scrolled to the end of, so
// two never load at once - and never loads a section whose end is already
// scrolled off above the viewport (which would grow content off-screen and
// jump the scroll position).
const FRIENDS_DISPLAY_CAP = 5000;
let loading_user_section = false;

window.addEventListener('scroll', async () => {
    if (loading_user_section) {
        return;
    }

    const threshold = 300;

    const target = Array.from(document.querySelectorAll('.PaginatedUserList')).find((list) => {
        if (list.dataset.hasMore !== '1') {
            return false;
        }

        const rect = list.getBoundingClientRect();

        return rect.bottom > 0 && rect.bottom <= window.innerHeight + threshold;
    });

    if (!target) {
        return;
    }

    // The friends list is capped for display; requests aren't.
    if (target.dataset.listType === 'friends' && target.querySelectorAll('.OtherUser').length >= FRIENDS_DISPLAY_CAP) {
        target.dataset.hasMore = '0';
        return;
    }

    loading_user_section = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    target.appendChild(spinner);

    try {
        const list_type = target.dataset.listType;
        const user_id = target.dataset.userId;
        const before = target.dataset.oldestFriendshipId;

        const response = await fetch(`${window.siteURL}/api/friend-list-history?listType=${list_type}&userId=${user_id}&beforeFriendshipId=${before}`);
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        const { items, hasMore: has_more, oldestFriendshipId: oldest_friendship_id } = data.response;

        target.dataset.hasMore = has_more ? '1' : '0';

        if (oldest_friendship_id !== null) {
            target.dataset.oldestFriendshipId = oldest_friendship_id;
        }

        items.forEach((item) => {
            const card = (list_type === 'incoming' ? FriendRequest : OtherUser).fromData(item);
            target.insertBefore(card.toElement(), spinner);
        });

        if (target.dataset.listType === 'friends' && target.querySelectorAll('.OtherUser').length >= FRIENDS_DISPLAY_CAP) {
            target.dataset.hasMore = '0';
        }
    } finally {
        spinner.remove();
        loading_user_section = false;
    }
});

function sync_post_composer_fields(form) {
    const link_input = form.querySelector('[name=\'linkURL\']');
    const file_input = form.querySelector('[name=\'files[]\']');

    if (!link_input || !file_input) {
        return;
    }

    const has_link = link_input.value.trim() !== '';
    const has_files = file_input.files.length > 0;

    file_input.style.display = has_link ? 'none' : '';
    link_input.style.display = has_files ? 'none' : '';
}

['input', 'change'].forEach((event_name) => {
    document.addEventListener(event_name, (event) => {
        const form = event.target.closest('.PostComposer');

        if (!form) {
            return;
        }

        sync_post_composer_fields(form);
    });
});

document.addEventListener('change', (event) => {
    const file_input = event.target.closest('.Composer input[type=\'file\']');

    if (!file_input) {
        return;
    }

    const cancel_button = file_input.closest('.Composer').querySelector('.CancelFileButton');

    cancel_button.style.display = file_input.files.length === 0 ? 'none' : '';
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('.CancelFileButton');

    if (!button) {
        return;
    }

    const file_input = button.closest('.Composer').querySelector('input[type=\'file\']');

    file_input.value = '';
    file_input.dispatchEvent(new Event('change', { bubbles: true }));
});

let active_quill = null;

document.addEventListener('DOMContentLoaded', () => {
    const editor_container = document.querySelector('#editor');

    if (!editor_container) {
        return;
    }

    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: editor_container.dataset.placeholder,
    });

    active_quill = quill;

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.Composer');

        if (!form || !form.contains(editor_container)) {
            return;
        }

        event.preventDefault();

        const description_input = form.querySelector('#description-input');
        description_input.value = quill.root.innerHTML;

        const submit_button = form.querySelector('button[type=\'submit\']');
        const progress_bar = form.querySelector('.ProgressBar');

        submit_button.disabled = true;
        progress_bar.value = 0;
        progress_bar.classList.add('Active');

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (progress_event) => {
            if (progress_event.lengthComputable) {
                progress_bar.max = progress_event.total;
                progress_bar.value = progress_event.loaded;
            }
        });

        xhr.addEventListener('loadend', () => {
            submit_button.disabled = false;
            progress_bar.classList.remove('Active');
            progress_bar.value = 0;

            if (xhr.status < 200 || xhr.status >= 300) {
                let error_message = 'Could not submit the post. Please try again.';

                try {
                    error_message = JSON.parse(xhr.responseText).error || error_message;
                } catch (error) {
                    // non-JSON response - keep the generic message
                }

                show_toast(error_message);
                return;
            }

            const data = JSON.parse(xhr.responseText);

            form.reset();
            quill.setText('');

            const submitted_link_image_preview = form.querySelector('.LinkImagePreview');

            if (submitted_link_image_preview) {
                submitted_link_image_preview.style.display = 'none';
                submitted_link_image_preview.querySelector('.LinkImagePreviewThumb').src = '';
            }

            const submitted_link_url_input = form.querySelector('[name=\'linkURL\']');

            if (submitted_link_url_input) {
                delete submitted_link_url_input.dataset.lastFetchedUrl;
            }

            if (data.response.processing) {
                const existing_notice = form.querySelector('.ProcessingNotice');

                if (existing_notice) {
                    existing_notice.remove();
                }

                const notice = document.createElement('p');
                notice.className = 'ProcessingNotice Muted text-sm';
                notice.textContent = 'Your media files are processing and you will be notified when they\'re ready to view. It\'s safe to leave this page.';
                form.appendChild(notice);

                return;
            }

            const post = Post.fromData(data.response);
            const reply_list = form.classList.contains('ReplyComposer') ? document.querySelector('.ReplyList') : null;
            const element = post.toElement();

            if (reply_list) {
                if (!document.querySelector('.RepliesHeading')) {
                    const heading = document.createElement('h2');
                    heading.className = 'RepliesHeading fw-bold text-lg';
                    heading.textContent = 'Replies';
                    reply_list.insertAdjacentElement('beforebegin', heading);
                }

                reply_list.insertBefore(element, reply_list.firstChild);
            } else {
                form.insertAdjacentElement('afterend', element);
            }

            render_math(element);
        });

        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-CSRF-Token', window.CSRFToken);
        xhr.send(new FormData(form));
    });
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.ChangePasswordForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const existing_error = form.querySelector('.Error');

    if (existing_error) {
        existing_error.remove();
    }

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const response = await fetch(window.siteURL + '/api/change-password', {
        method: 'POST',
        headers: csrf_headers({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
            currentPassword: form.querySelector('[name=\'currentPassword\']').value,
            newPassword: form.querySelector('[name=\'newPassword\']').value,
            confirmPassword: form.querySelector('[name=\'confirmPassword\']').value,
        }),
    });

    const data = await response.json();

    submit_button.disabled = false;

    if (!response.ok) {
        const error = document.createElement('p');
        error.className = 'Error';
        error.textContent = data.error;
        form.insertBefore(error, submit_button);
        return;
    }

    form.reset();
    submit_button.textContent = 'Changed!';
});

document.addEventListener('change', (event) => {
    const username_input = event.target.closest('.SignupForm [name=\'username\']');

    if (!username_input) {
        return;
    }

    username_input.value = username_input.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 32);
});

document.addEventListener('change', async (event) => {
    const select = event.target.closest('.ThemeSelect');

    if (!select) {
        return;
    }

    const theme = select.value;

    if (theme === 'system') {
        delete document.documentElement.dataset.theme;
    } else {
        document.documentElement.dataset.theme = theme;
    }

    await api_post('/api/update-theme', { theme });
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.EmojiTriggerButton');

    if (trigger) {
        const panel = trigger.closest('.EmojiPickerButton').querySelector('.EmojiPickerPanel');
        const was_active = panel.classList.contains('Active');

        document.querySelectorAll('.EmojiPickerPanel.Active').forEach((open_panel) => {
            open_panel.classList.remove('Active');
        });

        if (!was_active) {
            panel.classList.add('Active');
        }

        return;
    }

    if (event.target.closest('.EmojiPickerPanel')) {
        return;
    }

    document.querySelectorAll('.EmojiPickerPanel.Active').forEach((panel) => {
        panel.classList.remove('Active');
    });
});

document.addEventListener('emoji-click', (event) => {
    const panel = event.target.closest('.EmojiPickerPanel');

    if (!panel) {
        return;
    }

    const emoji = event.detail.unicode;
    const form = panel.closest('form');

    if (form.querySelector('#editor') && active_quill) {
        const selection = active_quill.getSelection(true);
        active_quill.insertText(selection.index, emoji, 'user');
        active_quill.setSelection(selection.index + emoji.length, 0, 'user');
        return;
    }

    const textarea = form.querySelector('textarea');

    if (!textarea) {
        return;
    }

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const value = textarea.value;

    textarea.value = value.slice(0, start) + emoji + value.slice(end);
    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
    textarea.focus();
});

document.addEventListener('skin-tone-change', async (event) => {
    if (!event.target.closest('.EmojiPickerPanel')) {
        return;
    }

    await api_post('/api/update-skin-tone', { skinTone: String(event.detail.skinTone) });
});

function show_link_image_preview(form, image) {
    const preview = form.querySelector('.LinkImagePreview');

    if (!preview) {
        return;
    }

    preview.querySelector('.LinkImagePreviewThumb').src = image.thumbnailURL;
    preview.querySelector('[name=\'linkImageSeed\']').value = image.seed;
    preview.style.display = '';
}

async function discard_staged_link_image(form) {
    const preview = form.querySelector('.LinkImagePreview');

    if (!preview) {
        return;
    }

    const seed_input = preview.querySelector('[name=\'linkImageSeed\']');
    const seed = seed_input.value;

    seed_input.value = '';
    preview.style.display = 'none';
    preview.querySelector('.LinkImagePreviewThumb').src = '';

    if (seed) {
        await api_post('/api/discard-link-image', { seed });
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.RemoveLinkImageButton');

    if (!button) {
        return;
    }

    discard_staged_link_image(button.closest('.Composer'));
});

document.addEventListener('input', (event) => {
    const input = event.target.closest('.Composer [name=\'linkURL\']');

    if (!input) {
        return;
    }

    // Fetched on input so the link previews as it's entered (people paste a link
    // and submit straight away). A paste is a complete URL, so fetch it
    // immediately; typing is debounced so a burst of keystrokes coalesces into a
    // single request.
    clearTimeout(input.dataset.debounceId);

    const delay = event.inputType === 'insertFromPaste' ? 0 : 500;

    input.dataset.debounceId = setTimeout(async () => {
        const form = input.closest('.Composer');
        const url = input.value.trim();

        if (url === input.dataset.lastFetchedUrl) {
            return;
        }

        input.dataset.lastFetchedUrl = url;

        await discard_staged_link_image(form);

        if (!url) {
            return;
        }

        const preview = await api_post('/api/link-preview', { url });

        if (!preview) {
            return;
        }

        // Only overwrite a field that's still exactly what the previous fetch put
        // there (or empty) - once the user edits an autofilled value by hand,
        // later link changes shouldn't clobber it.
        const title_input = form.querySelector('[name=\'title\']');

        if (preview.title && title_input) {
            const current_title = title_input.value.trim();

            if (current_title === '' || current_title === (title_input.dataset.autofilled ?? '')) {
                title_input.value = preview.title;
                title_input.dataset.autofilled = preview.title;
            }
        }

        if (preview.description && active_quill) {
            const current_description = active_quill.getText().trim();
            const autofilled_description = form.dataset.autofilledDescription ?? '';

            if (current_description === '' || current_description === autofilled_description) {
                active_quill.setText(preview.description);
                form.dataset.autofilledDescription = preview.description.trim();
            }
        }

        if (preview.image) {
            show_link_image_preview(form, preview.image);
        }
    }, delay);
});
