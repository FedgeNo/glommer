<?php

declare(strict_types=1);

class FeedItem extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'FeedItem';

    public ?int $itemId = null;
    public ?int $postId = null;
    public ?string $itemType = self::class;
    public ?string $createdAt = null;
    public ?string $altText = null;

    // When true, the real media URL is emitted as data-src (data-poster for a
    // video's thumbnail) instead of src, so the browser doesn't fetch it until
    // the carousel promotes it. Carousel sets this on items past the first few.
    public bool $deferred = false;

    public function srcURL(): string
    {
        return ServerURL::absolute(UploadProcessor::srcPath((int) $this -> itemId, (string) $this -> itemType));
    }

    public function imageURL(): ?string
    {
        $path = UploadProcessor::thumbnailPath((int) $this -> itemId, (string) $this -> itemType);

        return $path !== null ? ServerURL::absolute($path) : null;
    }

    public static function fromRow(array $row): self
    {
        $class = $row['itemType'] ?? static::class;

        if (!is_string($class) || !class_exists($class) || !is_a($class, self::class, true)) {
            throw new Exception('Unknown feed item type: ' . var_export($row['itemType'] ?? null, true));
        }

        $item = new $class();

        foreach ($row as $key => $value) {
            $item -> $key = $value;
        }

        return $item;
    }

    public static function itemsForPost(int $post_id): array
    {
        return self::itemsForPosts([$post_id])[$post_id] ?? [];
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

        $stmt = mysqli_prepare(DB::connection(), '
SELECT *
    FROM `FeedItems`
    WHERE `postId` IN (' . $placeholders . ')
    ORDER BY `postId` ASC, `itemId` ASC
');
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($post_ids)), ...$post_ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items_by_post = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $items_by_post[(int) $row['postId']][] = self::fromRow($row);
        }

        return $items_by_post;
    }
}
