<?php

declare(strict_types=1);

class Post extends HTMLObject
{
    protected const DESCRIPTION_SUMMARY_MAX_LENGTH = 160;

    public string $tagName = 'div';
    public ?string $class = 'Post';

    public ?int $postId = null;
    public ?int $userId = null;
    public ?int $parentId = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $keywords = null;
    public ?string $linkURL = null;
    public ?string $createdAt = null;

    // Whether a media post's description is truncated (with a "See More" link)
    // rather than shown in full. True in the feed, where a post is a preview;
    // PostPage flips it off so the permalink page shows the whole description.
    public bool $truncateDescription = true;

    /** @var FeedItem[] */
    public array $items = [];

    public ?User $author = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> postId !== null) {
            $this -> attributes['data-post-id'] = (string) $this -> postId;
        }

        if ($this -> parentId !== null) {
            $this -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        if ($this -> userId !== null) {
            $this -> attributes['data-author-id'] = (string) $this -> userId;
        }

        if ($this -> keywords !== null) {
            $this -> attributes['data-keywords'] = $this -> keywords;
        }

        if ($this -> createdAt !== null) {
            $this -> attributes['data-created-at'] = $this -> createdAt;
        }

        if ($this -> author !== null) {
            $this -> contents[] = $this -> authorByline();
        }

        if ($this -> linkURL !== null) {
            $link_image = null;

            foreach ($this -> items as $item) {
                if ($item instanceof ImageItem) {
                    $link_image = $item;
                    break;
                }
            }

            $this -> contents[] = new LinkItem($this -> linkURL, $this -> title, $this -> description, $link_image);
        } else {
            if ($this -> title !== null) {
                $heading = new Heading3();
                $heading -> contents[] = $this -> title;

                if ($this -> postId !== null) {
                    $title_link = new Anchor(ServerURL::absolute('/users/' . $this -> author ?-> username . '/' . $this -> postId));
                    $title_link -> addContents($heading);
                    $this -> contents[] = $title_link;
                } else {
                    $this -> contents[] = $heading;
                }
            }

            foreach ($this -> items as $item) {
                $item -> altText = $this -> imageAltText();
            }

            if (count($this -> items) > 1) {
                $carousel = new Carousel();
                $carousel -> items = $this -> items;
                $this -> contents[] = $carousel;
            } elseif (count($this -> items) === 1) {
                $this -> contents[] = $this -> items[0];
            }

            if ($this -> description !== null) {
                $this -> contents[] = $this -> truncateDescription
                    ? $this -> summarizedDescription()
                    : $this -> fullDescription();
            }
        }

        return parent::toDOM();
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

    protected function fullDescription(): HTMLObject
    {
        $body = new PostBody();
        $body -> addContents($this -> description);

        return $body;
    }

    protected function summarizedDescription(): HTMLObject
    {
        $see_more_url = $this -> postId !== null && $this -> author !== null
            ? ServerURL::absolute('/users/' . $this -> author -> username . '/' . $this -> postId)
            : null;

        $body = new TruncatedPostBody($see_more_url);
        $body -> addContents((string) $this -> description);

        return $body;
    }

    /**
     * Strips markup and collapses whitespace (including newlines - \s matches
     * both) from the description, untruncated.
     */
    protected function plainTextDescription(): string
    {
        $spaced = preg_replace('/<[^>]+>/', ' ', (string) $this -> description);

        return trim(preg_replace('/\s+/', ' ', strip_tags($spaced)));
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

        $name = $this -> author !== null ? ($this -> author -> displayName ?? $this -> author -> username) : null;

        return $name !== null ? 'Photo posted by ' . $name : 'Photo';
    }

    protected function authorByline(): HTMLObject
    {
        $byline = new Div();
        $byline -> class = 'PostByline d-flex align-items-center gap-2';

        $byline -> addContents($this -> author -> header());

        if ($this -> createdAt !== null && $this -> postId !== null) {
            $timestamp_link = new Anchor(ServerURL::absolute('/users/' . $this -> author -> username . '/' . $this -> postId));
            $timestamp_link -> class = 'PostTimestamp Muted text-sm ms-auto';

            $timestamp_link -> addContents(new RelativeTime($this -> createdAt, 'M j, Y'));

            $byline -> addContents($timestamp_link);
        }

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

    public static function fromRowWithItems(array $row): self
    {
        return self::fromRowsWithItems([$row])[0];
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
        $mysqli = Database::connection();

        // Collect the post plus all descendant replies, since the row DELETE
        // cascades through them and their media files would otherwise be
        // orphaned on disk.
        $all_post_ids = [$post_id];
        $frontier = [$post_id];

        while ($frontier !== []) {
            $placeholders = implode(', ', array_fill(0, count($frontier), '?'));

            $children_stmt = mysqli_prepare($mysqli, '
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
');
            mysqli_stmt_bind_param($children_stmt, str_repeat('i', count($frontier)), ...$frontier);
            mysqli_stmt_execute($children_stmt);
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

        $prune_notifications_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Notifications`
    WHERE `postId` IN (' . $post_id_placeholders . ')
');
        mysqli_stmt_bind_param($prune_notifications_stmt, str_repeat('i', count($all_post_ids)), ...$all_post_ids);
        mysqli_stmt_execute($prune_notifications_stmt);

        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Posts`
    WHERE `postId` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 'i', $post_id);
        mysqli_stmt_execute($delete_stmt);

        // Only remove files once the rows are actually gone.
        foreach ($doomed_items as $item) {
            UploadProcessor::deleteForItem((int) $item -> itemId, (string) $item -> itemType);
        }
    }

    /**
     * The public global feed: every top-level post (parentId IS NULL) by a
     * non-banned author, newest first. The site's default feed, not gated by
     * friendship (that's Timeline::rowsForUser, whose shape this mirrors).
     * The single source of truth for what the global feed shows - index.php,
     * rss-feed.php, and api/feed-history.php all go through here so a change
     * to feed visibility is made once, not hand-copied across each.
     * Cursor-paginate by passing the postId of the last post already seen as
     * $before_post_id; omit it for the first page. Returns $limit rows plus a
     * hasMore flag (fetches one extra to detect a next page without a second
     * count query).
     *
     * @return array{rows: array[], hasMore: bool}
     */
    public static function globalFeedRows(int $limit, ?int $before_post_id = null): array
    {
        $mysqli = Database::connection();
        $fetch_limit = $limit + 1;
        $not_banned = 0;

        if ($before_post_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iii', $not_banned, $before_post_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'ii', $not_banned, $fetch_limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
        }

        return ['rows' => $rows, 'hasMore' => $has_more];
    }

    /**
     * Builds Posts (with items and authors attached) for a whole page of
     * rows at once, so a feed load costs a fixed number of queries instead of
     * a few per post.
     *
     * @param array[] $rows
     * @return self[]
     */
    public static function fromRowsWithItems(array $rows): array
    {
        $posts = [];

        foreach ($rows as $row) {
            $posts[] = self::fromRow($row);
        }

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
     * @param int[] $post_ids
     * @return array<int, int> postId => number of direct replies
     */
    public static function replyCountsForPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `parentId`, COUNT(*) AS `replyCount`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
    GROUP BY `parentId`
');
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($post_ids)), ...$post_ids);
        mysqli_stmt_execute($stmt);
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

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `postId`, COUNT(*) AS `likeCount`
    FROM `Likes`
    WHERE `postId` IN (' . $placeholders . ')
    GROUP BY `postId`
');
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($post_ids)), ...$post_ids);
        mysqli_stmt_execute($stmt);
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

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `postId`
    FROM `Likes`
    WHERE `userId` = ? AND `postId` IN (' . $placeholders . ')
