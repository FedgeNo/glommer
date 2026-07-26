import { Avatar } from '/Avatar.js';
import { UserBio } from '/UserBio.js';
import { User } from '/User.js';
import { OtherUser } from '/OtherUser.js';
import { Post } from '/Post.js';
import { Message } from '/Message.js';
import { Notification } from '/Notification.js';
import { ReportCard } from '/ReportCard.js';
import { BannedUser } from '/BannedUser.js';
import { InfiniteScroller } from '/InfiniteScroller.js';
import { ClickHandler } from '/ClickHandler.js';
import { Search } from '/Search.js';
import { CarouselController } from '/CarouselController.js';
import { Toast } from '/Toast.js';
import { WebSocketManager } from '/WebSocketManager.js';
import { parse_server_date, format_relative_time, corrected_now, list_item } from '/utils.js';
import { render_math, render_formulas } from '/math.js';
import '/dom.js';

window.Avatar = Avatar;
window.UserBio = UserBio;
window.User = User;
window.OtherUser = OtherUser;
window.Post = Post;
window.Message = Message;
window.Notification = Notification;
window.ReportCard = ReportCard;

function refresh_relative_times(root) {
    (root || document).querySelectorAll('.RelativeTime').forEach((time_element) => {
        if (!time_element.hasAttribute('datetime')) {
            const post = time_element.closest('.Post');
            if (!post) {
                return;
            }
            time_element.setAttribute('datetime', post.dataset.createdAt);
        }
        time_element.textContent = format_relative_time(time_element.getAttribute('datetime'));
    });
}

function csrf_headers(extra) {
    return Object.assign({ 'X-CSRF-Token': window.CSRFToken }, extra || {});
}

document.addEventListener('DOMContentLoaded', () => {
    refresh_relative_times();
    setInterval(() => refresh_relative_times(), 60000);
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.needsMath) {
        render_math(document.body);
    }
});

// ---------- Confirm / prompt dialogs ----------

let active_confirm_cancel = null;

function show_confirm(message) {
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
        cancel_button.className = 'Button ConfirmDialogCancelButton';
        cancel_button.textContent = 'Cancel';

        const confirm_button = document.createElement('button');
        confirm_button.type = 'button';
        confirm_button.className = 'Button ConfirmDialogConfirmButton';
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
        cancel_button.className = 'Button ConfirmDialogCancelButton';
        cancel_button.textContent = 'Cancel';

        const confirm_button = document.createElement('button');
        confirm_button.type = 'button';
        confirm_button.className = 'Button ConfirmDialogConfirmButton';
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

// ---------- API helpers ----------

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
        if (error.name !== 'AbortError') {
            Toast.show('Network error. Please check your connection and try again.');
        }

        return null;
    }

    let data = null;

    try {
        data = await response.json();
    } catch (error) {
    }

    if (!response.ok || data === null) {
        Toast.show((data && data.error) || 'Something went wrong. Please try again.');
        return null;
    }

    return data.response;
}

// ---------- Message composer / scroll ----------

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

// ---------- Search input helpers ----------

document.addEventListener('input', (event) => {
    const input = event.target.closest('.SearchInput');

    if (!input) {
        return;
    }

    input.closest('.SearchBox')?.classList.toggle('HasQuery', input.value !== '');
});

// User search
const userSearchInput = document.querySelector('.UserSearchInput');
if (userSearchInput) {
    const userSearchResults = userSearchInput.closest('.UserSearch').querySelector('.UserSearchSection .UserList');
    new Search(userSearchInput, {
        endpoint: '/api/search-users',
        buildRequest: query => ({ q: query }),
        resultsContainer: userSearchResults,
        renderItem: userData => OtherUser.fromData(userData).toElement(),
        enableInfiniteScroll: true,
        countOffset: list => list.querySelectorAll('.OtherUser').length,
        onResponse: (input, data) => {
            const section = input.closest('.UserSearch').querySelector('.UserSearchSection');
            section.querySelector('h2').textContent = input.value.trim() === '' ? 'Suggested Users' : 'User Search Results';
            section.dataset.query = input.value.trim();
            section.dataset.offset = String(data.response.users.length);
        }
    });
}

// Trending-entity prefill
document.addEventListener('DOMContentLoaded', () => {
    const query = new URLSearchParams(window.location.search).get('q');
    const input = document.querySelector('.PostSearchInput');

    if (query === null || !input) {
        return;
    }

    input.value = query;
    input.dispatchEvent(new Event('input', { bubbles: true }));
});

// Post search
const postSearchInput = document.querySelector('.PostSearchInput');
if (postSearchInput) {
    const postSearchResults = document.querySelector('.SearchFeedList');
    new Search(postSearchInput, {
        endpoint: '/api/search-posts',
        buildRequest: query => ({
            q: query,
            userId: postSearchInput.closest('.PostSearch').dataset.userId || ''
        }),
        resultsContainer: postSearchResults,
        renderItem: postData => Post.fromData(postData).toElement(),
        enableInfiniteScroll: true,
        countOffset: list => list.querySelectorAll('.Post').length,
        onBeforeFetch: (input, query) => {
            const searching = query !== '';
            document.querySelector('.SearchFeedSection')?.classList.toggle('Searching', searching);
            document.querySelector('.ProfileFeedSection')?.classList.toggle('Searching', searching);
        },
        onResponse: (input, data) => {
            postSearchResults.dataset.query = input.value.trim();
            postSearchResults.dataset.userId = input.closest('.PostSearch').dataset.userId || '';
        }
    });
}

