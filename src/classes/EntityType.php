<?php

declare(strict_types=1);

/**
 * What a topic's kind is called, in whichever language it is being read in.
 *
 * The extractor speaks spaCy's vocabulary - gpe, norp, fac, work_of_art - which
 * is exactly right in the pipeline and means nothing on a page. The raw label
 * stays the identity (it is in the URL and the table); this is only what a
 * reader is shown, so it comes from the locale.
 *
 * Anything unlisted falls back to the raw label rather than being hidden: a new
 * spaCy version adding a category should show up looking odd, not vanish.
 */
class EntityType
{
    /**
     * The type as it appears in an address: its English plural, because
     * /topics/people/ lists people and one person is the page beneath it.
     * English here and not in the locale, since a route is an identifier.
     *
     * @var array<string, string>
     */
    private const SLUGS = [
        'hashtag' => 'hashtags',
        'person' => 'people',
        'org' => 'organizations',
        'gpe' => 'places',
        'loc' => 'regions',
        'fac' => 'landmarks',
        'product' => 'products',
        'event' => 'events',
        'work_of_art' => 'works',
        'law' => 'laws',
        'language' => 'languages',
        'norp' => 'groups',
    ];

    public static function label(string $type): string
    {
        return (string) (Strings::for(self::class)['singular'][$type] ?? $type);
    }

    public static function plural(string $type): string
    {
        return (string) (Strings::for(self::class)['plural'][$type] ?? $type);
    }

    /** What this kind is called in an address. */
    public static function slug(string $type): string
    {
        return self::SLUGS[$type] ?? $type;
    }

    /** The kind an address names, or null if it names nothing. */
    public static function fromSlug(string $slug): ?string
    {
        $type = array_search($slug, self::SLUGS, true);

        return $type === false ? null : $type;
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
