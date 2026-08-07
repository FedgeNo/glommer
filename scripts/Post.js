import { ClientConfig } from '/scripts/ClientConfig.js';
import { User } from '/scripts/User.js';
import { DeltaRenderer } from '/scripts/DeltaRenderer.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { parse_server_date } from '/scripts/utils.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { EmojiRenderer } from '/scripts/EmojiRenderer.js';
import { InfiniteScroller } from '/scripts/InfiniteScroller.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { enhanceCodeBlocks } from '/scripts/CodeBlockCopy.js';
import { Poll } from '/scripts/Poll.js';
import { PostRepostButton } from '/scripts/PostRepostButton.js';

export class Post {
    postId = null;
    userId = null;
    parentId = null;
    title = null;
    description = null;
    descriptionDelta = null;
    descriptionTruncated = false;
    seeMoreURL = null;
    keywords = null;
    linkURL = null;
    createdAt = null;
    editedAt = null;
    latitude = null;
    longitude = null;
    placeLabel = null;
    translatable = null;
    poll = null;
    repostedBy = null;
    // A post that came from another server - it carries no share button,
    // because the address worth passing on is the original.
    remote = false;
    reposted = false;
    repostCount = 0;
    rawDescriptionDelta = null;
    items = [];
    imageAltText = null;
    sensitive = false;
    replyCount = 0;
    likeCount = 0;
    liked = false;
    bookmarked = false;
    author = null;
    element = null;

    static fromData(data) {
        const post = new Post();
        Object.assign(post, data);
        return post;
    }

    /** Mirrors PostLikeButton::label() - the two must agree or the button rewords itself when pressed. */
    static likeLabel(liked, count) {
        return (liked ? 'Unlike' : 'Like') + (count > 0 ? ' (' + count + ')' : '');
    }

    /** Mirrors PostBookmarkButton::label(). */
    static bookmarkLabel(bookmarked) {
        return bookmarked ? 'Unbookmark' : 'Bookmark';
    }

    repostAttributionToElement() {
        const line = document.createElement('div');
        line.className = 'RepostAttribution';

        const who = document.createElement('a');
        who.href = ClientConfig.siteURL() + '/users/' + this.repostedBy.slug + '/';
        who.textContent = this.repostedBy.title || this.repostedBy.slug;

        line.appendWithSpace(who);
        line.appendWithSpace(document.createTextNode(' reposted'));

        return line;
    }

    authorBylineToElement() {
        const byline = document.createElement('header');
        byline.className = 'PostByline d-flex align-items-start gap-2';

        byline.appendWithSpace(User.fromData(this.author).header());

        const meta = document.createElement('div');
        meta.className = 'PostMeta d-flex flex-column align-items-end ms-auto';

        if (this.createdAt) {
            const timestamp_link = document.createElement('a');
            timestamp_link.className = 'TimestampLink muted text-sm';
            timestamp_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;

            const timestamp = document.createElement('time');
            timestamp.className = 'RelativeTime';
            timestamp.textContent = RelativeTime.format(this.createdAt);
            timestamp_link.appendWithSpace(timestamp);

            meta.appendWithSpace(timestamp_link);
        }

        // Mirrors PostLocationLink.php - the place name the server resolved
        // from its own gazetteer, or coordinates when nowhere is close enough
        // to name, linking to the map opened on where the post was filed.
        if (this.latitude !== null && this.longitude !== null) {
            const location_link = document.createElement('a');
            location_link.className = 'PostLocationLink';
            location_link.href = ClientConfig.siteURL() + '/map?lat=' + encodeURIComponent(this.latitude) + '&lng=' + encodeURIComponent(this.longitude);
            location_link.title = 'Show this place on the map';
            location_link.textContent = this.placeLabel || (this.latitude.toFixed(4) + ', ' + this.longitude.toFixed(4));
            meta.appendWithSpace(location_link);
        }

        if (this.editedAt) {
            const edited_marker = document.createElement('span');
            edited_marker.className = 'PostEditedMarker muted text-sm';
            edited_marker.title = RelativeTime.dateAndTime(this.editedAt);
            edited_marker.textContent = '(edited)';
            meta.appendWithSpace(edited_marker);
        }

        byline.appendWithSpace(meta);

        return byline;
    }