// Friend search
const friendSearchInput = document.querySelector('.FriendSearchInput');
if (friendSearchInput) {
    const friendSearchResults = document.querySelector('.FriendSearchList');
    new Search(friendSearchInput, {
        endpoint: '/api/search-friends',
        buildRequest: query => ({
            q: query,
            userId: friendSearchInput.closest('.FriendSearch').dataset.userId
        }),
        resultsContainer: friendSearchResults,
        renderItem: userData => OtherUser.fromData(userData).toElement(),
        enableInfiniteScroll: true,
        countOffset: list => list.querySelectorAll('.OtherUser').length,
        onBeforeFetch: (input, query) => {
            const searching = query !== '';
            document.querySelector('.FriendSearchSection')?.classList.toggle('Searching', searching);
            document.querySelector('.PendingFriendRequestSection')?.classList.toggle('Searching', searching);
            document.querySelector('.FriendSection')?.classList.toggle('Searching', searching);
            document.querySelector('.OutgoingFriendRequestSection')?.classList.toggle('Searching', searching);
        },
        onResponse: (input, data) => {
            friendSearchResults.dataset.query = input.value.trim();
        }
    });
}

// Banned user search
const bannedSearchInput = document.querySelector('.BannedUserSearchInput');
if (bannedSearchInput) {
    const bannedResultsContainer = document.querySelector('.BannedUserList');
    new Search(bannedSearchInput, {
        endpoint: query => query ? '/api/search-banned-users' : '/api/banned-history',
        buildRequest: query => {
            if (bannedResultsContainer) bannedResultsContainer.dataset.searchQuery = query;
            return query ? { q: query } : {};
        },
        resultsContainer: bannedResultsContainer,
        renderItem: data => BannedUser.fromData(data).toElement(),
        enableInfiniteScroll: true,
        countOffset: list => list.querySelectorAll('.BannedUser').length,
        onResponse: (input, data) => {
            if (bannedResultsContainer) bannedResultsContainer.dataset.hasMore = data.response.hasMore ? '1' : '0';
            if (data.response.items.length === 0) {
                const notice = document.createElement('p');
                notice.className = 'muted Notice';
                notice.textContent = input.value.trim() === '' ? 'No banned users.' : 'No banned users match that search.';
                bannedResultsContainer.appendChild(list_item(notice));
            }
        }
    });
}

// ---------- Scroll-to-top, slide_out, infinite scroll instances ----------

const SCROLL_TO_TOP_AT = 600;

window.addEventListener('scroll', () => {
    document.querySelector('.ScrollToTopButton')
        ?.classList.toggle('Scrolled', window.scrollY > SCROLL_TO_TOP_AT);
});

const SLIDE_OUT_MS = 200;

function slide_out(element) {
    if (!element) {
        return;
    }

    const item = element.closest('li') || element;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        item.remove();
        return;
    }

    item.style.height = item.getBoundingClientRect().height + 'px';
    item.classList.add('SlidingOut');

    void item.offsetHeight;

    item.style.height = '0';

    setTimeout(() => item.remove(), SLIDE_OUT_MS + 50);
}

// Feed infinite scroll
const feedList = document.querySelector('.FeedList:not(.SearchFeedList)');
if (feedList) {
    new InfiniteScroller(feedList, {
        endpoint: '/api/feed-history',
        buildRequest: offset => {
            const type = feedList.dataset.feedType;
            const req = { feedType: type, offset };
            if (type === 'user') req.userId = feedList.dataset.userId;
            else if (type === 'tag') req.tag = feedList.dataset.tag;
            return req;
        },
        countOffset: list => list.querySelectorAll('.Post').length,
        renderItem: postData => Post.fromData(postData).toElement(),
        threshold: 150,
    });
}

// Bookmarks infinite scroll
const bookmarkList = document.querySelector('.BookmarkList');
if (bookmarkList) {
    new InfiniteScroller(bookmarkList, {
        endpoint: '/api/bookmark-history',
        buildRequest: offset => ({ offset }),
        countOffset: list => list.querySelectorAll('.Post').length,
        renderItem: postData => Post.fromData(postData).toElement(),
    });
}

// Replies infinite scroll
const replyList = document.querySelector('.ReplyList');
if (replyList) {
    new InfiniteScroller(replyList, {
        endpoint: '/api/reply-history',
        buildRequest: offset => ({ parentId: replyList.dataset.parentId, offset }),
        countOffset: list => list.querySelectorAll('.Post').length,
        renderItem: postData => Post.fromData(postData).toElement(),
    });
}

// Friends lists infinite scroll
const friendsList = document.querySelector('.FriendSection .FriendList');
if (friendsList) {
    new InfiniteScroller(friendsList, {
        endpoint: '/api/friend-list-history',
        buildRequest: offset => ({
            listType: friendsList.dataset.listType,
            userId: friendsList.dataset.userId,
            offset
        }),
        countOffset: list => list.querySelectorAll('.OtherUser').length,
        renderItem: data => OtherUser.fromData(data).toElement(),
        threshold: 300
    });
}

