<?php

declare(strict_types=1);

/**
 * The post embedded inside a quote post: the quoted author's byline and a
 * readable slice of what they wrote, linking to the real thing. Deliberately
 * not a full Post card - no action bar, no media grid, no nesting (a quote
 * of a quote renders the inner one as its link alone) - because the point of
 * the embed is to give the commentary its context, not to be the post.
 */
class QuotedPost extends Div
{
    public ?string $class = 'QuotedPost';

    public ?int $postId = null;
    public ?int $userId = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $slug = null;
    public ?string $authorTitle = null;
    public ?string $createdAt = null;

    /**
     * The quoted rows for a page of posts, keyed by the QUOTING post's id -
     * one query for the page, the same shape every other per-page attachment
     * uses. Quotes of banned authors' posts come back as absent, exactly like
     * quotes of deleted ones.
     *
     * @param Post[] $posts
     * @return array<int, self>
     */
    public static function forPosts(array $posts): array
    {
        $wanting = [];

        foreach ($posts as $post) {
            if ($post -> quotedPostId !== null) {
                $wanting[(int) $post -> postId] = (int) $post -> quotedPostId;
            }
        }

        if ($wanting === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($wanting), '?'));
        $not_banned = 0;

        $rows = DB::rows('
SELECT `Posts`.`postId`, `Posts`.`userId`, `Posts`.`title`, `Posts`.`description`, `Posts`.`createdAt`,
    `Users`.`slug`, `Users`.`title` AS `authorTitle`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` IN (' . $placeholders . ') AND `Users`.`banned` = ?
', self::class, str_repeat('i', count($wanting)) . 'i', ...[...array_values($wanting), $not_banned]);

        $by_quoted_id = [];

        foreach ($rows as $row) {
            $by_quoted_id[(int) $row -> postId] = $row;
        }

        $attached = [];

        foreach ($wanting as $quoting_id => $quoted_id) {
            if (isset($by_quoted_id[$quoted_id])) {
                // One instance each, because rendering one is a one-shot act:
                // two posts quoting the same post are two embeds to draw, and
                // handing both the same object makes the second render throw
                // and take the whole page with it. One row read, several
                // objects made from it.
                $attached[$quoting_id] = clone $by_quoted_id[$quoted_id];
            }
        }

        return $attached;
    }

    public function toDOM(): \DOMElement
    {
        $byline = new Paragraph(($this -> authorTitle ?: $this -> slug) . ' · @' . $this -> slug);
        $byline -> class = 'QuotedPostByline';
        $byline -> mixins = ['muted', 'text-sm'];
        $this -> contents[] = $byline;

        if ((string) $this -> title !== '') {
            $title = new Paragraph((string) $this -> title);
            $title -> class = 'QuotedPostTitle';
            $this -> contents[] = $title;
        }

        if ((string) $this -> description !== '') {
            $this -> contents[] = new Paragraph(truncate((string) $this -> description, 280));
        }

        $link = new Anchor(
            ServerURL::absolute('/users/' . $this -> slug . '/' . $this -> postId),
            'View the quoted post'
        );
        $link -> class = 'QuotedPostLink';
        $link -> mixins = ['text-sm'];
        $this -> contents[] = $link;

        return parent::toDOM();
    }

    /** The JSON the client twin rebuilds this from. @return array<string, mixed> */
    public function toPayloadArray(): array
    {
        return [
            'postId' => (int) $this -> postId,
            'title' => $this -> title,
            'description' => $this -> description !== null ? truncate($this -> description, 280) : null,
            'slug' => $this -> slug,
            'authorTitle' => $this -> authorTitle,
        ];
    }
}