    linkItemToElement() {
        const wrapper = document.createElement('figure');
        wrapper.className = 'FeedItem LinkItem';

        // Always the anchor with target/rel, as LinkItem.php renders it; only the
        // href is withheld for a scheme we won't link to. Defence in depth either
        // way - create/edit-post already refuse anything but http(s).
        const link = document.createElement('a');
        link.target = '_blank';
        link.rel = 'noopener';

        if (DeltaRenderer.isSafeLink(this.linkURL, DeltaRenderer.ALLOWED_LINK_SCHEMES)) {
            link.href = this.linkURL;
        }

        const link_image = this.items.find((item) => item.itemType === 'ImageItem');

        if (link_image) {
            const image = document.createElement('img');
            image.className = 'LinkItemImage';
            image.src = link_image.image;
            image.alt = 'Link preview image';
            link.appendWithSpace(image);
        }

        const text = document.createElement('div');
        text.className = 'LinkItemText';

        if (this.title) {
            const heading = document.createElement('h3');
            heading.textContent = this.title;
            text.appendWithSpace(heading);
        }

        if (this.description) {
            const body = document.createElement('div');
            body.className = 'PostBody';
            body.textContent = this.description;
            text.appendWithSpace(body);
        }

        text.appendWithSpace(document.createTextNode(this.linkURL));
        link.appendWithSpace(text);
        wrapper.appendWithSpace(link);

        return wrapper;
    }

    itemToElement(item, deferred = false) {
        const wrapper = document.createElement('figure');
        wrapper.className = 'FeedItem ' + item.itemType;

        // Mirrors FeedItem.php/ImageItem.php: the row's identity for the post
        // editor, and the raw alt text - distinct from the img's alt below,
        // which falls back to "Image" and so can't be read back as the
        // author's own words.
        if (item.itemId) {
            wrapper.setAttribute('data-item-id', item.itemId);
        }
        if (item.itemType === 'ImageItem' && item.altText) {
            wrapper.setAttribute('data-alt-text', item.altText);
        }

        if (item.itemType === 'VideoItem') {
            const video = document.createElement('video');
            video.controls = true;

            if (deferred) {
                video.dataset.src = item.src;
                if (item.image) {
                    video.dataset.poster = item.image;
                }
            } else {
                video.src = item.src;
                if (item.image) {
                    video.poster = item.image;
                }
            }

            wrapper.appendWithSpace(video);
        } else if (item.itemType === 'AudioItem') {
            const audio = document.createElement('audio');
            audio.controls = true;

            if (deferred) {
                audio.dataset.src = item.src;
            } else {
                audio.src = item.src;
            }

            wrapper.appendWithSpace(audio);
        } else {
            const img = document.createElement('img');
            img.loading = 'lazy';
            img.decoding = 'async';

            // A remote attachment describes itself; ours is described by the
            // post it belongs to. Same order of preference as FeedItem's.
            img.alt = item.altText || this.imageAltText || 'Image';

            // The feed shows the thumbnail and carries the display-size URL for
            // fullscreen to swap in, exactly as ImageItem.php renders it.
            const thumbnail = item.image || item.src;

            if (deferred) {
                img.dataset.src = thumbnail;
            } else {
                img.src = thumbnail;
            }

            img.dataset.fullSrc = item.src;

            wrapper.appendWithSpace(img);
        }

        return wrapper;
    }