const incomingList = document.querySelector('.PendingFriendRequestSection .PendingFriendRequestList');
if (incomingList) {
    new InfiniteScroller(incomingList, {
        endpoint: '/api/friend-list-history',
        buildRequest: offset => ({
            listType: incomingList.dataset.listType,
            userId: incomingList.dataset.userId,
            offset
        }),
        countOffset: list => list.querySelectorAll('.OtherUser').length,
        renderItem: data => FriendRequest.fromData(data).toElement(),
        threshold: 300
    });
}

const outgoingList = document.querySelector('.OutgoingFriendRequestSection .OutgoingFriendRequestList');
if (outgoingList) {
    new InfiniteScroller(outgoingList, {
        endpoint: '/api/friend-list-history',
        buildRequest: offset => ({
            listType: outgoingList.dataset.listType,
            userId: outgoingList.dataset.userId,
            offset
        }),
        countOffset: list => list.querySelectorAll('.OtherUser').length,
        renderItem: data => OtherUser.fromData(data).toElement(),
        threshold: 300
    });
}

// Notifications infinite scroll (the main page list, not the nav dropdown)
const notificationList = Array.from(document.querySelectorAll('.NotificationList'))
    .find(candidate => !candidate.closest('.NotificationDropdown'));

if (notificationList) {
    new InfiniteScroller(notificationList, {
        endpoint: '/api/notification-history',
        buildRequest: offset => ({ offset }),
        countOffset: list => list.querySelectorAll('.Notification').length,
        renderItem: data => Notification.fromData(data).toElement(),
    });
}

// Reports infinite scroll (admin moderation queue)
const reportList = document.querySelector('.ReportList');
if (reportList) {
    new InfiniteScroller(reportList, {
        endpoint: '/api/report-history',
        buildRequest: offset => ({ offset }),
        countOffset: list => list.querySelectorAll('.ReportCard').length,
        renderItem: data => ReportCard.fromData(data).toElement(),
    });
}

// ---------- Post composer ----------

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

// ---------- Quill editor ----------

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

function create_quill_editor(editor_container) {
    const quill = new Quill(editor_container, {
        theme: 'snow',
        placeholder: editor_container.dataset.placeholder,
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ header: 3 }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block', 'code'],
                ['link', 'formula'],
                ['clean'],
            ],
        },
    });

    editor_container.__quill = quill;

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
                }

                Toast.show(error_message);
                return;
            }

            const data = JSON.parse(xhr.responseText);

            form.reset();
            quill.setText('');

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
                notice.className = 'ProcessingNotice muted text-sm';
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

                reply_list.insertBefore(list_item(element), reply_list.firstChild);
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

// Post editing (submit)
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

    const post_element = form.previousElementSibling;
    const link_input = form.querySelector('[name=\'linkURL\']');

    const result = await api_post('/api/edit-post', {
        postId: post_element.dataset.postId,
        title: form.querySelector('[name=\'title\']').value,
        linkURL: link_input ? link_input.value : '',
        description: form.querySelector('.DescriptionInput').value,
    });

    if (result === null) {
        save_button.disabled = false;
        return;
    }

    if (post_element && post_element.classList.contains('Post')) {
        const new_content = Post.fromData(result).postElement();
        post_element.querySelector('.PostContent').replaceWith(new_content);

        post_element.dataset.title = result.title || '';
        post_element.dataset.linkUrl = result.linkURL || '';
        post_element.dataset.descriptionDelta = result.rawDescriptionDelta || '';
        post_element.dataset.hasMedia = result.items.length > 0 ? '1' : '';

        post_element.style.display = '';
        render_math(new_content);
    }

    Toast.show('Changes saved.');

    form.remove();
});

// Password change
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
        Toast.show('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

// Two-factor authentication toggle
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

    Toast.show(now_enabled
        ? 'Two-factor authentication is now on.'
        : 'Two-factor authentication is now off.');
});

// Username validation
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

document.addEventListener('input', (event) => {
    const username_input = event.target.closest('.SignupForm [name=\'username\']');

    if (!username_input) {
        return;
    }

    const status = username_input.closest('.SignupForm').querySelector('.UsernameAvailability');

    if (!status) {
        return;
    }

    clearTimeout(username_input.dataset.debounceId);

    const requested = username_input.value;

    if (requested === '') {
        status.textContent = '';
        status.classList.remove('Error', 'muted');

        return;
    }

    const debounce_id = setTimeout(async () => {
        username_input.availabilityAbortController?.abort();
        const controller = new AbortController();
        username_input.availabilityAbortController = controller;

        let data;

        try {
            const response = await fetch(window.siteURL + '/api/username-available', {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ username: requested }),
                signal: controller.signal,
            });

            if (!response.ok) {
                return;
            }

            data = await response.json();
        } catch (error) {
            return;
        }

        if (username_input.value !== requested) {
            return;
        }

        status.classList.toggle('Error', !data.response.available);
        status.classList.toggle('muted', data.response.available);
        status.textContent = data.response.available
            ? `${data.response.username} is available.`
            : `${data.response.username} is already taken.`;
    }, 300);

    username_input.dataset.debounceId = debounce_id;
});

// Theme selection
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

    if (await api_post('/api/update-theme', { theme }) === null) {
        apply(previous_theme);
        select.value = previous_theme;
    }
});

// Emoji picker (non-click interactions)
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

// Link image preview
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

