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
                    $title_link = new Anchor(URL::absolute('/users/' . $this -> author ?-> username . '/' . $this -> postId));
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
                $this -> contents[] = $this -> hasVisualMedia()
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
        $full_text = $this -> plainTextDescription();
        $short_text = $this -> shortDescription();

        $body = new Paragraph();
        $body -> class = 'PostBody';

        if ($short_text === $full_text) {
            $body -> contents[] = $full_text;

            return $body;
        }

        $body -> contents[] = $short_text . ' ';
        $body -> addContents(new Anchor(URL::absolute('/users/' . $this -> author ?-> username . '/' . $this -> postId), 'See More...'));

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
            $timestamp_link = new Anchor(URL::absolute('/users/' . $this -> author -> username . '/' . $this -> postId));
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

        if ($this -> description !== null) {
            $body = new PostBody();
            $body -> addContents($this -> description);
            $sanitized_description = $body -> renderInner();
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
