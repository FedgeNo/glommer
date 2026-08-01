<?php

declare(strict_types=1);

/**
 * A paged ActivityPub collection.
 *
 * Without ?page= the collection describes itself - how many it holds and where
 * its first page is. With it, one page of members plus the links onward. That
 * is the shape the network expects, and it is what keeps a popular account's
 * followers from being one enormous response that gets slower the more it has
 * to say.
 *
 * Offset-paged like every other list here.
 */
class ActivityPubCollection
{
    public const PAGE_SIZE = 20;

    /**
     * The collection itself: a total, and a pointer at where to start reading.
     *
     * @return array<string, mixed>
     */
    public static function describe(string $id, int $total): array
    {
        $document = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $id,
            'type' => 'OrderedCollection',
            'totalItems' => $total,
        ];

        // No first page when there is nothing in it - a link to an empty page
        // says there is more to read when there is not.
        if ($total > 0) {
            $document['first'] = $id . '?page=1';
        }

        return $document;
    }

    /**
     * One page of it.
     *
     * @param string[] $items
     * @return array<string, mixed>
     */
    public static function page(string $id, int $total, int $page, array $items): array
    {
        $page = max(1, $page);

        $document = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $id . '?page=' . $page,
            'type' => 'OrderedCollectionPage',
            'partOf' => $id,
            'totalItems' => $total,
            'orderedItems' => array_values($items),
        ];

        // Only when there is actually another page: a next link leading to an
        // empty one makes a crawler walk forever.
        if ($page * self::PAGE_SIZE < $total) {
            $document['next'] = $id . '?page=' . ($page + 1);
        }

        if ($page > 1) {
            $document['prev'] = $id . '?page=' . ($page - 1);
        }

        return $document;
    }

    /** The requested page, or null when the caller asked for the collection. */
    public static function requestedPage(): ?int
    {
        return isset($_GET['page']) ? max(1, (int) $_GET['page']) : null;
    }
}