// Link preview fetch on input
document.addEventListener('input', (event) => {
    const input = event.target.closest('.Composer [name=\'linkURL\']');

    if (!input) {
        return;
    }

    clearTimeout(input.dataset.debounceId);

    const delay = event.inputType === 'insertFromPaste' ? 0 : 500;

    input.dataset.debounceId = setTimeout(async () => {
        const form = input.closest('.Composer');
        const url = input.value.trim();

        if (url === input.dataset.lastFetchedUrl) {
            return;
        }

        input.previewAbortController?.abort();
        const controller = new AbortController();
        input.previewAbortController = controller;

        await discard_staged_link_image(form);

        if (!url) {
            input.dataset.lastFetchedUrl = url;

            return;
        }

        const preview = await api_post('/api/link-preview', { url }, { signal: controller.signal });

        if (!preview || input.value.trim() !== url) {
            return;
        }

        input.dataset.lastFetchedUrl = url;

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

// Profile editing
function profile_enter_edit(card) {
    if (card.classList.contains('Editing')) {
        return;
    }

    card.classList.add('Editing');

    const name_input = document.createElement('input');
    name_input.type = 'text';
    name_input.className = 'DisplayNameInput';
    name_input.maxLength = 50;
    name_input.value = card.dataset.title;
    name_input.placeholder = 'Display name';
    card.querySelector('.DisplayName').replaceWith(name_input);

    const bio_input = document.createElement('textarea');
    bio_input.className = 'UserBioInput';
    bio_input.maxLength = 500;
    bio_input.value = card.dataset.description;
    bio_input.placeholder = 'Add a bio…';
    const bio = card.querySelector('.UserBio');
    bio.replaceWith(bio_input);

    const save = document.createElement('button');
    save.type = 'button';
    save.className = 'Button ProfileSaveButton';
    save.textContent = 'Save';
    bio_input.after(save);

    name_input.focus();
}

async function profile_save(card) {
    const name_input = card.querySelector('.DisplayNameInput');
    const bio_input = card.querySelector('.UserBioInput');
    const save = card.querySelector('.ProfileSaveButton');
    save.disabled = true;

    const data = await api_post('/api/update-profile', {
        title: name_input.value,
        description: bio_input.value,
    });

    if (!data) {
        save.disabled = false;
        return;
    }

    card.dataset.title = data.title || '';
    card.dataset.description = data.description || '';

    const heading = document.createElement('h2');
    heading.className = 'DisplayName';
    heading.textContent = data.title || card.dataset.username;
    name_input.replaceWith(heading);

    bio_input.replaceWith(new UserBio(data).toElement());

    save.remove();
    card.classList.remove('Editing');
    Toast.show('Profile saved.');
}

// Enter key saves profile when editing name
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
        return;
    }

    const name_input = event.target.closest('.DisplayNameInput');

    if (name_input) {
        event.preventDefault();
        profile_save(name_input.closest('.User.CurrentUser'));
    }
});

// Favicon upload
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.FaviconSettingsForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const file_input = form.querySelector('input[type=\'file\'][name=\'favicon\']');

    if (!file_input.files.length) {
        Toast.show('Choose a file first.');
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
            Toast.show(data.error || 'Something went wrong. Please try again.');
            return;
        }

        Toast.show('Settings saved.');
        form.querySelector('.FaviconPreview').src = window.siteURL + '/uploads/site/favicon.png?' + Date.now();
    } catch (error) {
        Toast.show('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

// Password reset
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
        Toast.show('That\'s already your password - nothing was changed.');
        return;
    }

    const notice = document.createElement('p');
    notice.textContent = 'Your password has been reset. You can now log in.';

    const login_link = document.createElement('a');
    login_link.href = window.siteURL + '/login';
    login_link.textContent = 'Log In';

    form.replaceWith(notice, login_link);
});

// Signup
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
        description: form.querySelector('[name=\'description\']').value,
        password: form.querySelector('[name=\'password\']').value,
        rememberMe: form.querySelector('[name=\'rememberMe\']').checked,
        captchaToken: captcha_input ? captcha_input.value : null,
    });

    if (!data) {
        submit_button.disabled = false;
        return;
    }

    window.location = window.siteURL + (data.verified ? '/' : '/check-inbox');
});

// Logout
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.LogoutForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    await api_post('/api/logout');

    window.location = window.siteURL + '/';
});

// Google reCAPTCHA
let recaptcha_api_loading = null;

function load_recaptcha_api() {
    if (window.grecaptcha && window.grecaptcha.render) {
        return Promise.resolve();
    }

    if (recaptcha_api_loading) {
        return recaptcha_api_loading;
    }

    recaptcha_api_loading = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => {
            const wait_for_render = () => {
                if (window.grecaptcha && window.grecaptcha.render) {
                    resolve();
                } else {
                    setTimeout(wait_for_render, 50);
                }
            };

            wait_for_render();
        });
        script.addEventListener('error', () => reject(new Error('reCAPTCHA failed to load')));
        document.head.appendChild(script);
    });

    return recaptcha_api_loading;
}

