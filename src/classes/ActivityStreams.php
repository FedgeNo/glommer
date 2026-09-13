<?php

declare(strict_types=1);

/** The ActivityStreams wire forms understood by our federation boundary. */
class ActivityStreams
{
    public const CONTEXT = 'https://www.w3.org/ns/activitystreams';
    public const ACCEPT = 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/activity+json';

    /**
     * Select one supported type, including full IRIs and type arrays. Unknown
     * extensions do not hide a supported type; conflicting supported types
     * are declined rather than dispatched according to array order.
     *
     * @param string[] $supported
     */
    public static function type(mixed $value, array $supported): ?string
    {
        $types = [];

        foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $type) {
            if (!is_string($type)) {
                continue;
            }

            if (str_starts_with($type, self::CONTEXT . '#')) {
                $type = substr($type, strlen(self::CONTEXT) + 1);
            }

            if (in_array($type, $supported, true)) {
                $types[$type] = true;
            }
        }

        return count($types) === 1 ? array_key_first($types) : null;
    }

    /** A Link's target is href, even if the Link also has its own id. */
    public static function reference(mixed $value): ?string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return count($value) === 1 ? self::reference($value[0]) : null;
            }

            $is_link = array_key_exists('href', $value)
                || self::type($value['type'] ?? null, ['Link', 'Mention']) !== null;
            $value = $is_link ? ($value['href'] ?? null) : ($value['id'] ?? null);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return string[] */
    public static function references(mixed $value): array
    {
        $values = is_array($value) && array_is_list($value) ? $value : [$value];
        $references = [];

        foreach ($values as $entry) {
            $uri = self::reference($entry);

            if ($uri !== null) {
                $references[] = $uri;
            }
        }

        return array_values(array_unique($references));
    }

    /** Missing attribution on an embedded object inherits its verified signer. */
    public static function attributedTo(array $object, string $actor_uri): bool
    {
        if (!array_key_exists('attributedTo', $object)) {
            return true;
        }

        $value = $object['attributedTo'];
        $values = is_array($value) && array_is_list($value) ? $value : [$value];

        if ($values === []) {
            return false;
        }

        foreach ($values as $entry) {
            if (self::reference($entry) !== $actor_uri) {
                return false;
            }
        }

        return true;
    }

    /** JSON-LD needs the ActivityStreams profile; generic JSON/HTML is not AS. */
    public static function isMediaType(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $media_type = strtolower(trim(explode(';', $value, 2)[0]));

        if ($media_type === 'application/activity+json') {
            return true;
        }

        if ($media_type !== 'application/ld+json') {
            return false;
        }

        if (preg_match('/;\s*profile\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', $value, $matches) !== 1) {
            return false;
        }

        $profiles = preg_split('/\s+/', trim($matches[1] !== '' ? $matches[1] : ($matches[2] ?? ''))) ?: [];

        return in_array(self::CONTEXT, $profiles, true);
    }
}
