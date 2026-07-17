<?php

declare(strict_types=1);

class Post extends HTMLObject
{
    protected const DESCRIPTION_SUMMARY_MAX_LENGTH = 160;

    // A post (and a reply, which is just a post with a parentId) is a
    // self-contained, independently distributable item of content - the textbook
    // <article>. The .Post/.Card styling is class-based, so the tag is free to
    // carry the right semantics.
    public string $tagName = 'article';
    public ?string $class = 'Post Card';

    public ?int $postId = null;
    public ?int $userId = null;
    public ?int $parentId = null;
    public ?string $title = null;
    // The derived plaintext form (the "document": <meta>/OG description, RSS
    // summary, FULLTEXT search). The rich content lives in descriptionDelta.
    public ?string $description = null;
    // The complete Quill Delta (JSON), the source both renderers build from.
    public ?string $descriptionDelta = null;
    public ?string $keywords = null;
    public ?string $linkURL = null;
    public ?string $createdAt = null;
    // Set the first time the author edits this post (api/edit-post.php) -
    // null for a never-edited post. Shown as a small "(edited)" marker next
    // to the timestamp; there's no edit history, just this one flag.
    public ?string $editedAt = null;
    // Set once a moderator dismisses a report on this post - blocks it from
    // being reported again (see api/report.php).
    public ?int $reportsDismissed = null;

    // Whether a media post's description is truncated (with a "See More" link)
    // rather than shown in full. True in the feed, where a post is a preview;
    // PostPage flips it off so the permalink page shows the whole description.
    public bool $truncateDescription = true;

    /** @var FeedItem[] */
    public array $items = [];

    public ?User $author = null;

    // Set only by BookmarkList's query (a JOIN column, not a Posts column) -
    // when this user bookmarked the post, for that list's cursor and sort
    // order. Null everywhere else.
    public ?string $bookmarkedAt = null;

    // The engagement counts the action bar shows, batch-attached for a whole
    // page at once by withItemsAndCounts(). Null on a bare Post (e.g. a report
    // snapshot) rendered with showActions off, where the bar - and these - are
    // never used; the action bar falls back to its own per-post lookups when a
    // count is null but a bar is still shown (a one-off standalone post).
    public ?int $replyCount = null;
    public ?int $likeCount = null;
    public ?bool $liked = null;
    public ?bool $bookmarked = null;

    // The permalink shows one focused post: its Delete redirects home rather
    // than removing a card in place (PostActionBar reads this). standalone
    // pages also render the description untruncated (truncateDescription off).
    public bool $standalone = false;

    // A report snapshot embeds only the post's content, no action bar; every
    // feed/permalink render leaves this on so the bar appears.
    public bool $showActions = true;

    public function toDOM(): \DOMElement
    {
        // The permalink shows one post in full - its body isn't height-capped
        // the way a feed card's is (see .Post:not(.PostStandalone) .PostBody).
        if ($this -> standalone) {
            $this -> class .= ' PostStandalone';
        }

        $this -> contents[] = $this -> contentElement();

        // A report snapshot embeds the content alone; every feed/permalink post
        // carries its action bar (like/reply/bookmark/edit/delete/report).
        if ($this -> showActions) {
            $action_bar = new PostActionBar();
            $action_bar -> postId = (int) $this -> postId;
            $action_bar -> postUserId = (int) $this -> userId;
            $action_bar -> postUsername = $this -> author ?-> slug;
            $action_bar -> replyCount = $this -> replyCount;
            $action_bar -> likeCount = $this -> likeCount;
            $action_bar -> liked = $this -> liked;
            $action_bar -> bookmarked = $this -> bookmarked;
            $action_bar -> standalone = $this -> standalone;

            $this -> contents[] = $action_bar;
        }

        return parent::toDOM();
    }