async function show_login_recaptcha(form, site_key) {
    if (form.recaptchaWidgetId !== undefined) {
        window.grecaptcha.reset(form.recaptchaWidgetId);
        return;
    }

    const notice = document.createElement('p');
    notice.className = 'muted text-sm LoginRecaptchaNotice';
    notice.textContent = 'Too many attempts on this account. Please complete the verification to continue.';

    const container = document.createElement('div');
    container.className = 'LoginRecaptcha';

    const submit_button = form.querySelector('button[type=\'submit\']');
    form.insertBefore(notice, submit_button);
    form.insertBefore(container, submit_button);

    try {
        await load_recaptcha_api();
        form.recaptchaWidgetId = window.grecaptcha.render(container, { sitekey: site_key });
    } catch (error) {
        Toast.show('Could not load the verification. Please try again in a moment.');
    }
}

function reset_login_recaptcha(form) {
    if (form.recaptchaWidgetId !== undefined && window.grecaptcha) {
        window.grecaptcha.reset(form.recaptchaWidgetId);
    }
}

// Login form
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.LoginForm');

    if (!form) {
        return;
    }

    event.preventDefault();

    const submit_button = form.querySelector('button[type=\'submit\']');
    submit_button.disabled = true;

    const captcha_input = form.querySelector('[name=\'cf-turnstile-response\']');

    const recaptcha_token = form.recaptchaWidgetId !== undefined && window.grecaptcha
        ? window.grecaptcha.getResponse(form.recaptchaWidgetId)
        : null;

    const data = await api_post('/api/login', {
        identifier: form.querySelector('[name=\'identifier\']').value,
        password: form.querySelector('[name=\'password\']').value,
        rememberMe: form.querySelector('[name=\'rememberMe\']').checked,
        captchaToken: captcha_input ? captcha_input.value : null,
        recaptchaToken: recaptcha_token || null,
    });

    if (!data) {
        reset_login_recaptcha(form);
        submit_button.disabled = false;
        return;
    }

    if (data.recaptchaRequired) {
        show_login_recaptcha(form, data.recaptchaSiteKey);
        submit_button.disabled = false;
        return;
    }

    if (data.twoFactorRequired) {
        window.location = window.siteURL + '/login';
        return;
    }

    window.location = window.siteURL + '/';
});

// 2FA verification
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

// Forgot password
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

    const notice = document.createElement('p');
    notice.textContent = 'If that email address is on file, a password reset link has been sent. If you don\'t see it, check your junk/spam folder.';
    form.replaceWith(notice);
});

// Change email
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
            Toast.show('That is already your email address.');
            return;
        }

        window.location = window.siteURL + '/check-inbox';
    } catch (error) {
        Toast.show('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

// Account deletion (password-based)
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

        window.location = window.siteURL + '/';
    } catch (error) {
        Toast.show('Network error. Please check your connection and try again.');
    } finally {
        submit_button.disabled = false;
    }
});

// Thumbnail fallback error handler
document.addEventListener('error', function(event) {
    const img = event.target;
    if (img instanceof HTMLImageElement && img.dataset.fullSrc && img.src !== img.dataset.fullSrc) {
        img.src = img.dataset.fullSrc;
        img.removeAttribute('data-full-src');
    }
}, true);

// Dynamic imports for optional modules
if (document.querySelector('.HashtagGraphList')) {
    import('/HashtagGraph.js');
}

if (document.querySelector('.HelpSearchInput')) {
    import('/help.js');
}

// ---------- Click handler functions and configuration ----------

async function sendFriendRequest(button) {
    button.disabled = true;
    try {
        const result = await api_post('/api/friend-request', { userId: button.dataset.userId });
        if (!result) { return; }
        button.dataset.sent = result.sent ? '1' : '0';
        button.textContent = result.sent ? 'Cancel' : 'Add Friend';
    } finally {
        button.disabled = false;
    }
}

async function toggleFollowUser(button) {
    const id = button.dataset.userId;
    const following = button.dataset.following === '1';
    button.disabled = true;
    try {
        const result = await api_post(following ? '/api/unfollow-remote' : '/api/follow-user', { userId: id });
        if (!result) { return; }
        button.dataset.following = result.following ? '1' : '0';
        button.textContent = result.following ? 'Unfollow' : 'Follow';
    } finally {
        button.disabled = false;
    }
}

async function blockUser(button) {
    if (!await show_confirm('Block this user? This will remove any existing friendship.')) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/block', { userId: button.dataset.userId });
        if (!result) { return; }
        slide_out(button.closest('.OtherUser'));
    } finally {
        button.disabled = false;
    }
}

async function removeFriend(button) {
    if (!await show_confirm('Remove this friend?')) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/remove-friend', { userId: button.dataset.userId });
        if (!result) { return; }
        slide_out(button.closest('.OtherUser'));
    } finally {
        button.disabled = false;
    }
}

async function toggleModStatus(button) {
    const id = button.dataset.userId;
    const isMod = button.dataset.isMod === '1';
    button.disabled = true;
    try {
        const result = await api_post('/api/set-mod', { userId: id, isMod: !isMod });
        if (!result) { return; }
        button.dataset.isMod = result.isMod ? '1' : '0';
        button.textContent = result.isMod ? 'Remove Mod' : 'Make Mod';
    } finally {
        button.disabled = false;
    }
}

async function unblockUser(button) {
    const id = button.dataset.userId;
    const card = button.closest('.OtherUser');
    button.disabled = true;
    try {
        const result = await api_post('/api/unblock', { userId: id });
        if (!result) { return; }
        card.replaceWith(OtherUser.fromData(result).toElement());
    } finally {
        button.disabled = false;
    }
}

