<?php

declare(strict_types=1);

/**
 * What a topic's kind is called in English.
 *
 * The extractor speaks spaCy's vocabulary - gpe, norp, fac, work_of_art - which
 * is exactly right in the pipeline and means nothing on a page. The raw label
 * stays the identity (it is in the URL and the table); this is only what a
 * reader is shown.
 *
 * Anything unlisted falls back to the raw label rather than being hidden: a new
 * spaCy version adding a category should show up looking odd, not vanish.
 */
class EntityType
{
    /** @var array<string, array{0: string, 1: string}> singular, plural */
    private const NAMES = [
        'hashtag' => ['Hashtag', 'Hashtags'],
        'person' => ['Person', 'People'],
        'org' => ['Organization', 'Organizations'],
        'gpe' => ['Place', 'Places'],
        'loc' => ['Region', 'Regions'],
        'fac' => ['Landmark', 'Landmarks'],
        'product' => ['Product', 'Products'],
        'event' => ['Event', 'Events'],
        'work_of_art' => ['Work', 'Works'],
        'law' => ['Law', 'Laws'],
        'language' => ['Language', 'Languages'],
        'norp' => ['Group', 'Groups'],
    ];

    public static function label(string $type): string
    {
        return self::NAMES[$type][0] ?? $type;
    }

    public static function plural(string $type): string
    {
        return self::NAMES[$type][1] ?? $type;
    }

    /**
     * Whether this is a kind the extractor can produce - which is what a URL
     * is checked against before anything is looked up under it.
     */
    public static function isKnown(string $type): bool
    {
        return in_array($type, EntityExtractor::ENTITY_TYPES, true);
    }

    /** Every kind, for a directory of them. */
    public static function all(): array
    {
        return EntityExtractor::ENTITY_TYPES;
    }
}