    /**
     * The post's content on its own - byline, media/link, title, body - as a
     * .PostContent element, without the surrounding card or action bar. The
     * feed/permalink card (toDOM) wraps this plus an action bar; a report
     * snapshot and the client-side edit swap render just this piece.
     */
    public function contentElement(): HTMLObject
    {
        $content = new Div();
        $content -> class = 'PostContent';

        if ($this -> postId !== null) {
            $content -> attributes['data-post-id'] = (string) $this -> postId;
        }

        if ($this -> parentId !== null) {
            $content -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        if ($this -> userId !== null) {
            $content -> attributes['data-author-id'] = (string) $this -> userId;
        }

        if ($this -> keywords !== null) {
            $content -> attributes['data-keywords'] = $this -> keywords;
        }

        if ($this -> createdAt !== null) {
            $content -> attributes['data-created-at'] = $this -> createdAt;
        }

        // The raw, untruncated Delta an edit needs to repopulate Quill -
        // toPayload()'s descriptionDelta is truncated for feed display, so
        // editing needs this separately. Only present for the viewer's own
        // post: nobody else can ever open the edit form, and everyone else's
        // feed shouldn't ship data they'll never use. Data, not markup - the
        // client reads it and feeds it straight to Quill.setContents(), no
        // HTML crosses the wire.
        if ($this -> userId !== null && Auth::id() === $this -> userId) {
            $content -> attributes['data-description-delta'] = $this -> descriptionDelta ?? '';
            $content -> attributes['data-edit-title'] = $this -> title ?? '';
            $content -> attributes['data-edit-link-url'] = $this -> linkURL ?? '';

            // The edit form hides the Link field for a media post: attached
            // media and a link are mutually exclusive (api/edit-post.php
            // enforces the same XOR create-post.php always has), and a media
            // post never had a link to begin with, so there's nothing to edit.
            $content -> attributes['data-has-media'] = count($this -> items) > 0 ? '1' : '';
        }

        if ($this -> author !== null) {
            $content -> contents[] = $this -> authorByline();
        }

        if ($this -> linkURL !== null) {
            $link_image = null;

            foreach ($this -> items as $item) {
                if ($item instanceof ImageItem) {
                    $link_image = $item;
                    break;
                }
            }

            $content -> contents[] = new LinkItem($this -> linkURL, $this -> title, $this -> description, $link_image);
        } else {
            if ($this -> title !== null) {
                $heading = new Heading3();
                $heading -> contents[] = $this -> title;

                if ($this -> postId !== null && $this -> author !== null) {
                    $title_link = new Anchor(ServerURL::absolute('/users/' . $this -> author -> slug . '/' . $this -> postId));
                    $title_link -> addContent($heading);
                    $content -> contents[] = $title_link;
                } else {
                    $content -> contents[] = $heading;
                }
            }

            foreach ($this -> items as $item) {
                $item -> altText = $this -> imageAltText();
            }

            if (count($this -> items) > 1) {
                $carousel = new Carousel();
                $carousel -> items = $this -> items;
                $content -> contents[] = $carousel;
            } elseif (count($this -> items) === 1) {
                $content -> contents[] = $this -> items[0];
            }

            if ($this -> descriptionDelta !== null) {
                $content -> contents[] = $this -> truncateDescription
                    ? $this -> summarizedDescription()
                    : $this -> fullDescription();
            }
        }

        return $content;
    }

    protected function hasVisualMedia(): bool
    {
        foreach ($this -> items as $item) {
            if ($item instanceof ImageItem || $item instanceof VideoItem) {
                return true;
            }
        }

        return false;
    }

    /** @return array[] the stored Delta's ops (empty if there's no rich content) */
    protected function descriptionOps(): array
    {
        return Delta::decode($this -> descriptionDelta);
    }

    /** The permalink to this post, used as the "See More" target and RSS/link. */
    protected function seeMoreURL(): ?string
    {
        return $this -> postId !== null && $this -> author !== null
            ? ServerURL::absolute('/users/' . $this -> author -> slug . '/' . $this -> postId)
            : null;
    }

    protected function fullDescription(): HTMLObject
    {
        return new DeltaRenderer($this -> descriptionOps());
    }

    protected function summarizedDescription(): HTMLObject
    {
        return new TruncatedDeltaRenderer($this -> descriptionOps(), $this -> seeMoreURL());
    }

    /**
     * Collapses whitespace in the description, untruncated. The column is
     * already plaintext (Delta::plainText derives it), so - unlike before the
     * Delta migration - there's no markup to strip; doing so would eat literal
     * '<'/'>' a user legitimately typed (or LaTeX like "$x < y$").
     */
    protected function plainTextDescription(): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $this -> description));
    }

    /**
     * The stripped, truncated description shown alongside a post's image(s)
     * or video in the feed - also reused as those images' alt text (via
     * imageAltText()) and as an RSS item's fallback title (via RSSItem)
     * rather than duplicating the truncation logic.
     */
    public function shortDescription(): string
    {
        $text = $this -> plainTextDescription();

        if (mb_strlen($text) <= self::DESCRIPTION_SUMMARY_MAX_LENGTH) {
            return $text;
        }

        // mb_substr, not substr - a byte-based cut can split a multibyte
        // character, and the resulting invalid UTF-8 makes json_encode()
        // return false for any payload this ends up in (alt text travels
        // through the create-post and feed-history JSON responses).
        return rtrim(mb_substr($text, 0, self::DESCRIPTION_SUMMARY_MAX_LENGTH)) . '...';
    }

    /**
     * Alt text for this post's attached image(s). All images in a multi-image
     * carousel share the same alt text, since there's no per-image caption to
     * draw a distinct one from.
     */
    protected function imageAltText(): string
    {
        $text = $this -> description !== null ? $this -> shortDescription() : '';

        if ($text !== '') {
            return $text;
        }

        $name = $this -> author !== null ? ($this -> author -> title ?: $this -> author -> slug) : null;

        return $name !== null ? 'Photo posted by ' . $name : 'Photo';
    }

    protected function authorByline(): HTMLObject
    {
        $byline = new Div();
        $byline -> class = 'PostByline d-flex align-items-start gap-2';

        $byline -> addContent($this -> author -> header());
        $byline -> addContent(new PostMeta($this));

        return $byline;
    }

    public static function fromRow(array $row): self
    {
        $post = new self();

        foreach ($row as $key => $value) {
            $post -> $key = $value;
        }

        return $post;
    }

    public static function fromRowWithItems(self $post): self
    {
        return self::fromRowsWithItems([$post])[0];
    }

    /**
     * Deletes a post and (via the parentId cascade) its whole reply subtree,
     * cleaning up every descendant's media files - which the row cascade can't
     * do. The single place a post is destroyed, used both by the owner's own
     * delete and by a moderator deleting reported content. Caller is
     * responsible for the authorization check.
     */
    public static function delete(int $post_id): void
    {
        // Collect the post plus all descendant replies, since the row DELETE
        // cascades through them and their media files would otherwise be
        // orphaned on disk.
        $all_post_ids = [$post_id];
        $frontier = [$post_id];

        while ($frontier !== []) {
            $placeholders = implode(', ', array_fill(0, count($frontier), '?'));

            $children_stmt = DB::run('
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
', str_repeat('i', count($frontier)), ...$frontier);
            $children_result = mysqli_stmt_get_result($children_stmt);

            $frontier = [];

            while ($row = mysqli_fetch_assoc($children_result)) {
                $all_post_ids[] = (int) $row['postId'];
                $frontier[] = (int) $row['postId'];
            }
        }

        $doomed_items = [];

        foreach (FeedItem::itemsForPosts($all_post_ids) as $post_items) {
            foreach ($post_items as $item) {
                $doomed_items[] = $item;
            }
        }

        // Notifications.postId carries no FK (it's a loose, per-type
        // reference - not every notification type even uses it - not a
        // strict single-table FK candidate), so nothing cascades these on
        // its own. Without this, a reply/like/postReady notification for a
        // deleted post would point at a 404'ing permalink forever.
        $post_id_placeholders = implode(', ', array_fill(0, count($all_post_ids), '?'));

        DB::run('
DELETE
    FROM `Notifications`
    WHERE `postId` IN (' . $post_id_placeholders . ')
', str_repeat('i', count($all_post_ids)), ...$all_post_ids);

        DB::run('
DELETE
    FROM `Posts`
    WHERE `postId` = ?
', 'i', $post_id);

        // Only remove files once the rows are actually gone.
        foreach ($doomed_items as $item) {
            UploadProcessor::deleteForItem((int) $item -> itemId, (string) $item -> type);
        }
    }

    /**
     * Attaches items and authors (batched, one query each rather than a pair
     * per post) to a whole page of already-built Posts at once.
     *
     * @param self[] $posts
     * @return self[]
     */
    public static function fromRowsWithItems(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        $post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);
        $items_by_post = FeedItem::itemsForPosts($post_ids);

        $user_ids = array_values(array_unique(array_map(fn ($post) => (int) $post -> userId, $posts)));
        $authors = User::loadMany($user_ids);

        foreach ($posts as $post) {
            $post -> items = $items_by_post[(int) $post -> postId] ?? [];
            $post -> author = $authors[(int) $post -> userId] ?? null;
        }

        return $posts;
    }

    /**
     * A whole page of feed-ready Posts: items and authors (fromRowsWithItems)
     * plus the engagement counts the action bar shows, every lookup batched
     * into one query rather than a handful per post. This is what a feed list
     * fetches straight into its contents.
     *
     * @param self[] $posts
     * @return self[]
     */
    public static function withItemsAndCounts(array $posts): array
    {
        $posts = self::fromRowsWithItems($posts);

        if ($posts === []) {
            return [];
        }

        $post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);

        $reply_counts = self::replyCountsForPosts($post_ids);
        $like_counts = self::likeCountsForPosts($post_ids);
        $liked = Auth::check() ? self::likedByUserForPosts($post_ids, (int) Auth::id()) : [];
        $bookmarked = Auth::check() ? Bookmark::bookmarkedByUserForPosts($post_ids, (int) Auth::id()) : [];

        foreach ($posts as $post) {
            $post_id = (int) $post -> postId;

            $post -> replyCount = $reply_counts[$post_id] ?? 0;
            $post -> likeCount = $like_counts[$post_id] ?? 0;
            $post -> liked = $liked[$post_id] ?? false;
            $post -> bookmarked = $bookmarked[$post_id] ?? false;
        }

        return $posts;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, int> postId => number of direct replies
     */
    public static function replyCountsForPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $stmt = DB::run('
SELECT `parentId`, COUNT(*) AS `replyCount`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
    GROUP BY `parentId`
', str_repeat('i', count($post_ids)), ...$post_ids);
        $result = mysqli_stmt_get_result($stmt);

        $counts = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $counts[(int) $row['parentId']] = (int) $row['replyCount'];
        }

        return $counts;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, int> postId => number of likes
     */
    public static function likeCountsForPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $stmt = DB::run('
SELECT `postId`, COUNT(*) AS `likeCount`
    FROM `Likes`
    WHERE `postId` IN (' . $placeholders . ')
    GROUP BY `postId`
', str_repeat('i', count($post_ids)), ...$post_ids);
        $result = mysqli_stmt_get_result($stmt);

        $counts = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $counts[(int) $row['postId']] = (int) $row['likeCount'];
        }

        return $counts;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, true> postId => true for each post $user_id has liked
     */
    public static function likedByUserForPosts(array $post_ids, int $user_id): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $stmt = DB::run('
SELECT `postId`
    FROM `Likes`
    WHERE `userId` = ? AND `postId` IN (' . $placeholders . ')
', 'i' . str_repeat('i', count($post_ids)), $user_id, ...$post_ids);
        $result = mysqli_stmt_get_result($stmt);

        $liked = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $liked[(int) $row['postId']] = true;
        }

        return $liked;
    }

    /**
     * The JSON representation used by AJAX endpoints (create-post, feed-history)
     * that feed the client-side Post class, which rebuilds the body from the
     * Delta ops via render_delta() - no HTML crosses the wire.
     */
    public function toPayload(int $reply_count, int $like_count, bool $liked, bool $bookmarked): array
    {
        $description_delta = null;
        $description_truncated = false;

        // toPayload only ever feeds the client-side feed, never the permalink
        // page, so it truncates the ops exactly like the server-rendered feed
        // does - and ships the very same truncated ops (one truncate pass, so
        // the '…' and the truncated flag can't drift). The client renders them
        // and appends its own "See More" when descriptionTruncated is set.
        if ($this -> descriptionDelta !== null) {
            $renderer = new TruncatedDeltaRenderer($this -> descriptionOps(), $this -> seeMoreURL());
            $description_delta = $renderer -> ops();
            $description_truncated = $renderer -> wasTruncated();
        }

        $items = [];

        foreach ($this -> items as $item) {
            $items[] = [
                'itemType' => $item -> type,
                'src' => $item -> srcURL(),
                'image' => $item -> imageURL(),
            ];
        }

        $is_own_post = $this -> userId !== null && Auth::id() === $this -> userId;

        return [
            'postId' => (int) $this -> postId,
            'userId' => (int) $this -> userId,
            'parentId' => $this -> parentId !== null ? (int) $this -> parentId : null,
            'title' => $this -> title,
            // Plaintext, used only by the client's link-preview card (its
            // description is shown as flat text, never rich). A regular post
            // body renders from descriptionDelta instead.
            'description' => $this -> description,
            'descriptionDelta' => $description_delta,
            'descriptionTruncated' => $description_truncated,
            // The raw, untruncated Delta an edit needs to repopulate Quill -
            // owner-only, same reasoning as toDOM()'s data-description-delta.
            'rawDescriptionDelta' => $is_own_post ? $this -> descriptionDelta : null,
            'seeMoreURL' => $this -> seeMoreURL(),
            'linkURL' => $this -> linkURL,
            'createdAt' => $this -> createdAt,
            'editedAt' => $this -> editedAt,
            'items' => $items,
            'imageAltText' => $this -> imageAltText(),
            'replyCount' => $reply_count,
            'likeCount' => $like_count,
            'liked' => $liked,
            'bookmarked' => $bookmarked,
            // A nested user object with row-named keys so post.js builds the
            // byline straight through User.fromData, no field-by-field transcode.
            'author' => $this -> author !== null ? [
                'userId' => (int) $this -> author -> userId,
                'slug' => $this -> author -> slug,
                'title' => $this -> author -> title,
                'image' => $this -> author -> avatarURL(),
            ] : null,
        ];
    }
}