async function acceptFriendRequest(button) {
    const friendshipId = button.dataset.friendshipId;
    button.disabled = true;
    const result = await api_post('/api/accept-friend', { friendshipId });
    if (!result) {
        button.disabled = false;
        return;
    }
    const card = button.closest('.OtherUser');
    if (card && result.userId) {
        const newCard = OtherUser.fromData(result).toElement();
        const pendingList = card.closest('.UserList[data-list-type="incoming"]');
        if (pendingList) {
            const friendsList = document.querySelector('.UserList[data-list-type="friends"]');
            if (friendsList) {
                friendsList.prepend(list_item(newCard));
            }
            slide_out(card);
            if (pendingList.querySelectorAll('li:not(.SlidingOut) .OtherUser').length === 0) {
                slide_out(pendingList.closest('.UserSection') || pendingList);
            }
        } else {
            card.replaceWith(newCard);
        }
    }
}

async function denyFriendRequest(button) {
    button.disabled = true;
    const result = await api_post('/api/deny-friend', { friendshipId: button.dataset.friendshipId });
    if (!result) {
        button.disabled = false;
        return;
    }
    slide_out(button.closest('.OtherUser'));
}

async function likePost(button) {
    const postData = button.closest('.Post').dataset;
    button.disabled = true;
    try {
        const result = await api_post('/api/like', { itemId: postData.postId });
        if (!result) { return; }
        button.dataset.liked = result.liked ? '1' : '0';
        button.textContent = (result.liked ? 'Unlike' : 'Like') + (result.count > 0 ? ' (' + result.count + ')' : '');
    } finally {
        button.disabled = false;
    }
}

async function bookmarkPost(button) {
    const postData = button.closest('.Post').dataset;
    button.disabled = true;
    try {
        const result = await api_post('/api/bookmark', { itemId: postData.postId });
        if (!result) { return; }
        button.dataset.bookmarked = result.bookmarked ? '1' : '0';
        button.textContent = result.bookmarked ? 'Bookmarked' : 'Bookmark';
    } finally {
        button.disabled = false;
    }
}

async function deletePost(button) {
    if (!await show_confirm('Delete this post?')) { return; }
    const postData = button.closest('.Post').dataset;
    button.disabled = true;
    try {
        const result = await api_post('/api/delete', { itemId: postData.postId });
        if (!result) { return; }
        if (button.dataset.standalone === '1') {
            window.location.href = window.siteURL + '/';
        } else {
            slide_out(button.closest('.Post'));
        }
    } finally {
        button.disabled = false;
    }
}

function startPostEdit(button) {
    const post_element = button.closest('.Post');
    const post = post_element ? post_element.dataset : null;

    if (!post || post.descriptionDelta === undefined) {
        return;
    }

    // Already editing this post - a second click on Edit shouldn't stack a
    // second form.
    if (post_element.nextElementSibling?.classList.contains('PostEditForm')) {
        return;
    }

    post_element.style.display = 'none';

    const form = document.createElement('form');
    form.className = 'PostEditForm Card d-flex flex-column gap-2';

    const fields = document.createElement('fieldset');

    const title_row = document.createElement('div');
    title_row.className = 'PostComposerFields d-flex gap-2';

    const title_input = document.createElement('input');
    title_input.type = 'text';
    title_input.name = 'title';
    title_input.placeholder = 'Title (optional)';
    title_input.maxLength = 255;
    title_input.value = post.title || '';
    title_input.setAttribute('aria-label', 'Title (optional)');
    title_row.appendChild(title_input);

    // A media post never had a link to begin with (create-post.php enforces
    // the same XOR api/edit-post.php does), so there's nothing here to edit.
    if (!post.hasMedia) {
        const link_input = document.createElement('input');
        link_input.type = 'text';
        link_input.name = 'linkURL';
        link_input.placeholder = 'Link (optional)';
        link_input.maxLength = 255;
        link_input.value = post.linkUrl || '';
        link_input.setAttribute('aria-label', 'Link (optional)');
        title_row.appendChild(link_input);
    }

    fields.appendChild(title_row);

    const editor_column = document.createElement('div');
    editor_column.className = 'EditorColumn';

    const editor_container = document.createElement('div');
    editor_container.className = 'QuillEditor';
    editor_container.dataset.placeholder = 'Edit your post...';
    editor_column.appendChild(editor_container);

    fields.appendChild(editor_column);

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
    cancel_button.className = 'Button EditFormCancelButton';
    cancel_button.textContent = 'Cancel';
    actions.appendChild(cancel_button);

    const save_button = document.createElement('button');
    save_button.type = 'submit';
    save_button.className = 'Button';
    save_button.textContent = 'Save';
    actions.appendChild(save_button);

    form.appendChild(actions);

    post_element.insertAdjacentElement('afterend', form);

    const quill = create_quill_editor(editor_container);

    try {
        quill.setContents(post.descriptionDelta ? JSON.parse(post.descriptionDelta) : { ops: [] });
    } catch (error) {
        // Malformed/empty stored Delta - start from an empty editor rather
        // than leaving the form broken.
    }
}

