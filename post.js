class Post {
    postId = null;
    userId = null;
    parentId = null;
    title = null;
    description = null;
    keywords = null;
    linkURL = null;
    createdAt = null;
    items = [];
    imageAltText = null;
    replyCount = 0;
    likeCount = 0;
    liked = false;
    authorUsername = null;
    authorDisplayName = null;
    authorImage = null;
    element = null;

    static fromData(data) {
        const post = new Post();
        Object.assign(post, data);
        return post;
    }

    static fromElement(element) {
        const post = new Post();
        post.element = element;
        post.postId = Number(element.dataset.postId);
        post.userId = Number(element.dataset.authorId);
        post.parentId = element.dataset.parentId ? Number(element.dataset.parentId) : null;
        post.keywords = element.dataset.keywords ?? null;
        post.createdAt = element.dataset.createdAt ?? null;
        return post;
    }

    authorBylineToElement() {
        const byline = document.createElement('div');
        byline.className = 'PostByline d-flex align-items-center gap-2';

        byline.appendChild(user_header_element(this.authorUsername, this.authorDisplayName, Boolean(this.authorImage), this.authorImage, this.userId, false));

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

        return byline;
    }

    /**
     * True for null/empty, and content that only looks non-empty because of
     * markup/whitespace - a Quill editor left "empty" typically still saves
     * as something like "<p><br></p>" rather than an empty string.
     */
    static isBlankDescription(description) {
        if (!description) {
            return true;
        }

        const container = document.createElement('div');
        container.innerHTML = description;

        return container.textContent.replace(/\s+/g, '') === '';
    }

    linkItemToElement() {
        const wrapper = document.createElement('div');
        wrapper.className = 'FeedItem LinkItem';

        const link_image = this.items.find((item) => item.itemType === 'ImageItem');

        if (link_image) {
            const image = document.createElement('img');
            image.className = 'LinkItemImage';
            image.src = link_image.image;
            image.alt = 'Link preview image';
            wrapper.appendChild(image);
        }

        const link = document.createElement('a');
        link.href = this.linkURL;

        if (this.title) {
            const heading = document.createElement('h3');
            heading.textContent = this.title;
            link.appendChild(heading);
        }

        if (!Post.isBlankDescription(this.description)) {
            const body = document.createElement('div');
            body.className = 'PostBody';
            body.innerHTML = this.description;
            link.appendChild(body);
        }

        link.appendChild(document.createTextNode(this.linkURL));
        wrapper.appendChild(link);

        return wrapper;
    }

    itemToElement(item) {
        const wrapper = document.createElement('div');
        wrapper.className = 'FeedItem ' + item.itemType;

        if (item.itemType === 'VideoItem') {
            const video = document.createElement('video');
            video.src = item.src;
            video.controls = true;

            if (item.image) {
                video.poster = item.image;
            }

            wrapper.appendChild(video);
        } else if (item.itemType === 'AudioItem') {
            const audio = document.createElement('audio');
            audio.src = item.src;
            audio.controls = true;
            wrapper.appendChild(audio);
        } else {
            const img = document.createElement('img');
            img.src = item.src;
            img.alt = this.imageAltText || 'Image';
            wrapper.appendChild(img);
        }

        return wrapper;
    }

    itemsToCarousel() {
        const carousel = document.createElement('div');
        carousel.className = 'Carousel';

        const track = document.createElement('div');
        track.className = 'CarouselTrack';

        this.items.forEach((item, index) => {
            const slide = document.createElement('div');
            slide.className = 'CarouselSlide' + (index === 0 ? ' Active' : '');
            slide.appendChild(this.itemToElement(item));
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
        }

        return carousel;
    }

    toElement() {
        const thread = document.createElement('div');
        thread.className = 'Thread Card MountIn';

        const post = document.createElement('div');
        post.className = 'Post';
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

            if (this.description) {
                const body = document.createElement('div');
                body.className = 'PostBody';
                // Pre-sanitized server-side (same PostBody/HTMLCleaner pass used for page loads).
                body.innerHTML = this.description;
                post.appendChild(body);
            }
        }

        thread.appendChild(post);

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

            if (Number(this.userId) === Number(window.currentUserId)) {
                const delete_button = document.createElement('button');
                delete_button.type = 'button';
                delete_button.className = 'Btn DeleteButton';
                delete_button.dataset.itemId = this.postId;
                delete_button.textContent = 'Delete';
                actions.appendChild(delete_button);
            } else {
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

        thread.appendChild(meta);

        this.element = thread;

        return thread;
    }
}
