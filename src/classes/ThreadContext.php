<?php

declare(strict_types=1);

/**
 * The line above a reply saying what it answers, and the way back to where the
 * conversation started.
 *
 * On the card rather than only on the permalink page, because a reply arrives
 * in a feed on its own - a relay is mostly other people's threads turning up
 * mid-conversation - and a post answering something you cannot see reads as a
 * non-sequitur. Above the byline for the reason the repost line is above it:
 * it answers the question the post raises before the post can raise it.
 */
class ThreadContext extends Div
{
    /**
     * Deeper than any thread worth walking back through, and a stop against a
     * cycle: parentId is a foreign key to another post, and nothing forbids a
     * loop, so the walk needs an end of its own rather than trusting the data.
     */
    private const MAX_DEPTH = 64;

    /** How much of a parent's body stands in for a title it does not have. */
    private const LABEL_LENGTH = 60;

    public ?string $class = 'ThreadContext';

    public ?int $parentId = null;
    public ?string $parentUsername = null;
    public ?string $parentLabel = null;
    public ?int $rootId = null;
    public ?string $rootUsername = null;

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $sentence = $words['response'] ?? [];

        $response = new Span();
        $response -> contents[] = (string) ($sentence['before'] ?? '');
        $response -> addContent(new Anchor(
            ServerURL::absolute('/users/' . $this -> parentUsername . '/' . $this -> parentId),
            $this -> parentLabel ?? (string) ($words['untitled'] ?? '')
        ));
        $response -> contents[] = (string) ($sentence['after'] ?? '');

        $this -> addContent($response);

        // Only where the start is somewhere else. On a direct reply the link
        // above already goes there, and two links to one post is furniture.
        if ($this -> rootId !== null && $this -> rootId !== $this -> parentId) {
            $this -> addContent(new ThreadStartLink(
                ServerURL::absolute('/users/' . $this -> rootUsername . '/' . $this -> rootId),
                (string) ($words['jumpToStart'] ?? '')
            ));
        }

        return parent::toDOM();
    }

    /**
     * What the client needs to build the same line, or null for a post that is
     * not a reply.
     */
    public function toPayloadArray(): array
    {
        return [
            'parentId' => $this -> parentId,
            'parentUsername' => $this -> parentUsername,
            'parentLabel' => $this -> parentLabel,
            'rootId' => $this -> rootId,
            'rootUsername' => $this -> rootUsername,
        ];
    }

    /**
     * The context for every post in a batch that is a reply, keyed by post id.
     *
     * One query for all of it, walking up from each reply at once: the same
     * pass that finds a post's parent finds the post its thread began with, so
     * a feed of replies costs one query rather than two per card.
     *
     * @param Post[] $posts
     * @return array<int, self>
     */
    public static function forPosts(array $posts): array
    {
        $reply_ids = [];

        foreach ($posts as $post) {
            if ($post -> parentId !== null) {
                $reply_ids[(int) $post -> postId] = true;
            }
        }

        $reply_ids = array_keys($reply_ids);

        if ($reply_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($reply_ids), '?'));

        // Every ancestor of every reply on the page, tagged with the reply it
        // was reached from, then narrowed to the two that are wanted: the one
        // directly above (depth 1) and the one with nothing above it.
        $rows = DB::rows('
WITH RECURSIVE `Ancestry` AS (
    SELECT `postId` AS `origin`, `postId`, `parentId`, 0 AS `depth`
        FROM `Posts`
        WHERE `postId` IN (' . $placeholders . ')
    UNION ALL
    SELECT `Ancestry`.`origin`, `Posts`.`postId`, `Posts`.`parentId`, `Ancestry`.`depth` + 1
        FROM `Posts`
        JOIN `Ancestry` ON `Ancestry`.`parentId` = `Posts`.`postId`
        WHERE `Ancestry`.`depth` < ' . self::MAX_DEPTH . '
)
SELECT `Ancestry`.`origin`, `Ancestry`.`depth`, `Posts`.`postId`, `Posts`.`parentId`, `Posts`.`title`, `Posts`.`description`, `Users`.`slug`
    FROM `Ancestry`
    JOIN `Posts` ON `Posts`.`postId` = `Ancestry`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Ancestry`.`depth` > 0 AND (`Ancestry`.`depth` = 1 OR `Posts`.`parentId` IS NULL)
', 'ThreadContextData', str_repeat('i', count($reply_ids)), ...$reply_ids);

        $contexts = [];

        foreach ($rows as $row) {
            $origin = (int) $row -> origin;
            $context = $contexts[$origin] ??= new self();

            if ((int) $row -> depth === 1) {
                $context -> parentId = (int) $row -> postId;
                $context -> parentUsername = $row -> slug;
                $context -> parentLabel = self::labelFor($row);
            }

            if ($row -> parentId === null) {
                $context -> rootId = (int) $row -> postId;
                $context -> rootUsername = $row -> slug;
            }
        }

        // A reply whose parent has gone (deleted, or never fetched from the
        // server it came from) has nothing to point at and shows no line.
        return array_filter($contexts, static fn (self $context): bool => $context -> parentId !== null);
    }

    /**
     * What to call the post being answered, or null when there is nothing to
     * build one from. Null rather than a fallback phrase here: this runs at
     * fetch time and ships to the client in toPayloadArray(), and the words
     * for "nothing to call it" belong to whichever side renders - toDOM()
     * here, threadContextToElement() in Post.js - not to this query.
     */
    private static function labelFor(object $row): ?string
    {
        if ($row -> title !== null && trim((string) $row -> title) !== '') {
            return trim((string) $row -> title);
        }

        // description is already plain text (Delta::plainText derives it), so
        // there is no markup to strip - and stripping would eat any literal
        // '<' or '>' the text legitimately contains.
        $description = trim((string) ($row -> description ?? ''));

        if ($description === '') {
            return null;
        }

        return mb_strlen($description) > self::LABEL_LENGTH
            ? mb_substr($description, 0, self::LABEL_LENGTH) . '…'
            : $description;
    }
}
