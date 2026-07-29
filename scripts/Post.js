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
    rawDescriptionDelta = null;
    items = [];
    imageAltText = null;
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

    authorBylineToElement() {
        const byline = document.createElement('div');
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

        if (this.editedAt) {
            const edited_marker = document.createElement('span');
            edited_marker.className = 'muted text-sm PostEditedMarker';
            edited_marker.title = parse_server_date(this.editedAt).toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
            edited_marker.textContent = '(edited)';
            meta.appendWithSpace(edited_marker);
        }

        // Mirrors PostLocationLink.php - coordinates rather than a place name,
        // linking into the nearby feed centred on where the post was filed.
        if (this.latitude !== null && this.longitude !== null) {
            const location_link = document.createElement('a');
            location_link.className = 'PostLocationLink muted text-sm';
            location_link.href = ClientConfig.siteURL() + '/nearby?lat=' + encodeURIComponent(this.latitude) + '&lng=' + encodeURIComponent(this.longitude);
            location_link.title = 'See posts near here';
            location_link.textContent = this.latitude.toFixed(4) + ', ' + this.longitude.toFixed(4);
            meta.appendWithSpace(location_link);
        }

        byline.appendWithSpace(meta);

        return byline;
    }

    linkItemToElement() {
        const wrapper = document.createElement('div');
        wrapper.className = 'FeedItem LinkItem';

        const link_is_safe = DeltaRenderer.isSafeLink(this.linkURL, DeltaRenderer.ALLOWED_LINK_SCHEMES);
        const link = document.createElement(link_is_safe ? 'a' : 'div');

        if (link_is_safe) {
            link.href = this.linkURL;
            link.target = '_blank';
            link.rel = 'noopener';
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
        const wrapper = document.createElement('div');
        wrapper.className = 'FeedItem ' + item.itemType;

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
            img.alt = this.imageAltText || 'Image';

            if (deferred) {
                img.dataset.src = item.src;
            } else {
                img.src = item.src;
            }

            wrapper.appendWithSpace(img);
        }

        return wrapper;
    }

    mediaFullscreenButtonElement() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'MediaFullscreen';
        button.setAttribute('aria-label', 'Fullscreen');
        button.textContent = '⛶';
        return button;
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
            prev_button.className = 'CarouselPrev';
            prev_button.setAttribute('aria-label', 'Previous');
            prev_button.textContent = '‹';
            carousel.appendWithSpace(prev_button);

            const next_button = document.createElement('button');
            next_button.type = 'button';
            next_button.className = 'CarouselNext';
            next_button.setAttribute('aria-label', 'Next');
            next_button.textContent = '›';
            carousel.appendWithSpace(next_button);

            const counter = document.createElement('div');
            counter.className = 'CarouselCounter';
            counter.textContent = '1 / ' + this.items.length;
            carousel.appendWithSpace(counter);

            const autoplay_button = document.createElement('button');
            autoplay_button.type = 'button';
            autoplay_button.className = 'CarouselAutoplay';
            autoplay_button.textContent = 'Autoplay';
            carousel.appendWithSpace(autoplay_button);
        }

        return carousel;
    }

    postElement() {
        const post = document.createElement('div');
        post.className = 'PostContent';

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

            if (this.items.length > 1) {
                post.appendWithSpace(this.itemsToCarousel());
            } else if (this.items.length === 1) {
                const wrapper = this.itemToElement(this.items[0]);
                wrapper.appendWithSpace(this.mediaFullscreenButtonElement());
                post.appendWithSpace(wrapper);
            }

            if (this.descriptionDelta) {
                const body = DeltaRenderer.render(this.descriptionDelta);

                if (this.descriptionTruncated && this.seeMoreURL) {
                    body.appendWithSpace(DeltaRenderer.seeMoreElement(this.seeMoreURL));
                }

                post.appendWithSpace(body);
            }
        }

        return post;
    }

    toElement() {
        const card = document.createElement('div');
        card.className = 'Post Card MountIn';

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
            card.dataset.hasMedia = this.items.length > 0 ? '1' : '';
        }

        card.appendWithSpace(this.postElement());

        const meta = document.createElement('div');
        meta.className = 'PostActionBar d-flex align-items-center gap-3';

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2 ms-auto';

        const logged_in = ClientConfig.get('currentUserId') !== null;

        if (logged_in || this.replyCount > 0) {
            const replies_link = document.createElement('a');
            replies_link.className = 'Button';
            replies_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
            replies_link.textContent = this.replyCount === 0 ? 'Reply' : 'Replies (' + this.replyCount + ')';
            actions.appendWithSpace(replies_link);
        }

        if (logged_in) {
            const like_button = document.createElement('button');
            like_button.type = 'button';
            like_button.className = 'Button LikeButton';
            like_button.dataset.liked = this.liked ? '1' : '0';
            like_button.textContent = (this.liked ? 'Unlike' : 'Like') + ' (' + this.likeCount + ')';
            actions.appendWithSpace(like_button);

            const bookmark_button = document.createElement('button');
            bookmark_button.type = 'button';
            bookmark_button.className = 'Button BookmarkButton';
            bookmark_button.dataset.bookmarked = this.bookmarked ? '1' : '0';
            bookmark_button.textContent = this.bookmarked ? 'Bookmarked' : 'Bookmark';
            actions.appendWithSpace(bookmark_button);

            if (Number(this.userId) === Number(ClientConfig.get('currentUserId'))) {
                const edit_button = document.createElement('button');
                edit_button.type = 'button';
                edit_button.className = 'Button EditButton';
                edit_button.textContent = 'Edit';
                actions.appendWithSpace(edit_button);

                const delete_button = document.createElement('button');
                delete_button.type = 'button';
                delete_button.className = 'Button DeleteButton';
                delete_button.textContent = 'Delete';
                actions.appendWithSpace(delete_button);
            } else if (Number(this.userId) !== 1) {
                const report_button = document.createElement('button');
                report_button.type = 'button';
                report_button.className = 'Button ReportButton';
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
            const likeBtn = event.target.closest('.LikeButton');
            if (likeBtn) {
                Post.#like(likeBtn);
                return;
            }

            const bookmarkBtn = event.target.closest('.BookmarkButton');
            if (bookmarkBtn) {
                Post.#bookmark(bookmarkBtn);
                return;
            }

            const deleteBtn = event.target.closest('.DeleteButton');
            if (deleteBtn) {
                Post.#delete(deleteBtn);
                return;
            }

            const reportBtn = event.target.closest('.ReportButton');
            if (reportBtn) {
                Post.#report(reportBtn);
            }
        });
        
        Post.enhanceExisting();
    }
    
    static enhanceExisting() {
       document.querySelectorAll('.Post').forEach(card => enhanceCodeBlocks(card));
    }

    static async #like(button) {
        const postData = button.closest('.Post').dataset;
        button.disabled = true;
        try {
            const result = await Api.post('/api/like', { itemId: postData.postId });
            if (!result) return;
            button.dataset.liked = result.liked ? '1' : '0';
            button.textContent = (result.liked ? 'Unlike' : 'Like') + (result.count > 0 ? ' (' + result.count + ')' : '');
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
            button.textContent = result.bookmarked ? 'Bookmarked' : 'Bookmark';
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

    static async #report(button) {
        const reason = await Dialog.prompt('Why are you reporting this?', { confirmLabel: 'Report' });
        if (reason === null) return;
        button.disabled = true;
        try {
            const result = await Api.post('/api/report', {
                targetType: button.dataset.targetType,
                targetId: button.dataset.targetId,
                reason
            });
            if (!result) return;
            button.textContent = 'Reported';
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(Post.init);
