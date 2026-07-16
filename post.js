class Post {
    postId = null;
    userId = null;
    parentId = null;
    title = null;
    // Plaintext summary, used only by the link-preview card. A post body renders
    // from descriptionDelta (the Delta ops), truncated in the feed with a
    // "See More" link when descriptionTruncated is set.
    description = null;
    descriptionDelta = null;
    descriptionTruncated = false;
    seeMoreURL = null;
    keywords = null;
    linkURL = null;
    createdAt = null;
    editedAt = null;
    // Owner-only, same reasoning as the server's data-description-delta -
    // the untruncated Delta an edit needs to repopulate Quill.
    rawDescriptionDelta = null;
    items = [];
    imageAltText = null;
    replyCount = 0;
    likeCount = 0;
    liked = false;
    bookmarked = false;
    authorUsername = null;
    authorDisplayName = null;
    authorImage = null;
    element = null;

    static fromData(data) {
        const post = new Post();
        Object.assign(post, data);
        return post;
    }

    authorBylineToElement() {
        const byline = document.createElement('div');
        byline.className = 'PostByline d-flex align-items-center gap-2';

        byline.appendChild(user_header_element(this.authorUsername, this.authorDisplayName, Boolean(this.authorImage), this.authorImage, this.userId));

        if (this.createdAt) {
            const timestamp_link = document.createElement('a');
            timestamp_link.className = 'PostTimestamp Muted text-sm ms-auto';
            timestamp_link.href = window.siteURL + '/users/' + this.authorUsername + '/' + this.postId;

            const timestamp = document.createElement('time');
            timestamp.className = 'RelativeTime';
            timestamp.dateTime = parse_server_date(this.createdAt).toISOString();
            timestamp.textContent = format_relative_time(this.createdAt);
            timestamp_link.appendChild(timestamp);

            byline.appendChild(timestamp_link);
        }

        if (this.editedAt) {
            const edited_marker = document.createElement('span');
            edited_marker.className = 'Muted text-sm PostEditedMarker';
            edited_marker.title = parse_server_date(this.editedAt).toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
            edited_marker.textContent = '(edited)';
            byline.appendChild(edited_marker);
        }

        return byline;
    }

    linkItemToElement() {
        const wrapper = document.createElement('div');
        wrapper.className = 'FeedItem LinkItem';

        const link = document.createElement('a');
        link.href = this.linkURL;
        // Opens in a new tab; rel=noopener keeps the opened (user-submitted)
        // page from reaching back through window.opener.
        link.target = '_blank';
        link.rel = 'noopener';

        const link_image = this.items.find((item) => item.itemType === 'ImageItem');

        if (link_image) {
            const image = document.createElement('img');
            image.className = 'LinkItemImage';
            image.src = link_image.image;
            image.alt = 'Link preview image';
            link.appendChild(image);
        }

        const text = document.createElement('div');
        text.className = 'LinkItemText';

        if (this.title) {
            const heading = document.createElement('h3');
            heading.textContent = this.title;
            text.appendChild(heading);
        }

        // The link card's description is plaintext (a flat summary), so it's a
        // text node - never rich, never a Delta. Mirrors the server LinkItem.
        if (this.description) {
            const body = document.createElement('div');
            body.className = 'PostBody';
            body.textContent = this.description;
            text.appendChild(body);
        }

        text.appendChild(document.createTextNode(this.linkURL));
        link.appendChild(text);
        wrapper.appendChild(link);

        return wrapper;
    }

    itemToElement(item, deferred = false) {
        const wrapper = document.createElement('div');
        wrapper.className = 'FeedItem ' + item.itemType;

        if (item.itemType === 'VideoItem') {
            const video = document.createElement('video');
            video.controls = true;

            // Deferred: stash the real URLs in data-* so the browser doesn't
            // fetch until the carousel promotes this slide (see main.js).
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

            wrapper.appendChild(video);
        } else if (item.itemType === 'AudioItem') {
            const audio = document.createElement('audio');
            audio.controls = true;

            if (deferred) {
                audio.dataset.src = item.src;
            } else {
                audio.src = item.src;
            }

            wrapper.appendChild(audio);
        } else {
            const img = document.createElement('img');
            img.alt = this.imageAltText || 'Image';

            if (deferred) {
                img.dataset.src = item.src;
            } else {
                img.src = item.src;
            }

            wrapper.appendChild(img);
        }

        return wrapper;
    }

    itemsToCarousel() {
        const carousel = document.createElement('div');
        carousel.className = 'Carousel';

        const track = document.createElement('div');
        track.className = 'CarouselTrack';

        // Carousel::INITIAL_EAGER_ITEMS, published as a page global (see
        // Page::create) so this isn't a second hand-kept copy of the number:
        // the first slide plus this many ahead load up front (hence > below,
        // matching Carousel.php), the rest defer until the carousel advances
        // toward them and main.js keeps the buffer filled.
        const initial_eager_items = window.carouselEagerItems;

        this.items.forEach((item, index) => {
            const slide = document.createElement('div');
            slide.className = 'CarouselSlide' + (index === 0 ? ' Active' : '');
            slide.appendChild(this.itemToElement(item, index > initial_eager_items));
            track.appendChild(slide);
        });

        carousel.appendChild(track);

        if (this.items.length > 1) {
            const prev_button = document.createElement('button');
            prev_button.type = 'button';
            prev_button.className = 'CarouselPrev';
            prev_button.setAttribute('aria-label', 'Previous');
            prev_button.textContent = '‹';
            carousel.appendChild(prev_button);

            const next_button = document.createElement('button');
            next_button.type = 'button';
            next_button.className = 'CarouselNext';
            next_button.setAttribute('aria-label', 'Next');
            next_button.textContent = '›';
            carousel.appendChild(next_button);

            const counter = document.createElement('div');
            counter.className = 'CarouselCounter';
            counter.textContent = '1 / ' + this.items.length;
            carousel.appendChild(counter);

            const autoplay_button = document.createElement('button');
            autoplay_button.type = 'button';
            autoplay_button.className = 'CarouselAutoplay';
            autoplay_button.textContent = 'Autoplay';
            carousel.appendChild(autoplay_button);
        }

        return carousel;
    }

    // The bare .Post element (byline + title/media/body, no action bar),
    // mirroring the server-side Post::contentElement(). toElement() wraps this
    // in the .Post card with the action bar; a ReportCard embeds it on its own.
    postElement() {
        const post = document.createElement('div');
        post.className = 'PostContent';
        post.dataset.postId = this.postId;
        post.dataset.authorId = this.userId;

        if (this.parentId !== null) {
            post.dataset.parentId = this.parentId;
        }

        if (this.keywords) {
            post.dataset.keywords = this.keywords;
        }

        if (this.createdAt) {
            post.dataset.createdAt = this.createdAt;
        }

        // Owner-only - what the edit form needs to repopulate Quill, without a
        // round trip for a post already on the page. Gate on ownership alone
        // and mirror the server's `?? ''` (Post::toDOM): rawDescriptionDelta is
        // null both for someone else's post AND for the owner's own bodyless
        // post (a link post with no text), and that second case must still set
        // the attribute or its Edit button goes permanently dead.
        if (Number(this.userId) === Number(window.currentUserId)) {
            post.dataset.descriptionDelta = this.rawDescriptionDelta || '';
            post.dataset.editTitle = this.title || '';
            post.dataset.editLinkUrl = this.linkURL || '';
            post.dataset.hasMedia = this.items.length > 0 ? '1' : '';
        }

        if (this.authorUsername) {
            post.appendChild(this.authorBylineToElement());
        }

        if (this.linkURL) {
            post.appendChild(this.linkItemToElement());
        } else {
            if (this.title) {
                const heading = document.createElement('h3');
                heading.textContent = this.title;

                if (this.postId !== null) {
                    const title_link = document.createElement('a');
                    title_link.href = window.siteURL + '/users/' + this.authorUsername + '/' + this.postId;
                    title_link.appendChild(heading);
                    post.appendChild(title_link);
                } else {
                    post.appendChild(heading);
                }
            }

            if (this.items.length > 1) {
                post.appendChild(this.itemsToCarousel());
            } else if (this.items.length === 1) {
                post.appendChild(this.itemToElement(this.items[0]));
            }

            if (this.descriptionDelta) {
                // Built from the Delta ops - same shape the server renders. The
                // feed ships already-truncated ops; append "See More" to the
                // full post when the server flagged the body as cut.
                const body = render_delta(this.descriptionDelta);

                if (this.descriptionTruncated && this.seeMoreURL) {
                    body.appendChild(see_more_element(this.seeMoreURL));
                }

                post.appendChild(body);
            }
        }

        return post;
    }

    toElement() {
        const card = document.createElement('div');
        card.className = 'Post Card MountIn';

        card.appendChild(this.postElement());

        // Mirrors the server-side PostActionBar: reply link when it's useful,
        // like button when logged in, and delete only on your own posts
        // (report on everyone else's).
        const meta = document.createElement('div');
        meta.className = 'PostActionBar d-flex align-items-center gap-3';

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2 ms-auto';

        const logged_in = window.currentUserId !== null;

        if (logged_in || this.replyCount > 0) {
            const replies_link = document.createElement('a');
            replies_link.className = 'Btn';
            replies_link.href = window.siteURL + '/users/' + this.authorUsername + '/' + this.postId;
            replies_link.textContent = this.replyCount === 0 ? 'Reply' : 'Replies (' + this.replyCount + ')';
            actions.appendChild(replies_link);
        }

        if (logged_in) {
            const like_button = document.createElement('button');
            like_button.type = 'button';
            like_button.className = 'Btn LikeButton';
            like_button.dataset.itemId = this.postId;
            like_button.dataset.liked = this.liked ? '1' : '0';
            like_button.textContent = (this.liked ? 'Unlike' : 'Like') + ' (' + this.likeCount + ')';
            actions.appendChild(like_button);

            const bookmark_button = document.createElement('button');
            bookmark_button.type = 'button';
            bookmark_button.className = 'Btn BookmarkButton';
            bookmark_button.dataset.itemId = this.postId;
            bookmark_button.dataset.bookmarked = this.bookmarked ? '1' : '0';
            bookmark_button.textContent = this.bookmarked ? 'Bookmarked' : 'Bookmark';
            actions.appendChild(bookmark_button);

            if (Number(this.userId) === Number(window.currentUserId)) {
                const edit_button = document.createElement('button');
                edit_button.type = 'button';
                edit_button.className = 'Btn EditButton';
                edit_button.dataset.itemId = this.postId;
                edit_button.textContent = 'Edit';
                actions.appendChild(edit_button);

                const delete_button = document.createElement('button');
                delete_button.type = 'button';
                delete_button.className = 'Btn DeleteButton';
                delete_button.dataset.itemId = this.postId;
                delete_button.textContent = 'Delete';
                actions.appendChild(delete_button);
            } else if (Number(this.userId) !== 1) {
                // The admin's posts can't be reported (the API rejects it -
                // nobody could act on the report anyway).
                const report_button = document.createElement('button');
                report_button.type = 'button';
                report_button.className = 'Btn ReportButton';
                report_button.dataset.targetType = 'post';
                report_button.dataset.targetId = this.postId;
                report_button.textContent = 'Report';
                actions.appendChild(report_button);
            }
        }

        meta.appendChild(actions);

        card.appendChild(meta);

        this.element = card;

        return card;
    }
}
