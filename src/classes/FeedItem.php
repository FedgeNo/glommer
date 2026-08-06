<?php

declare(strict_types=1);

class FeedItem extends Figure
{
    public ?string $class = 'FeedItem';

    public ?int $itemId = null;
    public ?int $postId = null;
    public ?string $type = self::class;
    public ?string $createdAt = null;
    public ?string $altText = null;

    // Where this item's file lives on the server that sent it. Null for
    // anything posted here, which is the common case and the one with bytes
    // on local disk.
    public ?string $remoteURL = null;

    // When true, the real media URL is emitted as data-src (data-poster for a
    // video's thumbnail) instead of src, so the browser doesn't fetch it until
    // the carousel promotes it. Carousel sets this on items past the first few.
    public bool $deferred = false;

    // Set by Post::contentElement() only for a standalone (non-Carousel)
    // single-item post - a Carousel carries its own MediaFullscreenButton
    // once for the whole carousel, so a slide's own FeedItem never sets this
    // (it would otherwise get one fullscreen button per slide).
    public bool $showFullscreenButton = false;

    public function toDOM(): \DOMElement
    {
        // The row's identity, for the post editor: editing an item's alt text
        // has to name which row it means, and the rendered element is the only
        // place the editor can learn that from.
        if ($this -> itemId !== null) {
            $this -> attributes['data-item-id'] = (string) $this -> itemId;
        }

        if ($this -> showFullscreenButton) {
            $this -> contents[] = new MediaFullscreenButton();
        }

        return parent::toDOM();
    }

    /**
     * Remote media is addressed by this item's own id, never by the remote
     * URL: the URL is looked up here, on the server, exactly the way rendering
     * the post looks it up. Nothing a visitor sends chooses what gets fetched,
     * which is the difference between a media proxy and an open one.
     */
    public function srcURL(): string
    {
        if ($this -> remoteURL !== null) {
            return RemoteMedia::proxyURL((int) $this -> itemId);
        }

        return ServerURL::absolute(UploadProcessor::srcPath((int) $this -> itemId, (string) $this -> type));
    }

    /**
     * Nothing generates a thumbnail for remote media - it isn't ours to
     * transcode, and one would mean storing a copy. Callers already treat a
     * missing thumbnail as "use the full file" (an image) or "no poster" (a
     * video).
     */
    public function imageURL(): ?string
    {
        if ($this -> remoteURL !== null) {
            return null;
        }

        $path = UploadProcessor::thumbnailPath((int) $this -> itemId, (string) $this -> type);

        return $path !== null ? ServerURL::absolute($path) : null;
    }

    public static function fromRow(FeedItemData $row): self
    {
        $class = $row -> type ?? static::class;

        if (!is_string($class) || !class_exists($class) || !is_a($class, self::class, true)) {
            throw new Exception('Unknown feed item type: ' . var_export($row -> type, true));
        }

        // Set explicitly, one field at a time - a blind property-copy loop
        // here would risk one day clobbering $item's own constructor-computed
        // $class/$tagName (derived from the real subclass) if FeedItemData
        // ever grew fields with the same names.
        $item = new $class();
        $item -> itemId = $row -> itemId;
        $item -> postId = $row -> postId;
        $item -> type = $row -> type;
        $item -> createdAt = $row -> createdAt;
        $item -> remoteURL = $row -> remoteURL;
        $item -> altText = $row -> altText;

        return $item;
    }

    /** What the remoteURL and altText columns hold, so callers can refuse what won't fit. */
    public const MAX_REMOTE_URL_LENGTH = 500;
    public const MAX_ALT_TEXT_LENGTH = 1000;

    /**
     * Records one attachment on a post that arrived from another server. No
     * bytes are fetched here - only the address of them, which RemoteMedia
     * resolves per request.
     */
    public static function createRemote(int $post_id, string $type, string $remote_url, ?string $alt_text): void
    {
        DB::run('
INSERT INTO `FeedItems` (`postId`, `type`, `remoteURL`, `altText`)
    VALUES (?, ?, ?, ?)
', 'isss', $post_id, $type, $remote_url, $alt_text);
    }

    /**
     * @param int[] $post_ids
     * @return array<int, self[]> postId => that post's items, in itemId order
     */
    public static function itemsForPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $rows = DB::rows('
SELECT *
    FROM `FeedItems`
    WHERE `postId` IN (' . $placeholders . ')
    ORDER BY `postId` ASC, `itemId` ASC
', 'FeedItemData', str_repeat('i', count($post_ids)), ...$post_ids);

        $items_by_post = [];

        foreach ($rows as $row) {
            $items_by_post[(int) $row -> postId][] = self::fromRow($row);
        }

        return $items_by_post;
    }
}
