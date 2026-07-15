<?php

declare(strict_types=1);

/**
 * The trending pipeline's extraction step - deliberately dumb for v1: the
 * explicit, user-supplied hashtags a post's body actually carries, parsed via
 * Delta::hashtags() - the exact same function the real hashtag system uses to
 * index a post at write time (Hashtag::indexPost()/reindexPost()), so there's
 * no way for this to drift from what a post's real hashtags are. Deliberately
 * NOT sourced from Posts.keywords (the denormalized flat copy) - that column
 * exists for FULLTEXT search and isn't guaranteed to hold only hashtags (a
 * pre-hashtag-feature or otherwise irregularly-populated row could carry
 * anything there). Parsing the actual post content directly is also what
 * keeps this a clean seam: a real NER/LLM extractor swapping in later would
 * need the raw text too, not a pre-extracted column.
 */
class EntityExtractor
{
    /**
     * @return array<int, array{type: string, value: string}>
     */
    public static function extract(?string $description_delta): array
    {
        if ($description_delta === null) {
            return [];
        }

        $entities = [];

        foreach (Delta::hashtags(Delta::decode($description_delta)) as $tag) {
            $entities[] = ['type' => 'hashtag', 'value' => $tag];
        }

        return $entities;
    }
}