');
        mysqli_stmt_bind_param($stmt, 'i' . str_repeat('i', count($post_ids)), $user_id, ...$post_ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $liked = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $liked[(int) $row['postId']] = true;
        }

        return $liked;
    }

    /**
     * The JSON representation used by AJAX endpoints (create-post, feed-history)
     * that feed the client-side Post class. The description is sanitized
     * through the same PostBody pass used for server-rendered pages, since the
     * client drops it into innerHTML.
     */
    public function toPayload(int $reply_count, int $like_count, bool $liked): array
    {
        $sanitized_description = null;

        // toPayload only ever feeds the client-side feed (create-post and
        // feed-history), never the permalink page, so it truncates the
        // description exactly like the server-rendered feed does - the client
        // injects this HTML verbatim, "See More" link included.
        if ($this -> description !== null) {
            $sanitized_description = $this -> summarizedDescription() -> renderInner();
        }

        $items = [];

        foreach ($this -> items as $item) {
            $items[] = [
                'itemType' => $item -> itemType,
                'src' => $item -> srcURL(),
                'image' => $item -> imageURL(),
            ];
        }

        return [
            'postId' => (int) $this -> postId,
            'userId' => (int) $this -> userId,
            'parentId' => $this -> parentId !== null ? (int) $this -> parentId : null,
            'title' => $this -> title,
            'description' => $sanitized_description,
            'linkURL' => $this -> linkURL,
            'createdAt' => $this -> createdAt,
            'items' => $items,
            'imageAltText' => $this -> imageAltText(),
            'replyCount' => $reply_count,
            'likeCount' => $like_count,
            'liked' => $liked,
            'authorUsername' => $this -> author ?-> username,
            'authorDisplayName' => $this -> author ?-> displayName,
            'authorImage' => $this -> author ?-> avatarURL(),
        ];
    }
}