    mediaFullscreenButtonElement() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button MediaFullscreenButton';
        button.setAttribute('aria-label', 'Fullscreen');
        button.textContent = '⛶';
        return button;
    }

    /**
     * The disclosure SensitiveMedia.php renders, built the same way: a real
     * <details>, so opening it needs no script of ours.
     */
    static sensitiveCover(media) {
        const cover = document.createElement('details');
        cover.className = 'SensitiveMedia';

        const summary = document.createElement('summary');
        summary.className = 'SensitiveMediaSummary';
        summary.textContent = 'Sensitive media';

        cover.appendWithSpace(summary);
        cover.appendWithSpace(media);

        return cover;
    }

    itemsToCarousel() {
        const carousel = document.createElement('div');
        carousel.className = 'Carousel';

        const track = document.createElement('div');
        track.className = 'CarouselTrack';

        const initial_eager_items = ClientConfig.get('carouselEagerItems');

        this.items.forEach((item, index) => {
            const slide = document.createElement('div');
            slide.className = 'CarouselSlide' + (index === 0 ? ' Active' : '');
            slide.appendWithSpace(this.itemToElement(item, index > initial_eager_items));
            track.appendWithSpace(slide);
        });

        carousel.appendWithSpace(track);
        carousel.appendWithSpace(this.mediaFullscreenButtonElement());

        if (this.items.length > 1) {
            const prev_button = document.createElement('button');
            prev_button.type = 'button';
            prev_button.className = 'Button CarouselPrevButton';
            prev_button.setAttribute('aria-label', 'Previous');
            prev_button.textContent = '‹';
            carousel.appendWithSpace(prev_button);

            const next_button = document.createElement('button');
            next_button.type = 'button';
            next_button.className = 'Button CarouselNextButton';
            next_button.setAttribute('aria-label', 'Next');
            next_button.textContent = '›';
            carousel.appendWithSpace(next_button);

            const counter = document.createElement('div');
            counter.className = 'CarouselCounter';
            counter.textContent = '1 / ' + this.items.length;
            carousel.appendWithSpace(counter);

            const autoplay_button = document.createElement('button');
            autoplay_button.type = 'button';
            autoplay_button.className = 'Button CarouselAutoplayButton';
            autoplay_button.textContent = 'Autoplay';
            carousel.appendWithSpace(autoplay_button);
        }

        return carousel;
    }

    postElement() {
        const post = document.createElement('div');
        post.className = 'PostContent';

        // Mirrors Post.php - above the byline, because it answers the question
        // the byline raises.
        if (this.repostedBy) {
            post.appendWithSpace(this.repostAttributionToElement());
        }

        if (this.author) {
            post.appendWithSpace(this.authorBylineToElement());
        }

        if (this.linkURL) {
            post.appendWithSpace(this.linkItemToElement());
        } else {
            if (this.title) {
                const heading = document.createElement('h3');
                heading.textContent = this.title;

                if (this.postId !== null) {
                    const title_link = document.createElement('a');
                    title_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
                    title_link.appendWithSpace(heading);
                    post.appendWithSpace(title_link);
                } else {
                    post.appendWithSpace(heading);
                }
            }

            let media = null;

            if (this.items.length > 1) {
                media = this.itemsToCarousel();
            } else if (this.items.length === 1) {
                media = this.itemToElement(this.items[0]);
                media.appendWithSpace(this.mediaFullscreenButtonElement());
            }

            if (media) {
                // Mirrors Post.php: a reader who has asked to see this media
                // gets it uncovered, the same as the server would have sent it.
                const cover = this.sensitive && !ClientConfig.get('showSensitiveMedia');

                post.appendWithSpace(cover ? Post.sensitiveCover(media) : media);
            }

            if (this.descriptionDelta) {
                const body = DeltaRenderer.render(this.descriptionDelta, this.customEmoji || {});

                if (this.descriptionTruncated && this.seeMoreURL) {
                    body.appendWithSpace(DeltaRenderer.seeMoreElement(this.seeMoreURL));
                }

                post.appendWithSpace(body);
            }

            // Mirrors Post.php - under the words, since the poll is what the
            // words are asking about.
            if (this.poll) {
                post.appendWithSpace(Poll.fromData(this.poll).element());
            }
        }

        return post;
    }

    toElement() {
        const card = document.createElement('article');
        card.className = 'Post MountIn';

        card.dataset.postId = this.postId;
        card.dataset.userId = this.userId;

        if (this.parentId !== null) {
            card.dataset.parentId = this.parentId;
        }

        if (this.keywords) {
            card.dataset.keywords = this.keywords;
        }

        if (this.createdAt) {
            card.dataset.createdAt = parse_server_date(this.createdAt).toISOString();
        }

        if (Number(this.userId) === Number(ClientConfig.get('currentUserId'))) {
            card.dataset.descriptionDelta = this.rawDescriptionDelta || '';
            card.dataset.title = this.title || '';
            card.dataset.linkUrl = this.linkURL || '';
            // Mirrors Post.php: a link post's preview picture is an item too,
            // and this asks whether the post is a media post rather than
            // whether it holds one.
            card.dataset.hasMedia = this.items.length > 0 && !this.linkURL ? '1' : '';
            // Mirrors Post.php's data-sensitive - without it the edit form
            // opens unchecked on an AJAX-rendered post and saving a typo fix
            // would silently clear the classification.
            card.dataset.sensitive = this.sensitive ? '1' : '';
        }

        card.appendWithSpace(this.postElement());

        const meta = document.createElement('footer');
        meta.className = 'PostActionBar d-flex align-items-center gap-3';

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2 ms-auto';

        const logged_in = ClientConfig.get('currentUserId') !== null;

        // Mirrors PostActionBar.php - the share button leads the bar and is
        // visible to everyone, logged in or not, but never on a post from
        // another server: the address worth passing on is the original, not
        // this server's copy of it.
        if (!this.remote) {
            const share_button = document.createElement('button');
            share_button.type = 'button';
            share_button.className = 'PostShareButton Button';
            share_button.dataset.shareUrl = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
            share_button.textContent = 'Share';
            actions.appendWithSpace(share_button);
        }

        if (logged_in || this.replyCount > 0) {
            const replies_link = document.createElement('a');
            replies_link.className = 'Button';
            replies_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
            replies_link.textContent = this.replyCount === 0 ? 'Reply' : 'Replies (' + this.replyCount + ')';
            actions.appendWithSpace(replies_link);
        }

        if (logged_in) {
            // Mirrors PostActionBar.php: offered only when there is body text
            // to translate and the server has a translator configured.
            if (this.translatable) {
                const translate_button = document.createElement('button');
                translate_button.type = 'button';
                translate_button.className = 'Button PostTranslateButton';
                translate_button.textContent = 'Translate';
                actions.appendWithSpace(translate_button);
            }

            const like_button = document.createElement('button');
            like_button.type = 'button';
            like_button.className = this.liked ? 'Button PostLikeButton Removing' : 'Button PostLikeButton';
            like_button.dataset.liked = this.liked ? '1' : '0';
            like_button.textContent = Post.likeLabel(this.liked, this.likeCount);
            actions.appendWithSpace(like_button);

            // Not on your own post - passing on your own writing is what your
            // profile is for, and the bar draws the same line.
            if (Number(this.userId) !== Number(ClientConfig.get('currentUserId'))) {
                const repost_button = document.createElement('button');
                repost_button.type = 'button';
                repost_button.className = this.reposted ? 'Button PostRepostButton Removing' : 'Button PostRepostButton';
                repost_button.textContent = PostRepostButton.label(this.reposted, this.repostCount);
                actions.appendWithSpace(repost_button);
            }

            const bookmark_button = document.createElement('button');
            bookmark_button.type = 'button';
            bookmark_button.className = this.bookmarked ? 'Button PostBookmarkButton Removing' : 'Button PostBookmarkButton';
            bookmark_button.dataset.bookmarked = this.bookmarked ? '1' : '0';
            bookmark_button.textContent = Post.bookmarkLabel(this.bookmarked);
            actions.appendWithSpace(bookmark_button);

            if (Number(this.userId) === Number(ClientConfig.get('currentUserId'))) {
                const edit_button = document.createElement('button');
                edit_button.type = 'button';
                edit_button.className = 'Button PostEditButton';
                edit_button.textContent = 'Edit';
                actions.appendWithSpace(edit_button);

                const delete_button = document.createElement('button');
                delete_button.type = 'button';
                delete_button.className = 'Button PostDeleteButton';
                delete_button.textContent = 'Delete';
                actions.appendWithSpace(delete_button);
            } else if (Number(this.userId) !== 1) {
                const report_button = document.createElement('button');
                report_button.type = 'button';
                report_button.className = 'Button ReportButton PostReportButton';
                report_button.dataset.targetType = 'post';
                report_button.dataset.targetId = this.postId;
                report_button.textContent = 'Report';
                actions.appendWithSpace(report_button);
            }
        }

        meta.appendWithSpace(actions);

        card.appendWithSpace(meta);

        this.element = card;

        EmojiRenderer.render(this.element);

        const postBody = this.element.querySelector('.PostBody');
        if (postBody && EmojiRenderer.isEmojiOnly(postBody)) {
            this.element.classList.add('emoji-only');
        }

        enhanceCodeBlocks(card);

        return card;
    }

    static init() {
        document.addEventListener('click', (event) => {
            const likeBtn = event.target.closest('.PostLikeButton');
            if (likeBtn) {
                Post.#like(likeBtn);
                return;
            }

            const bookmarkBtn = event.target.closest('.PostBookmarkButton');
            if (bookmarkBtn) {
                Post.#bookmark(bookmarkBtn);
                return;
            }

            const deleteBtn = event.target.closest('.PostDeleteButton');
            if (deleteBtn) {
                Post.#delete(deleteBtn);
                return;
            }

            const translateBtn = event.target.closest('.PostTranslateButton');
            if (translateBtn) {
                Post.#translate(translateBtn);
                return;
            }

        });
        
        Post.enhanceExisting();
    }
    
    static enhanceExisting() {
       document.querySelectorAll('.Post').forEach(card => enhanceCodeBlocks(card));
    }

    /**
     * Each translated post's original body element, so "Show original" is a
     * swap back rather than a re-render - and so the state lives here, never
     * in the DOM.
     */
    static #originalBodies = new WeakMap();

    static async #translate(button) {
        const post = button.closest('.Post');
        const body = post.querySelector('.PostBody');
        if (!body) return;

        // Already translated: swap the original back in place.
        if (Post.#originalBodies.has(post)) {
            body.replaceWith(Post.#originalBodies.get(post));
            Post.#originalBodies.delete(post);
            button.textContent = 'Translate';
            return;
        }

        button.disabled = true;

        try {
            // The reader's own interface language today; the parameter is
            // what lets a translated interface ask for its language later.
            const result = await Api.post('/api/translate-post', {
                postId: Number(post.dataset.postId),
                language: navigator.language || 'en',
            });

            if (!result) return;

            const translated = document.createElement('div');
            translated.className = 'PostBody MachineTranslation';

            for (const paragraph_text of String(result.body).split(/\n{2,}/)) {
                if (paragraph_text.trim() === '') continue;
                const paragraph = document.createElement('p');
                paragraph.textContent = paragraph_text.trim();
                translated.appendWithSpace(paragraph);
            }

            const label = document.createElement('p');
            label.className = 'MachineTranslationLabel muted text-sm';
            label.textContent = 'Machine translation';
            translated.appendWithSpace(label);

            Post.#originalBodies.set(post, body);
            body.replaceWith(translated);
            button.textContent = 'Show original';
        } finally {
            button.disabled = false;
        }
    }

    static async #like(button) {
        const postData = button.closest('.Post').dataset;
        button.disabled = true;
        try {
            const result = await Api.post('/api/like', { itemId: postData.postId });
            if (!result) return;
            button.dataset.liked = result.liked ? '1' : '0';
            button.classList.toggle('Removing', result.liked);
            button.textContent = Post.likeLabel(result.liked, result.count);
        } finally {
            button.disabled = false;
        }
    }

    static async #bookmark(button) {
        const postData = button.closest('.Post').dataset;
        button.disabled = true;
        try {
            const result = await Api.post('/api/bookmark', { itemId: postData.postId });
            if (!result) return;
            button.dataset.bookmarked = result.bookmarked ? '1' : '0';
            button.classList.toggle('Removing', result.bookmarked);
            button.textContent = Post.bookmarkLabel(result.bookmarked);
        } finally {
            button.disabled = false;
        }
    }

    static async #delete(button) {
        if (!await Dialog.confirm('Delete this post?')) return;
        const postData = button.closest('.Post').dataset;
        button.disabled = true;
        try {
            const result = await Api.post('/api/delete', { itemId: postData.postId });
            if (!result) return;
            if (button.dataset.standalone === '1') {
                window.location.href = ClientConfig.siteURL() + '/';
            } else {
                DOMUtils.slideOut(button.closest('.Post'));
            }
        } finally {
            button.disabled = false;
        }
    }

}

ReadyHandler.add(Post.init);
