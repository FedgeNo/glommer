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
 * Mirrors User::toDOM(): the full identity card - avatar, name, @username, and
 * joined date, all one link to the profile - wrapped in a .User.Card. Shared by
 * OtherUser (which adds action buttons) and the report card's user target
 * (which shows it plain). Expects {username, displayName, image, userId,
 * createdAt}.
 */
function user_card_element(user) {
    const div = document.createElement('div');
    div.className = 'User Card';

    if (user.username) {
        div.dataset.username = user.username;
    }

    const link = document.createElement('a');
    link.className = 'UserLink';
    link.href = window.siteURL + '/users/' + user.username + '/';

    link.appendChild(avatar_element(Boolean(user.image), user.image, user.displayName || user.username, user.userId));

    const info = document.createElement('div');

    const name_heading = document.createElement('h2');
    name_heading.textContent = user.displayName || user.username;
    info.appendChild(name_heading);

    const username_line = document.createElement('div');
    username_line.className = 'Muted text-sm';
    username_line.textContent = '@' + user.username;
    info.appendChild(username_line);

    if (user.createdAt) {
        const joined = document.createElement('div');
        joined.className = 'Muted text-sm';
        joined.textContent = 'Joined ' + parse_server_date(user.createdAt).toLocaleString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
        info.appendChild(joined);
    }

    link.appendChild(info);
    div.appendChild(link);

    return div;
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

// Subtrees KaTeX auto-render won't read: rendered math, and code where a
// delimiter is literal source. A run may never be coalesced across one, so
// these contribute a barrier character to the logical text (see below).
const MATH_COALESCE_SKIP = 'pre, code, .PostFormula, .katex';

/**
 * Repairs display-math runs ($$...$$ / \[...\]) that Quill's Enter/Shift+Enter
 * split across <p> blocks or <br>s, so each run's full source ends up in a
 * single DOM text node - the only shape KaTeX's auto-render can match. Quill
 * turns Enter into a new <p> and Shift+Enter into a <br> rather than a plain
 * "\n", so a matrix or \begin{align} block typed across lines would otherwise
 * silently fail to render.
 *
 * Surgical: only the nodes a run actually covers are touched (the break
 * elements inside it are removed and its source is merged into one text node);
 * all other structure is left intact. A run already sitting in one text node
 * is a no-op, so this is safe to run repeatedly over the same DOM.
 */
function coalesce_display_math(post_body) {
    display_math_block_groups(post_body).forEach((group) => {
        const segments = math_text_segments(group);
        let logical = '';

        segments.forEach((segment) => {
            segment.start = logical.length;
            logical += segment.text;
        });

        // U+0000 marks content auto-render won't read; [^\u0000] stops a
        // delimiter pair matching across it. One alternation, first-wins
        // left-to-right, mirroring auto-render's own scan order.
        const matches = [...logical.matchAll(/\$\$[^\u0000]*?\$\$|\\\[[^\u0000]*?\\\]/g)];

        // Right-to-left: splitText keeps the leading half in the original
        // node, so offsets held by matches still to be processed stay valid.
        for (let i = matches.length - 1; i >= 0; i--) {
            coalesce_run(segments, logical, matches[i].index, matches[i].index + matches[i][0].length);
        }
    });
}

/**
 * The block groups a run could span: consecutive sibling <p>s merge (an Enter
 * split only ever produces those), every other block stands alone (its own
 * runs can still be <br>-split), each <li> is its own group, and <pre> is
 * dropped since auto-render ignores it anyway.
 */
function display_math_block_groups(post_body) {
    const groups = [];
    let open_p_group = null;

    Array.from(post_body.children).forEach((child) => {
        if (child.tagName === 'P') {
            if (open_p_group === null) {
                open_p_group = [];
                groups.push(open_p_group);
            }
            open_p_group.push(child);
            return;
        }

        open_p_group = null;

        if (child.tagName === 'PRE') {
            return;
        }

        if (child.tagName === 'OL' || child.tagName === 'UL') {
            Array.from(child.children).forEach((li) => groups.push([li]));
            return;
        }

        groups.push([child]);
    });

    return groups;
}

/**
 * A group's logical text as segments: text nodes carry their data, <br>s and
 * block boundaries carry "\n" (whitespace KaTeX ignores, matching what a
 * plain-text input would have held), and skipped subtrees carry the barrier.
 */
function math_text_segments(blocks) {
    const segments = [];

    blocks.forEach((block, index) => {
        if (index > 0) {
            segments.push({ text: '\n' });
        }

        collect_math_segments(block, block, segments);
    });

    return segments;
}

function collect_math_segments(node, block, segments) {
    node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
            segments.push({ text: child.data, node: child, block: block });
        } else if (child.nodeType === Node.ELEMENT_NODE) {
            if (child.tagName === 'BR') {
                segments.push({ text: '\n', node: child, block: block });
            } else if (child.matches(MATH_COALESCE_SKIP)) {
                segments.push({ text: '\u0000' });
            } else {
                collect_math_segments(child, block, segments);
            }
        }
    });
}

/**
 * Collapses one matched run (logical [start, end)) into a single text node,
 * removing the break elements and consumed blocks it spanned. A run already
 * living in one text node is left untouched.
 */
function coalesce_run(segments, logical, start, end) {
    const covered = segments.filter((segment) =>
        segment.node !== undefined
        && segment.start < end
        && segment.start + segment.text.length > start
    );
    const first = covered[0];
    const last = covered[covered.length - 1];

    // A run opens and closes with delimiter characters, so first/last are
    // always text segments. One segment = already a single text node: done.
    // (This is also what makes the whole pass idempotent.)
    if (first === last) {
        return;
    }

    // Trim the boundary text nodes to exactly the run's ends.
    let start_node = first.node;

    if (start > first.start) {
        start_node = start_node.splitText(start - first.start);
    }

    if (end - last.start < last.node.data.length) {
        last.node.splitText(end - last.start);
    }

    // The whole source as one text node, placed where the run began.
    start_node.parentNode.insertBefore(document.createTextNode(logical.slice(start, end)), start_node);

    // start_node, not first.node: when the start was mid-node, first.node is
    // the surviving pre-run head and must stay.
    start_node.remove();
    covered.slice(1).forEach((segment) => segment.node.remove());

    // Blocks the run spanned: intermediates are fully consumed; the last one
    // survives only if the split left content after the closing delimiter.
    let block = first.block.nextElementSibling;

    while (block !== null && block !== last.block) {
        const next = block.nextElementSibling;
        block.remove();
        block = next;
    }

    if (last.block !== first.block && !last.block.hasChildNodes()) {
        last.block.remove();
    }
}

/**
 * Renders LaTeX source found within an element via KaTeX's auto-render
 * extension, if it's loaded on this page (only pages that show post content
 * load it - see Page::create()'s needsMath flag). Every delimiter form is
 * supported so pasted math from other tools just works: $$...$$ and \[...\]
 * for display, \(...\) and single $...$ for inline. Single $...$ can collide
 * with a literal dollar amount ("$5 ... $3"), but that's an accepted tradeoff
 * for recognising the math people actually paste. ($$ is listed before $ so a
 * display pair isn't mis-read as two empty inline ones.)
 */
function render_math(element) {
    // Formula embeds first (independent of the auto-render extension), so a post
    // body's .PostFormula spans render even where typed-delimiter math wouldn't.
    render_formulas(element);

    if (typeof renderMathInElement !== 'function') {
        return;
    }

    element.querySelectorAll('.PostBody').forEach((post_body) => {
        const text = post_body.textContent;

        if (text.includes('$$') || text.includes('\\[')) {
            coalesce_display_math(post_body);
        }
    });

    renderMathInElement(element, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '\\[', right: '\\]', display: true },
            { left: '\\(', right: '\\)', display: false },
            { left: '$', right: '$', display: false },
        ],
        throwOnError: false,
    });
}

