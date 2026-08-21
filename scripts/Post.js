import { ClientConfig } from '/scripts/ClientConfig.js';
import { Strings } from '/scripts/Strings.js';
import { User } from '/scripts/User.js';
import { DeltaRenderer } from '/scripts/DeltaRenderer.js';
import { Linkifier } from '/scripts/Linkifier.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { parse_server_date, truncate } from '/scripts/utils.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { EmojiRenderer } from '/scripts/EmojiRenderer.js';
import { InfiniteScroller } from '/scripts/InfiniteScroller.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { enhanceCodeBlocks } from '/scripts/CodeBlockCopy.js';
import { Poll } from '/scripts/Poll.js';
import { PostRepostButton } from '/scripts/PostRepostButton.js';
import { ToggleButton } from '/scripts/ToggleButton.js';
import { SkinTone } from '/scripts/SkinTone.js';
import { Working } from '/scripts/Working.js';

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
    // What the post says it was written in, when its sender said so. Null for
    // anything written here, since there is no way to declare one yet.
    language = null;
    quotedPost = null;
    poll = null;
    // What this reply answers and where its thread began - null on a post that
    // starts one. Mirrors Post.php's ThreadContext.
    threadContext = null;
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
    contentWarning = null;
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

    /** The glyphs the action bar shows, mirroring the PHP button classes. */
    static GLYPHS = {
        share: '📤',
        reply: '💬',
        translate: '🌐',
        showOriginal: '↩️',
        like: '👍',
        repost: '🔁',
        quote: '✍️',
        bookmark: '🔖',
    };

    /** Mirrors PostLikeButton::label() - the two must agree or the button changes shape when pressed. */
    static likeLabel(liked, count) {
        // The reader's own thumb, whichever tone they chose - mirrors
        // PostLikeButton::label(). The thumb is the same whether or not they
        // have liked it; the colour is what says so.
        const thumb = SkinTone.applied(Post.GLYPHS.like, SkinTone.forViewer());

        return count > 0 ? thumb + ' ' + count : thumb;
    }

    /** Mirrors PostLikeButton's aria-label - the name the glyph does not say. */
    static likeName(liked) {
        const words = Strings.for('PostLikeButton', { like: 'Like', unlike: 'Unlike' });

        return liked ? words.unlike : words.like;
    }

    /** Mirrors PostBookmarkButton::label() - its name, since the glyph never changes. */
    static bookmarkLabel(bookmarked) {
        const words = Strings.for('PostBookmarkButton', { remove: 'Remove Bookmark', add: 'Bookmark' });
        return bookmarked ? words.remove : words.add;
    }

    /** Names a glyph-only control for anything not looking at it. */
    static nameIt(element, name) {
        element.setAttribute('aria-label', name);
        element.setAttribute('title', name);
    }

    threadContextToElement() {
        const line = document.createElement('div');
        line.className = 'ThreadContext';

        const words = Strings.for('ThreadContext', {
            response: { before: 'In response to ', after: '' },
            untitled: 'this post',
            jumpToStart: 'Jump to Start',
        });

        const response = document.createElement('span');
        response.appendWithSpace(document.createTextNode(words.response.before || ''));

        const parent = document.createElement('a');
        parent.href = ClientConfig.siteURL() + '/users/' + this.threadContext.parentUsername + '/' + this.threadContext.parentId;
        // Mirrors ThreadContext::labelFor(): the server ships null rather than
        // baking "this post" in at fetch time, so the fallback words are said
        // here, in whichever language this renderer is running in.
        parent.textContent = this.threadContext.parentLabel ?? words.untitled;
        response.appendWithSpace(parent);
        response.appendWithSpace(document.createTextNode(words.response.after || ''));

        line.appendWithSpace(response);

        // Only where the start is somewhere else - see ThreadContext.php.
        if (this.threadContext.rootId && this.threadContext.rootId !== this.threadContext.parentId) {
            const start = document.createElement('a');
            start.className = 'ThreadStartLink';
            start.href = ClientConfig.siteURL() + '/users/' + this.threadContext.rootUsername + '/' + this.threadContext.rootId;
            start.textContent = words.jumpToStart;
            line.appendWithSpace(start);
        }

        return line;
    }

    repostAttributionToElement() {
        const line = document.createElement('div');
        line.className = 'RepostAttribution';

        const words = Strings.for('RepostAttribution', { attribution: { before: '', after: ' reposted' } });

        line.appendWithSpace(document.createTextNode(words.attribution.before || ''));

        const who = document.createElement('a');
        who.href = ClientConfig.siteURL() + '/users/' + this.repostedBy.slug + '/';
        who.textContent = this.repostedBy.title || this.repostedBy.slug;
        line.appendWithSpace(who);

        line.appendWithSpace(document.createTextNode(words.attribution.after || ''));

        return line;
    }

    authorBylineToElement() {
        const byline = document.createElement('header');
        byline.className = 'PostByline';

        byline.appendWithSpace(User.fromData(this.author).header());

        const meta = document.createElement('div');
        meta.className = 'PostMeta';

        if (this.createdAt) {
            const timestamp_link = document.createElement('a');
        timestamp_link.className = 'TimestampLink';
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
            location_link.title = Strings.for('PostClient').mapTitle || '';
            location_link.textContent = this.placeLabel || (this.latitude.toFixed(4) + ', ' + this.longitude.toFixed(4));
            meta.appendWithSpace(location_link);
        }

        if (this.editedAt) {
            const edited_marker = document.createElement('span');
        edited_marker.className = 'PostEditedMarker';
            edited_marker.title = RelativeTime.dateAndTime(this.editedAt);
            edited_marker.textContent = Strings.for('PostEditedMarker', { label: '(edited)' }).label;
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
            image.alt = Strings.for('LinkItem', { alt: 'Link preview image' }).alt;
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
            // Mirrors AudioItem.php: the spectrum goes above the controls, and
            // SpectrumAnalyser.js finds it by sitting beside the player.
            const spectrum = document.createElement('canvas');
            spectrum.className = 'SpectrumAnalyser';
            spectrum.width = 600;
            spectrum.height = 192;
            spectrum.setAttribute('aria-hidden', 'true');
            wrapper.appendWithSpace(spectrum);

            // Loaded here rather than only from main.js: a post can scroll in
            // long after page load, and the guard there ran once. Importing a
            // module twice costs nothing - the second call is the cached one.
            import('/scripts/SpectrumAnalyser.js');

            const audio = document.createElement('audio');
            audio.className = 'Audio';
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
            img.alt = item.altText || this.imageAltText || Strings.for('PostClient').image || '';

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
        button.setAttribute('aria-label', Strings.for('PostClient').fullscreen || '');
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
        summary.textContent = Strings.for('PostClient').sensitiveMedia || '';

        cover.appendWithSpace(summary);
        cover.appendWithSpace(media);

        return cover;
    }

    /**
     * The disclosure ContentWarning.php renders. Unlike the media cover there
     * is no reader preference that opens it: a warning is a sentence about
     * this post in particular, so there is nothing to have decided in advance.
     */
    static contentWarningGate(warning) {
        const gate = document.createElement('details');
        gate.className = 'ContentWarning';

        const summary = document.createElement('summary');
        summary.className = 'ContentWarningSummary';
        summary.textContent = warning;

        gate.appendWithSpace(summary);

        return gate;
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
            prev_button.setAttribute('aria-label', Strings.for('PostClient').previous || '');
            prev_button.textContent = '‹';
            carousel.appendWithSpace(prev_button);

            const next_button = document.createElement('button');
            next_button.type = 'button';
            next_button.className = 'Button CarouselNextButton';
            next_button.setAttribute('aria-label', Strings.for('PostClient').next || '');
            next_button.textContent = '›';
            carousel.appendWithSpace(next_button);

            const counter = document.createElement('div');
            counter.className = 'CarouselCounter';
            counter.textContent = '1 / ' + this.items.length;
            carousel.appendWithSpace(counter);

            const autoplay_button = document.createElement('button');
            autoplay_button.type = 'button';
            autoplay_button.className = 'Button CarouselAutoplayButton';
            autoplay_button.textContent = Strings.for('PostClient').autoplay || '';
            carousel.appendWithSpace(autoplay_button);
        }

        return carousel;
    }

    postElement() {
        const post = document.createElement('div');
        post.className = 'PostContent';

        // Mirrors Post.php - first of all, because a reply read without it is
        // an answer to a question that is not on the page.
        if (this.threadContext) {
            post.appendWithSpace(this.threadContextToElement());
        }

        // Mirrors Post.php - above the byline, because it answers the question
        // the byline raises.
        if (this.repostedBy) {
            post.appendWithSpace(this.repostAttributionToElement());
        }

        if (this.author) {
            post.appendWithSpace(this.authorBylineToElement());
        }

        // Mirrors Post.php: everything the author wrote is built into the
        // warning where there is one, so a spoiler in the words is covered
        // along with one in the pictures. The byline stays outside it.
        const warning = (this.contentWarning || '').trim();
        const target = warning === '' ? post : Post.contentWarningGate(warning);

        if (this.linkURL) {
            target.appendWithSpace(this.linkItemToElement());
        } else {
            if (this.title) {
                const heading = document.createElement('h3');
                heading.textContent = this.title;

                if (this.postId !== null) {
                    const title_link = document.createElement('a');
                    title_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
                    title_link.appendWithSpace(heading);
                    target.appendWithSpace(title_link);
                } else {
                    target.appendWithSpace(heading);
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
                // gets it uncovered, the same as the server would have sent it -
                // and a warning already gated it, so a second cover inside the
                // first would only make them ask twice.
                const cover = this.sensitive && warning === '' && !ClientConfig.get('showSensitiveMedia');

                target.appendWithSpace(cover ? Post.sensitiveCover(media) : media);
            }

            if (this.descriptionDelta) {
                const body = DeltaRenderer.render(this.descriptionDelta, this.customEmoji || {}, !this.remote);

                if (this.descriptionTruncated && this.seeMoreURL) {
                    body.appendWithSpace(DeltaRenderer.seeMoreElement(this.seeMoreURL));
                }

                target.appendWithSpace(body);
            }

            // Mirrors Post.php - under the words, since the poll is what the
            // words are asking about.
            if (this.poll) {
                target.appendWithSpace(Poll.fromData(this.poll).element());
            }
        }

        // Mirrors QuotedPost.php: the quoted material under the commentary,
        // byline and a readable slice, linking to the real thing.
        if (this.quotedPost) {
            const quoted = document.createElement('div');
            quoted.className = 'QuotedPost';

            const byline = document.createElement('p');
            byline.className = 'QuotedPostByline';
            byline.textContent = (this.quotedPost.authorTitle || this.quotedPost.slug) + ' · @' + this.quotedPost.slug;
            quoted.appendWithSpace(byline);

            if (this.quotedPost.title) {
                const title = document.createElement('p');
                title.className = 'QuotedPostTitle';
                title.textContent = this.quotedPost.title;
                quoted.appendWithSpace(title);
            }

            if (this.quotedPost.description) {
                const body = document.createElement('p');
                body.textContent = truncate(
                    this.quotedPost.description,
                    ClientConfig.get('quotedPostMaxLength')
                );
                quoted.appendWithSpace(body);
            }

            const link = document.createElement('a');
            link.className = 'QuotedPostLink';
            link.href = ClientConfig.siteURL() + '/users/' + this.quotedPost.slug + '/' + this.quotedPost.postId;
            link.textContent = Strings.for('QuotedPost', { viewLink: 'View the Quoted Post' }).viewLink;
            quoted.appendWithSpace(link);

            target.appendWithSpace(quoted);
        }

        if (target !== post) {
            post.appendWithSpace(target);
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
            card.dataset.contentWarning = this.contentWarning || '';
        }

        card.appendWithSpace(this.postElement());

        const meta = document.createElement('footer');
        meta.className = 'PostActionBar';

        const actions = document.createElement('div');
        // Mirrors PostActionBar.php - anchored at the start, not the end.
        actions.className = 'PostActionBarActions';

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
            Post.nameIt(share_button, 'Share');
            share_button.textContent = Post.GLYPHS.share;
            actions.appendWithSpace(share_button);
        }

        if (logged_in || this.replyCount > 0) {
            const replies_link = document.createElement('a');
            replies_link.className = 'Button';
            replies_link.href = ClientConfig.siteURL() + '/users/' + this.author.slug + '/' + this.postId;
            replies_link.textContent = this.replyCount === 0
                ? Post.GLYPHS.reply
                : Post.GLYPHS.reply + ' ' + this.replyCount;
            const reply_words = Strings.for('PostActionBar', {
                reply: 'Reply',
                replies: { one: 'Replies ({count})', other: 'Replies ({count})' },
            });

            Post.nameIt(replies_link, this.replyCount === 0
                ? reply_words.reply
                : Strings.plural(reply_words.replies, this.replyCount));
            actions.appendWithSpace(replies_link);
        }

        if (logged_in) {
            // Mirrors PostActionBar.php: offered only when there is body text
            // to translate and the server has a translator configured.
            if (this.translatable) {
                const translate_button = ToggleButton.build(
                    [Post.GLYPHS.translate, Post.GLYPHS.showOriginal],
                    'PostTranslateButton'
                );
                Post.nameIt(translate_button, 'Translate');
                actions.appendWithSpace(translate_button);
            }

            // Sized by what it says - mirrors PostLikeButton.php.
            const like_button = document.createElement('button');
            like_button.type = 'button';
            like_button.className = 'Button PostLikeButton';
            like_button.textContent = Post.likeLabel(this.liked, this.likeCount);
            if (this.liked) like_button.classList.add('Removing');
            like_button.dataset.liked = this.liked ? '1' : '0';
            like_button.setAttribute('aria-pressed', this.liked ? 'true' : 'false');
            Post.nameIt(like_button, Post.likeName(this.liked));
            actions.appendWithSpace(like_button);

            // Not on your own post - passing on your own writing is what your
            // profile is for, and the bar draws the same line.
            if (Number(this.userId) !== Number(ClientConfig.get('currentUserId'))) {
                const repost_button = document.createElement('button');
                repost_button.type = 'button';
                repost_button.className = 'Button PostRepostButton';
                repost_button.textContent = PostRepostButton.label(this.reposted, this.repostCount);
                if (this.reposted) repost_button.classList.add('Removing');
                repost_button.setAttribute('aria-pressed', this.reposted ? 'true' : 'false');
                Post.nameIt(repost_button, PostRepostButton.name(this.reposted));
                actions.appendWithSpace(repost_button);
            }

            // Mirrors PostActionBar.php - Repost's talkative sibling, a link
            // to the quote-composing page.
            const quote_link = document.createElement('a');
            quote_link.className = 'PostQuoteButton Button';
            quote_link.href = ClientConfig.siteURL() + '/quote/' + this.postId;
            quote_link.textContent = Post.GLYPHS.quote;
            Post.nameIt(quote_link, Strings.for('PostQuoteButton', { name: 'Quote' }).name);
            actions.appendWithSpace(quote_link);

            const bookmark_button = document.createElement('button');
            bookmark_button.type = 'button';
            bookmark_button.className = 'Button PostBookmarkButton';
            bookmark_button.textContent = Post.GLYPHS.bookmark;
            if (this.bookmarked) bookmark_button.classList.add('Removing');
            bookmark_button.dataset.bookmarked = this.bookmarked ? '1' : '0';
            bookmark_button.setAttribute('aria-pressed', this.bookmarked ? 'true' : 'false');
            Post.nameIt(bookmark_button, Post.bookmarkLabel(this.bookmarked));
            actions.appendWithSpace(bookmark_button);

            if (Number(this.userId) === Number(ClientConfig.get('currentUserId'))) {
                const edit_button = document.createElement('button');
                edit_button.type = 'button';
                edit_button.className = 'Button PostEditButton';
                edit_button.textContent = Strings.for('PostEditButton', { name: 'Edit' }).name;
                actions.appendWithSpace(edit_button);

                const delete_button = document.createElement('button');
                delete_button.type = 'button';
                delete_button.className = 'Button PostDeleteButton';
                delete_button.textContent = Strings.for('PostDeleteButton', { name: 'Delete' }).name;
                actions.appendWithSpace(delete_button);
            } else if (Number(this.userId) !== 1) {
                const report_button = document.createElement('button');
                report_button.type = 'button';
                report_button.className = 'Button ReportButton PostReportButton';
                report_button.dataset.targetType = 'post';
                report_button.dataset.targetId = this.postId;
                report_button.textContent = Strings.for('ReportButton', { name: 'Report' }).name;
                actions.appendWithSpace(report_button);
            }
        }

        meta.appendWithSpace(actions);

        card.appendWithSpace(meta);

        this.element = card;

        // The content, not the card: the action bar's buttons are emoji too,
        // and they are furniture sized by their own rules rather than anything
        // somebody wrote.
        this.element.querySelectorAll(EmojiRenderer.CONTENT).forEach(content => EmojiRenderer.render(content));

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

    /**
     * One line of translated text, with its links, #tags and @mentions live
     * again. A translation arrives as plain text, so what the body renderer
     * makes clickable has to be found in it a second time - by the same
     * tokenizer, so a tag reads the same translated as it did before.
     */
    static #appendLinkified(paragraph, line) {
        for (const segment of Linkifier.tokenize(line)) {
            const inner = document.createTextNode(segment.text);

            if (segment.type === 'url') {
                paragraph.appendChild(DeltaRenderer.linkedNode(segment.href, inner));
            } else if (segment.type === 'hashtag') {
                paragraph.appendChild(DeltaRenderer.hashtagNode(segment.tag, inner));
            } else if (segment.type === 'mention') {
                paragraph.appendChild(DeltaRenderer.mentionNode(segment.username, inner));
            } else {
                paragraph.appendChild(inner);
            }
        }
    }

    static async #translate(button) {
        const post = button.closest('.Post');
        const body = post.querySelector('.PostBody');
        if (!body) return;

        // Already translated: swap the original back in place.
        if (Post.#originalBodies.has(post)) {
            body.replaceWith(Post.#originalBodies.get(post));
            Post.#originalBodies.delete(post);
            ToggleButton.select(button, Post.GLYPHS.translate);
            button.classList.remove('Removing');
            Post.nameIt(button, 'Translate');
            return;
        }

        Working.start(button);

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

            const newline = String.fromCharCode(10);

            for (const paragraph_text of String(result.body).split(/\n{2,}/)) {
                if (paragraph_text.trim() === '') continue;
                const paragraph = document.createElement('p');

                // A blank line started a new paragraph above; a single newline
                // is a break the author made inside one, and setting the whole
                // paragraph as text would collapse it into a space.
                paragraph_text.trim().split(newline).forEach((line, index) => {
                    if (index > 0) {
                        paragraph.appendChild(document.createElement('br'));
                    }

                    Post.#appendLinkified(paragraph, line);
                });

                translated.appendWithSpace(paragraph);
            }

            const label = document.createElement('p');
        label.className = 'MachineTranslationLabel';
            label.textContent = Strings.for('PostClient').machineTranslation || '';
            translated.appendWithSpace(label);

            Post.#originalBodies.set(post, body);
            body.replaceWith(translated);
            ToggleButton.select(button, Post.GLYPHS.showOriginal);
            // Red, like every other button whose next press undoes what the
            // last one did - the glyph alone cannot say the body is no longer
            // what its author wrote.
            button.classList.add('Removing');
            Post.nameIt(button, 'Show original');
        } finally {
            Working.stop(button);
        }
    }

    static async #like(button) {
        const postData = button.closest('.Post').dataset;
        Working.start(button);
        try {
            const result = await Api.post('/api/like', { itemId: postData.postId });
            if (!result) return;
            button.dataset.liked = result.liked ? '1' : '0';
            button.classList.toggle('Removing', result.liked);
            button.setAttribute('aria-pressed', result.liked ? 'true' : 'false');
            Post.nameIt(button, Post.likeName(result.liked));
            button.textContent = Post.likeLabel(result.liked, result.count);
        } finally {
            Working.stop(button);
        }
    }

    static async #bookmark(button) {
        const postData = button.closest('.Post').dataset;
        Working.start(button);
        try {
            const result = await Api.post('/api/bookmark', { itemId: postData.postId });
            if (!result) return;
            button.dataset.bookmarked = result.bookmarked ? '1' : '0';
            button.classList.toggle('Removing', result.bookmarked);
            button.setAttribute('aria-pressed', result.bookmarked ? 'true' : 'false');
            Post.nameIt(button, Post.bookmarkLabel(result.bookmarked));
        } finally {
            Working.stop(button);
        }
    }

    static async #delete(button) {
        if (!await Dialog.confirm(Strings.for('PostClient').deleteConfirm || '')) return;
        const postData = button.closest('.Post').dataset;
        Working.start(button);
        try {
            const result = await Api.post('/api/delete', { itemId: postData.postId });
            if (!result) return;
            if (button.dataset.standalone === '1') {
                window.location.href = ClientConfig.siteURL() + '/';
            } else {
                DOMUtils.slideOut(button.closest('.Post'));
            }
        } finally {
            Working.stop(button);
        }
    }

}

ReadyHandler.add(Post.init);
