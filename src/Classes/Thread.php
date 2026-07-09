<?php

declare(strict_types=1);

class Thread extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Thread Card';

    public ?Post $post = null;
    public ?int $replyCount = null;
    public ?int $likeCount = null;
    public ?bool $liked = null;

    public function toDOM(): \DOMElement
    {
        $action_bar = new PostActionBar();
        $action_bar -> postId = (int) $this -> post -> postId;
        $action_bar -> postUserId = (int) $this -> post -> userId;
        $action_bar -> postUsername = $this -> post -> author ?-> username;
        $action_bar -> replyCount = $this -> replyCount;
        $action_bar -> likeCount = $this -> likeCount;
        $action_bar -> liked = $this -> liked;

        $this -> contents[] = $this -> post;
        $this -> contents[] = $action_bar;

        return parent::toDOM();
    }

    public static function fromRow(array $row): self
    {
        return self::fromRows([$row])[0];
    }

    /**
     * Builds Threads for a whole page of Post rows at once, batching the
     * per-post lookups (items, authors, reply counts, like counts, viewer's
     * likes) into one query each.
     *
     * @param array[] $rows
     * @return self[]
     */
    public static function fromRows(array $rows): array
    {
        $posts = Post::fromRowsWithItems($rows);

        if ($posts === []) {
            return [];
        }

        $post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);

        $reply_counts = Post::replyCountsForPosts($post_ids);
        $like_counts = Post::likeCountsForPosts($post_ids);
        $liked = Auth::check() ? Post::likedByUserForPosts($post_ids, (int) Auth::id()) : [];

        $threads = [];

        foreach ($posts as $post) {
            $post_id = (int) $post -> postId;

            $thread = new self();
            $thread -> post = $post;
            $thread -> replyCount = $reply_counts[$post_id] ?? 0;
            $thread -> likeCount = $like_counts[$post_id] ?? 0;
            $thread -> liked = $liked[$post_id] ?? false;

            $threads[] = $thread;
        }

        return $threads;
    }
}