/**
 * Renders the formula embeds a Delta carries. Each .PostFormula span holds its
 * LaTeX source in data-formula - emitted server-side by DeltaRenderer, or built
 * by render_delta() from a {formula} embed op - with the raw source as fallback
 * text. KaTeX replaces that with the rendered math. This is the embed path;
 * render_math() is the separate pass for typed/pasted delimiters in plain text.
 */
function render_formulas(element) {
    if (typeof katex === 'undefined' || typeof katex.render !== 'function') {
        return;
    }

    element.querySelectorAll('.PostFormula[data-formula]').forEach((span) => {
        if (span.dataset.rendered === '1') {
            return;
        }

        katex.render(span.dataset.formula, span, { throwOnError: false });
        span.dataset.rendered = '1';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    render_math(document.body);
});

/**
 * Shows a transient, non-blocking notification in the bottom-right corner, in
 * place of window.alert() - which is jarring and blocks the whole page.
 * Auto-dismisses after a few seconds, or immediately via its close button
 * (handled by the delegated .ToastCloseButton click listener below).
 *
 * `message` is either a plain string - rendered as text, never as markup, so
 * it's always safe even for server messages that interpolate user-controlled
 * content (a moderator's ban reason, an actor name, ...) - or a prebuilt DOM
 * node (e.g. a link), appended as-is. A string is never assigned as innerHTML.
 */
function show_toast(message) {
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

    if (message instanceof Node) {
        text.appendChild(message);
    } else {
        text.textContent = message;
    }

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
let active_confirm_cancel = null;

function show_confirm(message) {
    // Cleanly cancel any dialog already open - resolve its promise as false
    // and drop its keydown listener - rather than orphaning that caller's
    // await forever and leaking the listener by just yanking the DOM.
    active_confirm_cancel?.();

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
            active_confirm_cancel = null;
            document.removeEventListener('keydown', on_keydown);
            overlay.remove();
            resolve(confirmed);
        };

        active_confirm_cancel = () => finish(false);

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
 * Like show_confirm, but requires the user to type something first: resolves to
 * the trimmed text on confirm, or null on cancel/escape/outside-click. The
 * confirm button stays disabled until the field is non-empty. This is our own
 * dialog, never window.prompt (which a user can suppress) - so an action gated
 * on it, like a ban's required reason, can't be bypassed by disabling dialogs.
 */
function show_prompt(message, options = {}) {
    active_confirm_cancel?.();

    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'ConfirmDialogOverlay';

        const card = document.createElement('div');
        card.className = 'ConfirmDialogCard Card';

        const text = document.createElement('div');
        text.className = 'ConfirmDialogMessage';
        text.textContent = message;
        card.appendChild(text);

        const input = document.createElement('textarea');
        input.className = 'ConfirmDialogInput';
        input.rows = 3;

        if (options.placeholder) {
            input.placeholder = options.placeholder;
        }

        card.appendChild(input);

        const actions = document.createElement('div');
        actions.className = 'ConfirmDialogActions d-flex gap-2';

        const cancel_button = document.createElement('button');
        cancel_button.type = 'button';
        cancel_button.className = 'Btn ConfirmDialogCancelButton';
        cancel_button.textContent = 'Cancel';

        const confirm_button = document.createElement('button');
        confirm_button.type = 'button';
        confirm_button.className = 'Btn ConfirmDialogConfirmButton';
        confirm_button.textContent = options.confirmLabel || 'OK';
        confirm_button.disabled = true;

        actions.appendChild(cancel_button);
        actions.appendChild(confirm_button);
        card.appendChild(actions);
        overlay.appendChild(card);
        document.body.appendChild(overlay);

        const finish = (value) => {
            active_confirm_cancel = null;
            document.removeEventListener('keydown', on_keydown);
            overlay.remove();
            resolve(value);
        };

        active_confirm_cancel = () => finish(null);

        const on_keydown = (event) => {
            if (event.key === 'Escape') {
                finish(null);
            }
        };

        input.addEventListener('input', () => {
            confirm_button.disabled = input.value.trim() === '';
        });

        cancel_button.addEventListener('click', () => finish(null));
        confirm_button.addEventListener('click', () => {
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
        document.addEventListener('keydown', on_keydown);

        input.focus();
    });
}

/**
 * POSTs JSON to an API endpoint and returns the parsed response value, or null
 * on any failure (after telling the user what went wrong). Callers only need
 * to handle the success path.
 */
async function api_post(path, payload, { signal } = {}) {
    let response;

    try {
        response = await fetch(window.siteURL + path, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: payload === undefined ? undefined : JSON.stringify(payload),
            signal,
        });
    } catch (error) {
        // An intentional abort (a newer call superseding this one) isn't a
        // network failure - nothing went wrong, something else just made
        // this call moot. Only a real failure gets the toast.
        if (error.name !== 'AbortError') {
            show_toast('Network error. Please check your connection and try again.');
        }

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
    const mobile_menu = document.querySelector('.MobileNavMenu');

    if (nav) {
        const update_layout = () => {
            const nav_height = nav.offsetHeight;

            if (page_title) {
                page_title.style.top = nav_height + 'px';
            }

            if (mobile_menu) {
                mobile_menu.style.top = nav_height + 'px';
                // Not bottom: 0 in CSS - .MainNavigation's backdrop-filter makes
                // it the containing block for this fixed-position child, so a
                // plain bottom: 0 there resolves against .MainNavigation's own
                // (nav-bar-sized) box instead of the real viewport. dvh stays
                // viewport-relative regardless of containing block.
                mobile_menu.style.height = `calc(100dvh - ${nav_height}px)`;
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

/**
 * Toggles MobileNavMenu open/closed - the hamburger's only job. Closes on
 * Escape or a click outside the panel, same "outside/Escape dismisses"
 * convention the confirm/prompt dialogs use elsewhere in this file.
 */
document.addEventListener('click', (event) => {
    const hamburger = document.querySelector('.NavHamburgerButton');
    const mobile_menu = document.querySelector('.MobileNavMenu');

    if (!hamburger || !mobile_menu) {
        return;
    }

    if (event.target.closest('.NavHamburgerButton')) {
        const open = mobile_menu.classList.toggle('Open');
        hamburger.classList.toggle('Active', open);
        hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
        return;
    }

    if (mobile_menu.classList.contains('Open') && !event.target.closest('.MobileNavMenu')) {
        mobile_menu.classList.remove('Open');
        hamburger.classList.remove('Active');
        hamburger.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const mobile_menu = document.querySelector('.MobileNavMenu');
    const hamburger = document.querySelector('.NavHamburgerButton');

    if (mobile_menu && mobile_menu.classList.contains('Open')) {
        mobile_menu.classList.remove('Open');
        hamburger?.classList.remove('Active');
        hamburger?.setAttribute('aria-expanded', 'false');
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

        // Abort whatever this input's previous search is still waiting on -
        // without this, a slower earlier response can resolve after a faster
        // later one and overwrite fresher results with stale ones.
        input.searchAbortController?.abort();
        const controller = new AbortController();
        input.searchAbortController = controller;

        let data;

        try {
            const response = await fetch(window.siteURL + '/api/search-users', {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ q: query }),
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            return; // aborted by a newer search, a network failure, or a non-JSON response body either way
        }

        results.replaceChildren();

        // Remembered so the scroll handler below knows what query to keep
        // paginating, and where to resume from.
        results.dataset.query = query;
        results.dataset.hasMore = data.response.hasMore ? '1' : '0';

        if (data.response.oldestUserId !== null) {
            results.dataset.oldestUserId = data.response.oldestUserId;
        } else {
            delete results.dataset.oldestUserId;
        }

        data.response.users.forEach((user_data) => {
            const user = OtherUser.fromData(user_data);
            results.appendChild(user.toElement());
        });
    }, 300);

    input.dataset.debounceId = debounce_id;
});

let loading_older_user_results = false;

window.addEventListener('scroll', async () => {
    const results = document.querySelector('.UserSearchResults');

    if (!results || results.dataset.hasMore !== '1' || loading_older_user_results) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_user_results = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    results.appendChild(spinner);

    try {
        const query = results.dataset.query ?? '';
        const before_user_id = results.dataset.oldestUserId;

        const response = await fetch(`${window.siteURL}/api/search-users`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ q: query, beforeUserId: before_user_id }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        // A new search (typed while this fetch was in flight) already reset
        // results.innerHTML, detaching our spinner - these are results for a
        // now-stale query, and inserting relative to a detached spinner
        // would throw. Just drop them.
        if (!results.contains(spinner)) {
            return;
        }

        results.dataset.hasMore = data.response.hasMore ? '1' : '0';

        if (data.response.oldestUserId !== null) {
            results.dataset.oldestUserId = data.response.oldestUserId;
        }

        data.response.users.forEach((user_data) => {
            const user = OtherUser.fromData(user_data);
            results.insertBefore(user.toElement(), spinner);
        });
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_user_results = false;
    }
});

// A trending-entity link (TrendingEntityChip) points here with ?q=<value> -
// prefill the box and fire the same debounced search the user typing would,
// rather than landing on an empty, unpopulated search page.
document.addEventListener('DOMContentLoaded', () => {
    const query = new URLSearchParams(window.location.search).get('q');
    const input = document.querySelector('.PostSearchInput');

    if (query === null || !input) {
        return;
    }

    input.value = query;
    input.dispatchEvent(new Event('input', { bubbles: true }));
});

document.addEventListener('input', (event) => {
    const input = event.target.closest('.PostSearchInput');

    if (!input) {
        return;
    }

    clearTimeout(input.dataset.debounceId);

    const debounce_id = setTimeout(async () => {
        const query = input.value.trim();
        const post_search = input.closest('.PostSearch');
        const results = post_search.querySelector('.PostSearchResults');

        // On a profile page PostSearch carries the author's id (data-user-id),
        // so the search is restricted to their posts and the default feed is
        // hidden while a query is active (the results take its place); clearing
        // the box brings the feed back. Global /search has no author / no feed.
        const author_id = post_search.dataset.userId || '';

        const profile_feed = document.querySelector('.ProfileFeed');

        if (profile_feed) {
            profile_feed.style.display = query === '' ? '' : 'none';
        }

        // Abort whatever this input's previous search is still waiting on -
        // without this, a slower earlier response can resolve after a faster
        // later one and overwrite fresher results with stale ones.
        input.searchAbortController?.abort();
        const controller = new AbortController();
        input.searchAbortController = controller;

        let data;

        try {
            const response = await fetch(window.siteURL + '/api/search-posts', {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ q: query, userId: author_id }),
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            return; // aborted by a newer search, a network failure, or a non-JSON response body either way
        }

        results.replaceChildren();

        // Remembered so the scroll handler below knows what query (and author)
        // to keep paginating, and where to resume from.
        results.dataset.query = query;
        results.dataset.userId = author_id;
        results.dataset.hasMore = data.response.hasMore ? '1' : '0';

        if (data.response.posts.length > 0) {
            results.dataset.oldestPostId = data.response.posts[data.response.posts.length - 1].postId;
        } else {
            delete results.dataset.oldestPostId;
        }

        data.response.posts.forEach((post_data) => {
            const element = Post.fromData(post_data).toElement();
            results.appendChild(element);
            render_math(element);
        });
    }, 300);

    input.dataset.debounceId = debounce_id;
});

let loading_older_post_results = false;

window.addEventListener('scroll', async () => {
    const results = document.querySelector('.PostSearchResults');

    if (!results || results.dataset.hasMore !== '1' || loading_older_post_results) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_post_results = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    results.appendChild(spinner);

    try {
        const query = results.dataset.query ?? '';
        const before_post_id = results.dataset.oldestPostId;
        const response = await fetch(`${window.siteURL}/api/search-posts`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ q: query, beforePostId: before_post_id, userId: results.dataset.userId || '' }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        // A new search (typed while this fetch was in flight) already reset
        // results.innerHTML, detaching our spinner - these are results for a
        // now-stale query, and inserting relative to a detached spinner
        // would throw. Just drop them.
        if (!results.contains(spinner)) {
            return;
        }

        results.dataset.hasMore = data.response.hasMore ? '1' : '0';

        if (data.response.posts.length === 0) {
            return;
        }

        data.response.posts.forEach((post_data) => {
            const element = Post.fromData(post_data).toElement();
            results.insertBefore(element, spinner);
            render_math(element);
        });

        results.dataset.oldestPostId = data.response.posts[data.response.posts.length - 1].postId;
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_post_results = false;
    }
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
    const card = button.closest('.OtherUser');

    button.disabled = true;

    const result = await api_post('/api/unblock', { userId: user_id });

    if (result === null) {
        button.disabled = false;
        return;
    }

    // Rebuilt from the same OtherUser class the page uses everywhere else,
    // rather than hand-assembled here, so it gets every action button a
    // fresh page load would show (Friends link, Report/Ban, mod controls,
    // ...) instead of a partial, hand-picked subset.
    card.replaceWith(OtherUser.fromData(result).toElement());
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
    const button = event.target.closest('.BookmarkButton');

    if (!button) {
        return;
    }

    const item_id = button.dataset.itemId;

    button.disabled = true;

    try {
        const result = await api_post('/api/bookmark', { itemId: item_id });

        if (result === null) {
            return;
        }

        button.dataset.bookmarked = result.bookmarked ? '1' : '0';
        button.textContent = result.bookmarked ? 'Bookmarked' : 'Bookmark';
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

    const card = button.closest('.Post');

    if (card) {
        card.remove();
    }
});

/**
 * Post editing is entirely client-built (no PHP EditPostForm/SSR counterpart
 * needed - it never appears except through this interaction, same as
 * show_confirm()'s dialog). Clicking Edit hides the .PostContent in place
 * and inserts a small form with its own .QuillEditor, pre-populated from the
 * data-description-delta/data-edit-title/data-edit-link-url attributes
 * Post::contentElement()/toPayload() only ever include for the viewer's own
 * posts. Saving replaces just the .PostContent element with a freshly built
 * one (post.js's postElement(), same as post creation) - the surrounding
 * .Post card and its PostActionBar (reply/like counts, unaffected by an edit)
 * are left alone.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('.EditButton');

    if (!button) {
        return;
    }

    const wrapper = button.closest('.Post');
    const post_element = wrapper ? wrapper.querySelector('.PostContent') : null;

    if (!post_element || post_element.dataset.descriptionDelta === undefined) {
        return;
    }

    // Already editing this post - a second click on Edit shouldn't stack a
    // second form.
    if (wrapper.querySelector('.PostEditForm')) {
        return;
    }

    post_element.style.display = 'none';

    const form = document.createElement('form');
    form.className = 'PostEditForm Card d-flex flex-column gap-2';
    form.dataset.postId = post_element.dataset.postId;

    const fields = document.createElement('fieldset');

    const title_row = document.createElement('div');
    title_row.className = 'PostComposerFields d-flex gap-2';

    const title_input = document.createElement('input');
    title_input.type = 'text';
    title_input.name = 'title';
    title_input.placeholder = 'Title (optional)';
    title_input.maxLength = 255;
    title_input.value = post_element.dataset.editTitle || '';
    title_input.setAttribute('aria-label', 'Title (optional)');
    title_row.appendChild(title_input);

    // A media post never had a link to begin with (create-post.php enforces
    // the same XOR api/edit-post.php does), so there's nothing here to edit.
    if (!post_element.dataset.hasMedia) {
        const link_input = document.createElement('input');
        link_input.type = 'text';
        link_input.name = 'linkURL';
        link_input.placeholder = 'Link (optional)';
        link_input.maxLength = 255;
        link_input.value = post_element.dataset.editLinkUrl || '';
        link_input.setAttribute('aria-label', 'Link (optional)');
        title_row.appendChild(link_input);
    }

    fields.appendChild(title_row);

    const editor_container = document.createElement('div');
    editor_container.className = 'QuillEditor';
    editor_container.dataset.placeholder = 'Edit your post...';
    fields.appendChild(editor_container);

    const description_input = document.createElement('input');
    description_input.type = 'hidden';
    description_input.className = 'DescriptionInput';
    description_input.name = 'description';
    fields.appendChild(description_input);

    form.appendChild(fields);

    const actions = document.createElement('div');
    actions.className = 'd-flex align-items-center gap-2 ms-auto';

    const cancel_button = document.createElement('button');
    cancel_button.type = 'button';
    cancel_button.className = 'Btn EditFormCancelButton';
    cancel_button.textContent = 'Cancel';
    actions.appendChild(cancel_button);

    const save_button = document.createElement('button');
    save_button.type = 'submit';
    save_button.className = 'Btn';
    save_button.textContent = 'Save';
    actions.appendChild(save_button);

    form.appendChild(actions);

    post_element.insertAdjacentElement('afterend', form);

    const quill = create_quill_editor(editor_container);

    try {
        const raw_delta = post_element.dataset.descriptionDelta;
        quill.setContents(raw_delta ? JSON.parse(raw_delta) : { ops: [] });
    } catch (error) {
        // Malformed/empty stored Delta - start from an empty editor rather
        // than leaving the form broken.
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('.EditFormCancelButton');

    if (!button) {
        return;
    }

    const form = button.closest('.PostEditForm');
    const post_element = form.previousElementSibling;

    form.remove();

    if (post_element && post_element.classList.contains('PostContent')) {
        post_element.style.display = '';
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.PostEditForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const quill = form.querySelector('.QuillEditor').__quill;
    form.querySelector('.DescriptionInput').value = JSON.stringify(quill.getContents());

    const save_button = form.querySelector('button[type=\'submit\']');
    save_button.disabled = true;

    const post_id = form.dataset.postId;
    const post_element = form.previousElementSibling;
    const link_input = form.querySelector('[name=\'linkURL\']');

    const result = await api_post('/api/edit-post', {
        postId: post_id,
        title: form.querySelector('[name=\'title\']').value,
        linkURL: link_input ? link_input.value : '',
        description: form.querySelector('.DescriptionInput').value,
    });

    if (result === null) {
        save_button.disabled = false;
        return;
    }

    const updated_post = Post.fromData(result);
    const new_post_element = updated_post.postElement();

    if (post_element && post_element.classList.contains('PostContent')) {
        post_element.replaceWith(new_post_element);
    }

    form.remove();
    render_math(new_post_element);
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

    // They're a friend now. Rebuild the card from the same OtherUser class the
    // page uses everywhere else (the payload now carries friendshipStatus
    // 'accepted', so it renders Remove Friend + every other action a fresh load
    // would) rather than hand-picking buttons here.
    const card = button.closest('.OtherUser');

    if (card && result.userId) {
        const new_card = OtherUser.fromData(result).toElement();

        // On the friends page the card sits in the pending-requests section;
        // move the rebuilt card into the friends section instead, so the live
        // DOM matches what a reload shows (an accepted friendship only ever
        // appears in FriendList, never PendingFriendRequestList).
        const pending_list = card.closest('.UserList[data-list-type="incoming"]');

        if (pending_list) {
            const friends_list = document.querySelector('.UserList[data-list-type="friends"]');

            if (friends_list) {
                friends_list.querySelector('.Notice')?.remove();
                friends_list.querySelector('h2').after(new_card);
            }

            card.remove();

            // user-friends.php only renders the pending-requests section at all
            // when it's non-empty - mirror that once the last card leaves.
            if (pending_list.querySelectorAll('.OtherUser').length === 0) {
                pending_list.remove();
            }
        } else {
            card.replaceWith(new_card);
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

    const reason = await show_prompt('Why are you reporting this?', { confirmLabel: 'Report' });

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

    const reason = await show_prompt(
        'Ban this user? This hides all their content and blocks their login. They\'ll see this reason on the login form.',
        { confirmLabel: 'Ban', placeholder: 'Reason for ban (required)' }
    );

    if (reason === null) {
        return;
    }

    const user_id = button.dataset.userId;

    button.disabled = true;

    const result = await api_post('/api/ban', { userId: user_id, reason });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.textContent = 'Banned';
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.BanTrendingEntityButton');

    if (!button) {
        return;
    }

    const entity_type = button.dataset.entityType;
    const entity_value = button.dataset.entityValue;

    const reason = await show_prompt(
        `Ban "${entity_value}" from trending? It won't be able to trend again until unbanned.`,
        { confirmLabel: 'Ban', placeholder: 'Reason for ban (required)' }
    );

    if (reason === null) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/ban-trending-entity', { entityType: entity_type, entityValue: entity_value, reason });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.closest('.TrendingEntityChip')?.remove();
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.UnbanTrendingEntityButton');

    if (!button) {
        return;
    }

    const entity_type = button.dataset.entityType;
    const entity_value = button.dataset.entityValue;

    if (!await show_confirm(`Unban "${entity_value}"? It will be able to trend again.`)) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/unban-trending-entity', { entityType: entity_type, entityValue: entity_value });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.closest('.BannedTrendingEntity')?.remove();
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

// Running autoplays, keyed by their carousel element. Each entry is whatever
// we're currently waiting on to advance: a setTimeout id for an image slide
// (a fixed pause), or null for a video/audio slide (advancing there is
// driven by that media's own "ended" event instead - see
// schedule_carousel_autoplay_advance()).
const carousel_autoplays = new WeakMap();
const CAROUSEL_AUTOPLAY_IMAGE_DELAY = 3000;

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

    // A video/audio playing on the slide we just left would keep going (and
    // stay audible) while hidden - pause every one in the carousel so
    // nothing plays off-screen once the displayed item changes.
    carousel.querySelectorAll('video, audio').forEach((media) => media.pause());

    // Load the slide now on screen (covers a backward wrap onto one we hadn't
    // reached yet), plus a buffer of the next several slides ahead of it, so
    // the loading stays ahead of the viewer and advancing never waits on a
    // fetch - rather than trickling one slide in per step and effectively
    // loading just-in-time. window.carouselEagerItems
    // (Carousel::INITIAL_EAGER_ITEMS) is how many to keep loaded ahead.
    carousel_load_slide(slides[next_index]);

    for (let i = next_index + 1; i <= next_index + window.carouselEagerItems && i < slides.length; i++) {
        carousel_load_slide(slides[i]);
    }

    const counter = carousel.querySelector('.CarouselCounter');

    if (counter) {
        counter.textContent = (next_index + 1) + ' / ' + slides.length;
    }
}

// Waits on whatever the now-active slide actually is: an image holds for
// CAROUSEL_AUTOPLAY_IMAGE_DELAY then advances on a timer, a video/audio
// plays through and advances when it fires "ended" (see the delegated
// listener below) - a fixed timer would either cut playing media off early
// or leave dead air waiting out a short clip.
function schedule_carousel_autoplay_advance(carousel) {
    if (!carousel_autoplays.has(carousel)) {
        return;
    }

    const media = carousel.querySelector('.CarouselSlide.Active video, .CarouselSlide.Active audio');

    if (media) {
        carousel_autoplays.set(carousel, null);
        // Marked so the "manual play stops autoplay" listener below can tell
        // this play() call apart from the viewer actually clicking play
        // themselves - autoplay starting its own media shouldn't stop autoplay.
        carousel.dataset.autoplayStartedPlay = '1';
        media.play().catch(() => {
            // Blocked (e.g. the browser's autoplay policy) - the play event
            // never fires in that case, so the flag above would otherwise
            // stay stuck and misread the viewer's next manual play as
            // autoplay's own. Nothing further advances this slide
            // automatically; the viewer can still step through manually.
            delete carousel.dataset.autoplayStartedPlay;
        });

        return;
    }

    const timeout_id = setTimeout(() => {
        carousel_advance(carousel, 1);
        schedule_carousel_autoplay_advance(carousel);
    }, CAROUSEL_AUTOPLAY_IMAGE_DELAY);

    carousel_autoplays.set(carousel, timeout_id);
}

function start_carousel_autoplay(carousel) {
    if (carousel_autoplays.has(carousel)) {
        return;
    }

    carousel_autoplays.set(carousel, null);
    schedule_carousel_autoplay_advance(carousel);

    const toggle = carousel.querySelector('.CarouselAutoplay');

    if (toggle) {
        toggle.textContent = 'Stop Autoplay';
    }
}

function stop_carousel_autoplay(carousel) {
    if (!carousel_autoplays.has(carousel)) {
        return;
    }

    const pending_timeout = carousel_autoplays.get(carousel);

    if (pending_timeout) {
        clearTimeout(pending_timeout);
    }

    carousel_autoplays.delete(carousel);

    const toggle = carousel.querySelector('.CarouselAutoplay');

    if (toggle) {
        toggle.textContent = 'Autoplay';
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.CarouselPrev, .CarouselNext');

    if (!button) {
        return;
    }

    const carousel = button.closest('.Carousel');

    // Stepping through by hand stops autoplay.
    stop_carousel_autoplay(carousel);
    carousel_advance(carousel, button.classList.contains('CarouselNext') ? 1 : -1);
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.CarouselAutoplay');

    if (!toggle) {
        return;
    }

    const carousel = toggle.closest('.Carousel');

    if (carousel_autoplays.has(carousel)) {
        stop_carousel_autoplay(carousel);
    } else {
        start_carousel_autoplay(carousel);
    }
});

// Clicking an image in the carousel stops autoplay.
document.addEventListener('click', (event) => {
    const image = event.target.closest('.Carousel .ImageItem img');

    if (!image) {
        return;
    }

    stop_carousel_autoplay(image.closest('.Carousel'));
});

// The viewer manually (re-)playing a video/audio in the carousel stops
// autoplay - unless autoplay itself just started this exact play. The play
// event doesn't bubble, so this listens in the capture phase to catch it via
// delegation.
document.addEventListener('play', (event) => {
    const media = event.target.closest('.Carousel video, .Carousel audio');

    if (!media) {
        return;
    }

    const carousel = media.closest('.Carousel');

    if (carousel.dataset.autoplayStartedPlay === '1') {
        delete carousel.dataset.autoplayStartedPlay;
        return;
    }

    stop_carousel_autoplay(carousel);
}, true);

// Advances past a video/audio slide once it finishes playing - the "ended"
// event doesn't bubble either, same delegation approach as "play" above.
document.addEventListener('ended', (event) => {
    const media = event.target.closest('.Carousel video, .Carousel audio');

    if (!media) {
        return;
    }

    const carousel = media.closest('.Carousel');

    if (!carousel_autoplays.has(carousel)) {
        return;
    }

    carousel_advance(carousel, 1);
    schedule_carousel_autoplay_advance(carousel);
}, true);

// Pause any video/audio once it's scrolled well clear of the viewport (~50vh
// past the edge - about a post away), so a player you've scrolled past doesn't
// keep going. Pause-only by design: no auto-resume when it scrolls back, which
// would fight the browser's autoplay policy and feel janky. The positive
// rootMargin is what holds off the pause until it's a half-viewport out rather
// than the instant it leaves.
const media_offscreen_observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (!entry.isIntersecting && !entry.target.paused) {
            entry.target.pause();
        }
    });
}, { rootMargin: '50% 0px' });

function observe_offscreen_media(root) {
    if (root.matches?.('video, audio')) {
        media_offscreen_observer.observe(root);
    }

    root.querySelectorAll?.('video, audio').forEach((media) => media_offscreen_observer.observe(media));
}

document.addEventListener('DOMContentLoaded', () => {
    observe_offscreen_media(document.body);

    // Media inserted later (infinite scroll, client-rendered carousels) gets
    // observed too - same "dynamically added content is handled automatically"
    // spirit as the delegated event handlers. observe() is a no-op on an
    // already-observed element, so overlapping mutations don't double up.
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    observe_offscreen_media(node);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
});

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

        // Already carries its own cache-busting ?v=<mtime> (User::avatarPath()) -
        // appending another query param here would have produced a malformed
        // "...?v=123?t=456" URL.
        avatar.src = data.response.image;

        const fallback = form.parentElement.querySelector('.AvatarInitial');

        if (fallback) {
            fallback.remove();
        }
    } catch (error) {
        show_toast('Network error. Please check your connection and try again.');
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

        const response = await fetch(`${window.siteURL}/api/message-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ otherUserId: other_user_id, beforeMessageId: before_message_id }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

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
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        const height_before_cleanup = document.body.scrollHeight;
        spinner.remove();
        window.scrollTo(0, window.scrollY - (height_before_cleanup - document.body.scrollHeight));
        loading_older_messages = false;
    }
});

let loading_older_notifications = false;

window.addEventListener('scroll', async () => {
    // The nav dropdown also renders a .NotificationList (and earlier in the
    // DOM), so target the page's list specifically - querySelector would grab
    // the dropdown's, which is capped and always has-more=0, and the page list
    // would never scroll.
    const list = Array.from(document.querySelectorAll('.NotificationList')).find((candidate) => !candidate.closest('.NotificationDropdown'));

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

        const response = await fetch(`${window.siteURL}/api/notification-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ beforeNotificationId: before_notification_id }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        const { notifications, hasMore: has_more } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (notifications.length === 0) {
            return;
        }

        notifications.forEach((notification_data) => {
            list.insertBefore(Notification.fromData(notification_data).toElement(), spinner);
        });

        list.dataset.oldestNotificationId = notifications[notifications.length - 1].notificationId;
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_notifications = false;
    }
});

let loading_older_reports = false;

window.addEventListener('scroll', async () => {
    // There's only ever one .ReportList (the admin moderation queue), so a
    // plain querySelector is right - no dropdown-dodging like notifications.
    const list = document.querySelector('.ReportList');

    if (!list || list.dataset.hasMore !== '1' || loading_older_reports) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_reports = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    list.appendChild(spinner);

    try {
        const before_report_id = list.dataset.oldestReportId;

        const response = await fetch(`${window.siteURL}/api/report-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ beforeReportId: before_report_id }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        const { reports, hasMore: has_more } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (reports.length === 0) {
            return;
        }

        reports.forEach((report_data) => {
            const card = ReportCard.fromData(report_data).toElement();
            list.insertBefore(card, spinner);
            // A reported post/message can contain math - render it (formula
            // embeds and typed delimiters) the same as the feed does.
            render_math(card);
        });

        list.dataset.oldestReportId = reports[reports.length - 1].reportId;
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_reports = false;
    }
});

const WS_RECONNECT_DELAY_MS = 10000;
const WS_MAX_RECONNECT_ATTEMPTS = 3;
let ws_reconnect_attempts = 0;

// Schedules a reconnect after a failed (re)connect or a dropped socket, but
// gives up after a few tries rather than polling forever - a genuinely-gone
// server or an expired session would otherwise retry indefinitely. A
// successful connection resets the counter (see the socket 'open' handler),
// so an established session still rides out the occasional blip.
function schedule_ws_reconnect() {
    if (ws_reconnect_attempts >= WS_MAX_RECONNECT_ATTEMPTS) {
        show_toast('Something went wrong. Try reloading the page.');
        return;
    }

    ws_reconnect_attempts += 1;
    setTimeout(connect_websocket, WS_RECONNECT_DELAY_MS);
}

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

    const toast_link = document.createElement('a');
    toast_link.href = notification.targetURL();
    toast_link.textContent = notification.text();
    show_toast(toast_link);

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
        const response = await fetch(`${window.siteURL}/api/ws-token`, {
            method: 'POST',
            headers: csrf_headers(),
        });

        if (!response.ok) {
            throw new Error('token fetch failed');
        }

        ({ token } = (await response.json()).response);
    } catch (error) {
        schedule_ws_reconnect();
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
        schedule_ws_reconnect();
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

    socket.addEventListener('open', () => {
        ws_reconnect_attempts = 0;
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

        // Restore the dot if the server never actually recorded them as seen -
        // otherwise the UI claims they're read while the server still has them
        // unseen.
        if (await api_post('/api/mark-notifications-seen') === null) {
            dot.classList.add('Active');
        }
    });

    connect_websocket();
});

let loading_older_feed_items = false;

window.addEventListener('scroll', async () => {
    const list = document.querySelector('.FeedList');

    // A hidden feed (e.g. the profile feed while a per-user post search is
    // active) must not paginate - offsetParent is null when it's display:none.
    if (!list || list.offsetParent === null || list.dataset.hasMore !== '1' || loading_older_feed_items) {
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
        const request = { feedType: feed_type, beforePostId: before_post_id };

        if (feed_type === 'user') {
            request.userId = list.dataset.userId;
        } else if (feed_type === 'tag') {
            request.tag = list.dataset.tag;
        }

        const response = await fetch(`${window.siteURL}/api/feed-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(request),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

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
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_feed_items = false;
    }
});

let loading_older_bookmarks = false;

window.addEventListener('scroll', async () => {
    const list = document.querySelector('.BookmarkList');

    if (!list || list.offsetParent === null || list.dataset.hasMore !== '1' || loading_older_bookmarks) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_bookmarks = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    list.appendChild(spinner);

    try {
        const before_created_at = encodeURIComponent(list.dataset.oldestBookmarkCreatedAt);
        const before_post_id = list.dataset.oldestBookmarkPostId;

        const response = await fetch(`${window.siteURL}/api/bookmark-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ beforeCreatedAt: before_created_at, beforePostId: before_post_id }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        const { posts, hasMore: has_more, oldestBookmarkCreatedAt: oldest_created_at, oldestBookmarkPostId: oldest_post_id } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (posts.length === 0) {
            return;
        }

        posts.forEach((post_data) => {
            const element = Post.fromData(post_data).toElement();
            list.insertBefore(element, spinner);
            render_math(element);
        });

        list.dataset.oldestBookmarkCreatedAt = oldest_created_at;
        list.dataset.oldestBookmarkPostId = oldest_post_id;
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_bookmarks = false;
    }
});

let loading_older_replies = false;

window.addEventListener('scroll', async () => {
    const list = document.querySelector('.ReplyList');

    if (!list || list.dataset.hasMore !== '1' || loading_older_replies) {
        return;
    }

    const near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    if (!near_bottom) {
        return;
    }

    loading_older_replies = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    list.appendChild(spinner);

    try {
        const parent_id = list.dataset.parentId;
        const before_post_id = list.dataset.oldestPostId;

        const response = await fetch(`${window.siteURL}/api/reply-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ parentId: parent_id, beforePostId: before_post_id }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

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
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_older_replies = false;
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

    const target = Array.from(document.querySelectorAll('.UserList')).find((list) => {
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

        const response = await fetch(`${window.siteURL}/api/friend-list-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ listType: list_type, userId: user_id, beforeFriendshipId: before }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

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
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
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

    const remove_files_button = file_input.closest('.Composer').querySelector('.RemoveFilesButton');

    remove_files_button.style.display = file_input.files.length === 0 ? 'none' : '';
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('.RemoveFilesButton');

    if (!button) {
        return;
    }

    const file_input = button.closest('.Composer').querySelector('input[type=\'file\']');

    file_input.value = '';
    file_input.dispatchEvent(new Event('change', { bubbles: true }));
});

/**
 * Adds a native `title` (hover tooltip) to each Quill toolbar button - the snow
 * theme provides none. Plain-format buttons map by their ql-* class; the header
 * and list buttons carry their level/kind in a `value` attribute.
 */
function add_toolbar_tooltips(quill) {
    const toolbar = quill.getModule('toolbar').container;

    const titles = {
        'ql-bold': 'Bold',
        'ql-italic': 'Italic',
        'ql-underline': 'Underline',
        'ql-strike': 'Strikethrough',
        'ql-blockquote': 'Blockquote',
        'ql-code-block': 'Code block',
        'ql-code': 'Inline code',
        'ql-link': 'Link',
        'ql-formula': 'Formula',
        'ql-clean': 'Clear formatting',
    };

    Object.entries(titles).forEach(([class_name, title]) => {
        const button = toolbar.querySelector('button.' + class_name);

        if (button) {
            button.title = title;
        }
    });

    toolbar.querySelectorAll('button.ql-header[value]').forEach((button) => {
        button.title = 'Heading ' + button.getAttribute('value');
    });

    const list_titles = { ordered: 'Numbered list', bullet: 'Bullet list' };

    toolbar.querySelectorAll('button.ql-list[value]').forEach((button) => {
        button.title = list_titles[button.getAttribute('value')] || 'List';
    });
}

/**
 * Creates a Quill editor inside $editor_container (a .QuillEditor div) and
 * stores the instance directly on that element (.__quill) rather than in a
 * single shared variable - more than one can be mounted on a page at once
 * (the page's own composer plus an inline post-edit form, say), so each
 * caller looks up "this form's Quill" from its own .QuillEditor rather than
 * a page-wide global.
 */
function create_quill_editor(editor_container) {
    const quill = new Quill(editor_container, {
        theme: 'snow',
        placeholder: editor_container.dataset.placeholder,
        modules: {
            // Only the formats DeltaRenderer / render_delta actually render.
            // 'formula' opens Quill's KaTeX-backed formula input (KaTeX is
            // loaded before Quill so window.katex is present at construction).
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ header: 1 }, { header: 2 }, { header: 3 }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block', 'code'],
                ['link', 'formula'],
                ['clean'],
            ],
        },
    });

    editor_container.__quill = quill;

    // Quill's snow toolbar ships no button tooltips - add native title hints so
    // hovering a toolbar button explains what it does.
    add_toolbar_tooltips(quill);

    return quill;
}

document.addEventListener('DOMContentLoaded', () => {
    const editor_container = document.querySelector('.QuillEditor');

    if (editor_container) {
        create_quill_editor(editor_container);
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.Composer');
        const form_editor_container = form ? form.querySelector('.QuillEditor') : null;

        if (!form || !form_editor_container || !form_editor_container.__quill) {
            return;
        }

        event.preventDefault();

        const quill = form_editor_container.__quill;

        // Submit the Delta (Quill's own document model), not rendered HTML -
        // the server sanitizes and stores it, and both renderers build from it.
        const description_input = form.querySelector('.DescriptionInput');
        description_input.value = JSON.stringify(quill.getContents());

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

            // reset() empties the fields but fires no input/change event, so
            // re-sync the link/file mutual hiding by hand - after a post both
            // controls show again until one is used again. The file picker's
            // Remove Files button tracks the picker by the same change event,
            // so it gets the same hand-reset (no files selected anymore).
            sync_post_composer_fields(form);

            const remove_files_button = form.querySelector('.RemoveFilesButton');

            if (remove_files_button) {
                remove_files_button.style.display = 'none';
            }

            const link_image_preview = form.querySelector('.LinkImagePreview');

            if (link_image_preview) {
                link_image_preview.style.display = 'none';
                link_image_preview.querySelector('.LinkImagePreviewThumb').src = '';
            }

            const link_url_input = form.querySelector('[name=\'linkURL\']');

            if (link_url_input) {
                delete link_url_input.dataset.lastFetchedUrl;
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

    try {
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

        if (!response.ok) {
            const error = document.createElement('p');
            error.className = 'Error';
            error.textContent = data.error;
            form.insertBefore(error, submit_button);
            return;
        }

        form.reset();
        submit_button.textContent = 'Changed!';
    } catch (error) {
        show_toast('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

/* ----- Settings: toggle opt-in email 2FA ----- */

const TWO_FACTOR_ON_EXPLANATION = 'When you log in, we\'ll email a verification code you have to enter to finish signing in.';
const TWO_FACTOR_OFF_EXPLANATION = 'Add a second step at login: we\'ll email a verification code you have to enter, so your password alone isn\'t enough to get in.';

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.TwoFactorSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const existing_error = form.querySelector('.Error');

    if (existing_error) {
        existing_error.remove();
    }

    const submit_button = form.querySelector('button[type=\'submit\']');
    const password_input = form.querySelector('[name=\'currentPassword\']');
    submit_button.disabled = true;

    const data = await api_post('/api/two-factor', {
        action: submit_button.dataset.action,
        currentPassword: password_input.value,
    });

    if (!data) {
        submit_button.disabled = false;
        return;
    }

    // Flip the form in place to the new state (no reload) - same strings the
    // server renders in TwoFactorSettingsForm, kept in parity here.
    const now_enabled = data.enabled;
    form.dataset.enabled = now_enabled ? '1' : '0';
    form.querySelector('legend').textContent = now_enabled
        ? 'Two-factor authentication is on'
        : 'Two-factor authentication is off';
    form.querySelector('fieldset p').textContent = now_enabled
        ? TWO_FACTOR_ON_EXPLANATION
        : TWO_FACTOR_OFF_EXPLANATION;
    submit_button.textContent = now_enabled
        ? 'Turn off two-factor authentication'
        : 'Turn on two-factor authentication';
    submit_button.dataset.action = now_enabled ? 'disable' : 'enable';
    password_input.value = '';
    submit_button.disabled = false;

    show_toast(now_enabled
        ? 'Two-factor authentication is now on.'
        : 'Two-factor authentication is now off.');
});

document.addEventListener('input', (event) => {
    const username_input = event.target.closest('.SignupForm [name=\'username\']');

    if (!username_input) {
        return;
    }

    username_input.value = username_input.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 32);
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
    const previous_theme = document.documentElement.dataset.theme || 'system';

    const apply = (value) => {
        if (value === 'system') {
            delete document.documentElement.dataset.theme;
        } else {
            document.documentElement.dataset.theme = value;
        }
    };

    apply(theme);

    // On a failed save, put the page back to the theme the server still holds
    // rather than showing one that didn't persist (which would silently snap
    // back on the next load). api_post already surfaced the error to the user.
    if (await api_post('/api/update-theme', { theme }) === null) {
        apply(previous_theme);
        select.value = previous_theme;
    }
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
    const quill = form.querySelector('.QuillEditor')?.__quill;

    if (quill) {
        const selection = quill.getSelection(true);
        quill.insertText(selection.index, emoji, 'user');
        quill.setSelection(selection.index + emoji.length, 0, 'user');
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

        // Abort whatever this input's previous preview fetch is still waiting
        // on, and register this run's controller, synchronously, before any
        // await below - two overlapping callbacks (a fast keystroke burst)
        // must never race on which one gets to be "current", which they
        // could if this happened after an awaited step.
        input.previewAbortController?.abort();
        const controller = new AbortController();
        input.previewAbortController = controller;

        await discard_staged_link_image(form);

        if (!url) {
            input.dataset.lastFetchedUrl = url;

            return;
        }

        const preview = await api_post('/api/link-preview', { url }, { signal: controller.signal });

        // A null preview covers both a failed fetch and an aborted one -
        // don't mark the url as fetched on failure, so retyping the same url
        // later retries instead of permanently no-op'ing. Also bail if the
        // input has moved on to a different value since this fetch started.
        if (!preview || input.value.trim() !== url) {
            return;
        }

        input.dataset.lastFetchedUrl = url;

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

        const quill = form.querySelector('.QuillEditor')?.__quill;

        if (preview.description && quill) {
            const current_description = quill.getText().trim();
            const autofilled_description = form.dataset.autofilledDescription ?? '';

            if (current_description === '' || current_description === autofilled_description) {
                quill.setText(preview.description);
                form.dataset.autofilledDescription = preview.description.trim();
            }
        }

        if (preview.image) {
            show_link_image_preview(form, preview.image);
        }
    }, delay);
});

/* ----- Admin Banned Users page: unban, infinite scroll, and search ----- */

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.UnbanButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Unban this user? Their content and login work again.')) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/unban', { userId: button.dataset.userId });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.closest('.BannedUser').remove();
});

/* ----- Settings: revoke a remembered device ----- */

document.addEventListener('click', async (event) => {
    const button = event.target.closest('.RevokeSessionButton');

    if (!button) {
        return;
    }

    if (!await show_confirm('Revoke this device? It will be signed out and have to log in again.')) {
        return;
    }

    button.disabled = true;

    const result = await api_post('/api/revoke-session', { tokenId: button.dataset.tokenId });

    if (result === null) {
        button.disabled = false;
        return;
    }

    button.closest('.RememberedDevice').remove();
});

let loading_banned_users = false;

window.addEventListener('scroll', async () => {
    const list = document.querySelector('.BannedUserList');

    if (!list || list.dataset.hasMore !== '1' || loading_banned_users) {
        return;
    }

    const threshold = 300;
    const rect = list.getBoundingClientRect();

    if (!(rect.bottom > 0 && rect.bottom <= window.innerHeight + threshold)) {
        return;
    }

    loading_banned_users = true;

    const spinner = document.createElement('div');
    spinner.className = 'LoadingSpinner';
    list.appendChild(spinner);

    try {
        const response = await fetch(`${window.siteURL}/api/banned-history`, {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ beforeUserId: list.dataset.oldestUserId }),
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        // A new search (typed while this fetch was in flight) already reset
        // list.innerHTML, detaching our spinner - these are results for a
        // now-stale query, and inserting relative to a detached spinner
        // would throw. Just drop them.
        if (!list.contains(spinner)) {
            return;
        }

        const { items, hasMore: has_more, oldestUserId: oldest_user_id } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (oldest_user_id !== null) {
            list.dataset.oldestUserId = oldest_user_id;
        }

        items.forEach((item) => {
            list.insertBefore(BannedUser.fromData(item).toElement(), spinner);
        });
    } catch (error) {
        // A network failure or a non-JSON response body - leave hasMore as-is
        // so the next scroll simply retries.
    } finally {
        spinner.remove();
        loading_banned_users = false;
    }
});

document.addEventListener('input', (event) => {
    const input = event.target.closest('.BannedUserSearchInput');

    if (!input) {
        return;
    }

    clearTimeout(input.dataset.debounceId);

    const debounce_id = setTimeout(async () => {
        const query = input.value.trim();
        const list = document.querySelector('.BannedUserList');

        if (!list) {
            return;
        }

        // An empty box goes back to the paginated full list (first page,
        // cursor reset); a query shows its matches with pagination off.
        const url = query === ''
            ? `${window.siteURL}/api/banned-history`
            : `${window.siteURL}/api/search-banned-users`;
        const request_body = query === '' ? {} : { q: query };

        // Abort whatever this input's previous search/page-reset is still
        // waiting on - without this, a slower earlier response can resolve
        // after a faster later one and overwrite fresher results with stale
        // ones.
        input.searchAbortController?.abort();
        const controller = new AbortController();
        input.searchAbortController = controller;

        let data;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(request_body),
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            return; // aborted by a newer search, a network failure, or a non-JSON response body either way
        }

        list.replaceChildren();

        const { items, hasMore: has_more, oldestUserId: oldest_user_id } = data.response;

        list.dataset.hasMore = has_more ? '1' : '0';

        if (oldest_user_id !== null && oldest_user_id !== undefined) {
            list.dataset.oldestUserId = oldest_user_id;
        }

        if (items.length === 0) {
            const notice = document.createElement('p');
            notice.className = 'Muted Notice';
            notice.textContent = query === '' ? 'No banned users.' : 'No banned users match that search.';
            list.appendChild(notice);
            return;
        }

        items.forEach((item) => {
            list.appendChild(BannedUser.fromData(item).toElement());
        });
    }, 300);

    input.dataset.debounceId = debounce_id;
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.AdminSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const data = await api_post('/api/turnstile-settings', {
        turnstileSiteKey: form.querySelector('[name=\'turnstileSiteKey\']').value,
        turnstileSecretKey: form.querySelector('[name=\'turnstileSecretKey\']').value,
    });

    submit_button.disabled = false;

    if (data) {
        show_toast('Settings saved.');
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.GoogleAuthSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const data = await api_post('/api/google-auth-settings', {
        googleAuthClientId: form.querySelector('[name=\'googleAuthClientId\']').value,
        googleAuthSecret: form.querySelector('[name=\'googleAuthSecret\']').value,
    });

    submit_button.disabled = false;

    if (data) {
        show_toast('Settings saved.');
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.MailSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const data = await api_post('/api/mail-settings', {
        smtpHost: form.querySelector('[name=\'smtpHost\']').value,
        smtpPort: form.querySelector('[name=\'smtpPort\']').value,
        smtpUsername: form.querySelector('[name=\'smtpUsername\']').value,
        smtpPassword: form.querySelector('[name=\'smtpPassword\']').value,
        smtpEncryption: form.querySelector('[name=\'smtpEncryption\']').value,
    });

    submit_button.disabled = false;

    if (data) {
        show_toast('Settings saved.');
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.InfoSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    // Each info form holds one textarea whose name is the Settings key
    // (aboutText/termsText/privacyText); the endpoint is /api/<key-without-Text>-settings.
    const field = form.querySelector('textarea');
    const field_name = field.name;
    const path = '/api/' + field_name.replace(/Text$/, '') + '-settings';

    const data = await api_post(path, {
        [field_name]: field.value,
    });

    submit_button.disabled = false;

    if (data) {
        show_toast('Settings saved.');
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.FaviconSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const file_input = form.querySelector('input[type=\'file\'][name=\'favicon\']');

    if (!file_input.files.length) {
        show_toast('Choose a file first.');
        return;
    }

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const body = new FormData();
    body.append('favicon', file_input.files[0]);

    try {
        const response = await fetch(window.siteURL + '/api/favicon-settings', {
            method: 'POST',
            headers: csrf_headers(),
            body,
        });

        const data = await response.json();

        if (!response.ok) {
            show_toast(data.error || 'Something went wrong. Please try again.');
            return;
        }

        show_toast('Settings saved.');
        form.querySelector('.FaviconPreview').src = window.siteURL + '/uploads/site/favicon.png?' + Date.now();
    } catch (error) {
        show_toast('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.ResetPasswordForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const data = await api_post('/api/reset-password', {
        token: form.querySelector('[name=\'token\']').value,
        newPassword: form.querySelector('[name=\'newPassword\']').value,
        confirmPassword: form.querySelector('[name=\'confirmPassword\']').value,
    });

    if (!data) {
        submit_button.disabled = false;
        return;
    }

    if (!data.reset) {
        submit_button.disabled = false;
        show_toast('That\'s already your password - nothing was changed.');
        return;
    }

    // Swap the form out for the same "you're done" message the old
    // server-rendered page showed, rather than a redirect - there's nowhere
    // more useful to send someone who just reset their password than back
    // here with confirmation.
    const notice = document.createElement('p');
    notice.textContent = 'Your password has been reset. You can now log in.';

    const login_link = document.createElement('a');
    login_link.href = window.siteURL + '/login';
    login_link.textContent = 'Log In';

    form.replaceWith(notice, login_link);
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.SignupForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const captcha_input = form.querySelector('[name=\'cf-turnstile-response\']');

    const data = await api_post('/api/signup', {
        username: form.querySelector('[name=\'username\']').value,
        email: form.querySelector('[name=\'email\']').value,
        displayName: form.querySelector('[name=\'displayName\']').value,
        password: form.querySelector('[name=\'password\']').value,
        rememberMe: form.querySelector('[name=\'rememberMe\']').checked,
        captchaToken: captcha_input ? captcha_input.value : null,
    });

    if (!data) {
        submit_button.disabled = false;
        return;
    }

    // Auto-verified (mail delivery itself is broken/unconfigured, not this
    // address specifically) - there's nothing to check, so don't tell them
    // to. Just let them straight into the site, same as any other verified
    // sign-up would land.
    window.location = window.siteURL + (data.verified ? '/' : '/check-inbox');
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.LogoutForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    // A plain POST-and-redirect leaves the POST in browser history, so
    // hitting Back afterward triggers the "confirm form resubmission"
    // prompt. Logging out via fetch (no navigation) and redirecting via JS
    // avoids that entirely - Back just lands on whatever GET page preceded
    // this one.
    await api_post('/api/logout');

    window.location = window.siteURL + '/';
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.LoginForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const captcha_input = form.querySelector('[name=\'cf-turnstile-response\']');

    const data = await api_post('/api/login', {
        identifier: form.querySelector('[name=\'identifier\']').value,
        password: form.querySelector('[name=\'password\']').value,
        rememberMe: form.querySelector('[name=\'rememberMe\']').checked,
        captchaToken: captcha_input ? captcha_input.value : null,
    });

    if (!data) {
        submit_button.disabled = false;
        return;
    }

    // 2FA is on for this account: the password checked out but login isn't
    // complete. Reload /login, which now shows the code-entry step (the
    // pending state lives server-side in the session) rather than logging in.
    if (data.twoFactorRequired) {
        window.location = window.siteURL + '/login';
        return;
    }

    window.location = window.siteURL + '/';
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.TwoFactorForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const data = await api_post('/api/verify-2fa', {
        code: form.querySelector('[name=\'code\']').value,
    });

    if (!data) {
        submit_button.disabled = false;
        return;
    }

    window.location = window.siteURL + '/';
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.ForgotPasswordForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const data = await api_post('/api/forgot-password', {
        email: form.querySelector('[name=\'email\']').value,
    });

    submit_button.disabled = false;

    if (!data) {
        return;
    }

    // Swap the form out entirely (rather than just re-enabling it) so it
    // can't be resubmitted a bunch of times in a row - each submit sends an
    // email.
    const notice = document.createElement('p');
    notice.textContent = 'If that email address is on file, a password reset link has been sent. If you don\'t see it, check your junk/spam folder.';
    form.replaceWith(notice);
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.ChangeEmailForm');

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

    try {
        const response = await fetch(window.siteURL + '/api/change-email', {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                newEmail: form.querySelector('[name=\'newEmail\']').value,
                currentPassword: form.querySelector('[name=\'currentPassword\']').value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            const error = document.createElement('p');
            error.className = 'Error';
            error.textContent = data.error;
            form.insertBefore(error, submit_button);
            return;
        }

        if (!data.response.changed) {
            show_toast('That is already your email address.');
            return;
        }

        // The account is back behind the verification gate until the new
        // address confirms - land on the page that says exactly that.
        window.location = window.siteURL + '/check-inbox';
    } catch (error) {
        show_toast('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.DeleteAccountForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    if (!await show_confirm('Delete your account? Your posts, replies, and messages are gone permanently - this can\'t be undone.')) {
        return;
    }

    const existing_error = form.querySelector('.Error');

    if (existing_error) {
        existing_error.remove();
    }

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    try {
        const response = await fetch(window.siteURL + '/api/delete-account', {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                currentPassword: form.querySelector('[name=\'currentPassword\']').value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            const error = document.createElement('p');
            error.className = 'Error';
            error.textContent = data.error;
            form.insertBefore(error, submit_button);
            return;
        }

        // The account (and this session) is gone - nowhere left to land but home.
        window.location = window.siteURL + '/';
    } catch (error) {
        show_toast('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});