function cancelEditingPost(button) {
    const form = button.closest('.PostEditForm');
    const postElement = form.previousElementSibling;
    form.remove();
    if (postElement && postElement.classList.contains('Post')) {
        postElement.style.display = '';
    }
}

async function reportContent(button) {
    const reason = await show_prompt('Why are you reporting this?', { confirmLabel: 'Report' });
    if (reason === null) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/report', {
            targetType: button.dataset.targetType,
            targetId: button.dataset.targetId,
            reason
        });
        if (!result) { return; }
        button.textContent = 'Reported';
    } finally {
        button.disabled = false;
    }
}

async function banUser(button) {
    const reason = await show_prompt(
        'Ban this user? This hides all their content and blocks their login. They\'ll see this reason on the login form.',
        { confirmLabel: 'Ban', placeholder: 'Reason for ban (required)' }
    );
    if (reason === null) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/ban', { userId: button.dataset.userId, reason });
        if (!result) { return; }
        button.textContent = 'Banned';
    } finally {
        button.disabled = false;
    }
}

async function banTrendingEntity(button) {
    const entityType = button.dataset.entityType;
    const entityValue = button.dataset.entityValue;
    const reason = await show_prompt(
        `Ban "${entityValue}" from trending? It won't be able to trend again until unbanned.`,
        { confirmLabel: 'Ban', placeholder: 'Reason for ban (required)' }
    );
    if (reason === null) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/ban-trending-entity', { entityType, entityValue, reason });
        if (!result) { return; }
        slide_out(button.closest('.TrendingEntityChip'));
    } finally {
        button.disabled = false;
    }
}

async function unbanTrendingEntity(button) {
    const entityType = button.dataset.entityType;
    const entityValue = button.dataset.entityValue;
    if (!await show_confirm(`Unban "${entityValue}"? It will be able to trend again.`)) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/unban-trending-entity', { entityType, entityValue });
        if (!result) { return; }
        slide_out(button.closest('.BannedTrendingEntity'));
    } finally {
        button.disabled = false;
    }
}

async function dismissReport(button) {
    button.disabled = true;
    try {
        const result = await api_post('/api/dismiss-report', { reportId: button.dataset.reportId });
        if (!result) { return; }
        slide_out(button.closest('.ReportCard'));
    } finally {
        button.disabled = false;
    }
}

async function deleteReportedContent(button) {
    if (!await show_confirm('Delete this content permanently? Deleting a post also removes all its replies.')) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/delete-reported-content', { reportId: button.dataset.reportId });
        if (!result) { return; }
        slide_out(button.closest('.ReportCard'));
    } finally {
        button.disabled = false;
    }
}

async function resendVerificationEmail(button) {
    button.disabled = true;
    const result = await api_post('/api/resend-verification');
    if (!result) {
        button.disabled = false;
        return;
    }
    button.textContent = 'Sent!';
}

async function unbanUser(button) {
    if (!await show_confirm('Unban this user? Their content and login work again.')) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/unban', { userId: button.dataset.userId });
        if (!result) { return; }
        slide_out(button.closest('.BannedUser'));
    } finally {
        button.disabled = false;
    }
}

async function revokeSession(button) {
    if (!await show_confirm('Revoke this device? It will be signed out and have to log in again.')) { return; }
    button.disabled = true;
    try {
        const result = await api_post('/api/revoke-session', { tokenId: button.dataset.tokenId });
        if (!result) { return; }
        slide_out(button.closest('.RememberedDevice'));
    } finally {
        button.disabled = false;
    }
}

function clearSearch(button) {
    const input = button.closest('.SearchBox').querySelector('.SearchInput');
    input.value = '';
    input.focus();
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function removeFiles(button) {
    const fileInput = button.closest('.Composer').querySelector('input[type="file"]');
    fileInput.value = '';
    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
}

function removeLinkImage(button) {
    discard_staged_link_image(button.closest('.Composer'));
}

function toggleEmojiPicker(trigger) {
    const panel = trigger.closest('.EmojiPickerButton').querySelector('.EmojiPickerPanel');
    const wasActive = panel.classList.contains('Active');
    document.querySelectorAll('.EmojiPickerPanel.Active').forEach(p => p.classList.remove('Active'));
    if (!wasActive) {
        panel.classList.add('Active');
    }
}

function scrollToTop() {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
}

function startProfileEdit(target) {
    const card = target.closest('.User.CurrentUser');
    if (card && !card.classList.contains('Editing') && !target.closest('a')) {
        profile_enter_edit(card);
    }
}

function saveProfile(saveButton) {
    profile_save(saveButton.closest('.User.CurrentUser'));
}

async function confirmGoogleDelete(button) {
    if (!await show_confirm('Delete your account? Your posts, replies, and messages are gone permanently - this can\'t be undone. You\'ll confirm by signing in with Google.')) {
        return;
    }
    window.location = window.siteURL + '/auth-google?intent=delete';
}

ClickHandler.init([
    { selector: '.FriendRequestButton',                          handler: sendFriendRequest },
    { selector: '.FollowUserButton',                             handler: toggleFollowUser },
    { selector: '.BlockUserButton',                              handler: blockUser },
    { selector: '.RemoveFriendButton',                           handler: removeFriend },
    { selector: '.ModButton',                                    handler: toggleModStatus },
    { selector: '.UnblockUserButton',                            handler: unblockUser },
    { selector: '.AcceptFriendButton',                           handler: acceptFriendRequest },
    { selector: '.DenyFriendButton',                             handler: denyFriendRequest },
    { selector: '.LikeButton',                                   handler: likePost },
    { selector: '.BookmarkButton',                               handler: bookmarkPost },
    { selector: '.DeleteButton',                                 handler: deletePost },
    { selector: '.EditButton',                                   handler: startPostEdit },
    { selector: '.EditFormCancelButton',                         handler: cancelEditingPost },
    { selector: '.ReportButton',                                 handler: reportContent },
    { selector: '.BanButton',                                    handler: banUser },
    { selector: '.BanTrendingEntityButton',                      handler: banTrendingEntity },
    { selector: '.UnbanTrendingEntityButton',                    handler: unbanTrendingEntity },
    { selector: '.DismissReportButton',                          handler: dismissReport },
    { selector: '.DeleteReportedContentButton',                  handler: deleteReportedContent },
    { selector: '.ResendVerificationButton',                     handler: resendVerificationEmail },
    { selector: '.UnbanButton',                                  handler: unbanUser },
    { selector: '.RevokeSessionButton',                          handler: revokeSession },
    { selector: '.ToastCloseButton',                             handler: Toast.dismiss },
    { selector: '.SearchClearButton',                            handler: clearSearch },
    { selector: '.RemoveFilesButton',                            handler: removeFiles },
    { selector: '.RemoveLinkImageButton',                        handler: removeLinkImage },
    { selector: '.EmojiTriggerButton',                           handler: toggleEmojiPicker },
    { selector: '.ScrollToTopButton',                            handler: scrollToTop },
    { selector: '.User.CurrentUser .DisplayName, .User.CurrentUser .UserBio, .User.CurrentUser .EditProfileButton', handler: startProfileEdit },
    { selector: '.ProfileSaveButton',                            handler: saveProfile },
    { selector: '.GoogleDeleteButton',                           handler: confirmGoogleDelete },
]);

// Start the live WebSocket for notifications and messages
const wsManager = new WebSocketManager();
wsManager.init();

// Admin WebSocket client test
const statusLine = document.querySelector('.WebSocketClientStatus');
if (statusLine) {
    wsManager.showStatus(statusLine);
}

const carousel = new CarouselController();
carousel.init();

// Insert after all imports, before any other code in main.js

const BLOCK_TAGS = new Set([
    'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI',
    'DIV', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER',
]);

const nativeAppend = Node.prototype.appendChild;
const nativeInsert = Node.prototype.insertBefore;

function isWhitespaceText(node) {
    return node && node.nodeType === Node.TEXT_NODE && /^\s*$/.test(node.data);
}

function isInlineElement(node) {
    return node && node.nodeType === Node.ELEMENT_NODE && !BLOCK_TAGS.has(node.tagName);
}

// Skip spacing when the node is inside a Quill editor
function insideQuillEditor(node) {
    return node && node.closest && node.closest('.ql-editor');
}

function ensureNewlineBefore(parent, child) {
    const prev = child.previousSibling;
    if (!isWhitespaceText(prev)) {
        nativeInsert.call(parent, document.createTextNode('\n'), child);
    }
}

function ensureNewlineAfter(parent, child) {
    const next = child.nextSibling;
    if (!isWhitespaceText(next)) {
        if (next) {
            nativeInsert.call(parent, document.createTextNode('\n'), next);
        } else {
            nativeAppend.call(parent, document.createTextNode('\n'));
        }
    }
}

function ensureSpaceBetween(parent, prev, next) {
    const prevText = (prev.innerText || prev.textContent || '').trimEnd();
    const nextText = (next.innerText || next.textContent || '').trimStart();
    if (prevText.length > 0 && !/\s$/.test(prevText) &&
        nextText.length > 0 && !/^\s/.test(nextText)) {
        nativeInsert.call(parent, document.createTextNode(' '), next);
    }
}

Node.prototype.appendChild = function (child) {
    const prev = this.lastChild;
    const result = nativeAppend.call(this, child);

    // Never touch anything inside a Quill editor
    if (insideQuillEditor(child) || insideQuillEditor(this)) {
        return result;
    }

    if (child.nodeType === Node.ELEMENT_NODE) {
        if (BLOCK_TAGS.has(child.tagName)) {
            ensureNewlineBefore(this, child);
            ensureNewlineAfter(this, child);
        } else if (isInlineElement(prev) && isInlineElement(child)) {
            ensureSpaceBetween(this, prev, child);
        }
    }
    return result;
};

Node.prototype.insertBefore = function (newNode, refNode) {
    const prev = refNode ? refNode.previousSibling : this.lastChild;
    const result = nativeInsert.call(this, newNode, refNode);

    if (insideQuillEditor(newNode) || insideQuillEditor(this)) {
        return result;
    }

    if (newNode.nodeType === Node.ELEMENT_NODE) {
        if (BLOCK_TAGS.has(newNode.tagName)) {
            ensureNewlineBefore(this, newNode);
            ensureNewlineAfter(this, newNode);
        } else {
            if (isInlineElement(prev) && isInlineElement(newNode)) {
                ensureSpaceBetween(this, prev, newNode);
            }
            if (isInlineElement(newNode) && isInlineElement(refNode)) {
                ensureSpaceBetween(this, newNode, refNode);
            }
        }
    }
    return result;
};
