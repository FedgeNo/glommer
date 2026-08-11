<?php

declare(strict_types=1);

/**
 * The labelled fields an account publishes about itself - "Website", "Pronouns",
 * "Location" - which travel as PropertyValue attachments on the actor document.
 *
 * Names and values arrive as HTML, since the far side renders them into a page
 * of its own and a value is usually a link. They are reduced to words here, at
 * the door: what is stored is a name and a value, and the profile renders them
 * the way it renders a bio - as text, linkified by this site's own renderer.
 * Nobody else's markup reaches a page here.
 *
 * A verification mark is deliberately not carried across. Mastodon shows one
 * where a field's link points back at the profile and that page links to it in
 * return, which is a claim this server has not checked - and repeating somebody
 * else's badge as though it were ours is the kind of thing UI must not do.
 */
class RemoteActorFields
{
    /** More than any profile has a reason to publish, and a bound on a stranger's array. */
    private const MAX_FIELDS = 8;

    private const MAX_NAME_LENGTH = 60;
    private const MAX_VALUE_LENGTH = 300;

    /**
     * The fields from an actor document's attachment array, ready to store.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public static function fromAttachments(mixed $attachments): array
    {
        if (!is_array($attachments)) {
            return [];
        }

        $fields = [];

        foreach ($attachments as $attachment) {
            if (count($fields) >= self::MAX_FIELDS) {
                break;
            }

            if (!is_array($attachment) || ($attachment['type'] ?? null) !== 'PropertyValue') {
                continue;
            }

            $name = self::words($attachment['name'] ?? null, self::MAX_NAME_LENGTH);
            $value = self::words($attachment['value'] ?? null, self::MAX_VALUE_LENGTH);

            if ($name === null || $value === null) {
                continue;
            }

            $fields[] = ['name' => $name, 'value' => $value];
        }

        return $fields;
    }

    /** What to store in Users.remoteActorFields, or null for an account with none. */
    public static function encode(array $fields): ?string
    {
        if ($fields === []) {
            return null;
        }

        $json = json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? null : $json;
    }

    /**
     * The fields back off a row, shaped the way fromAttachments() left them -
     * a row written by an older version, or by hand, is not trusted to be.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public static function decode(?string $stored): array
    {
        $decoded = $stored === null ? null : json_decode($stored, true);

        if (!is_array($decoded)) {
            return [];
        }

        $fields = [];

        foreach ($decoded as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null) || !is_string($field['value'] ?? null)) {
                continue;
            }

            $fields[] = ['name' => $field['name'], 'value' => $field['value']];
        }

        return array_slice($fields, 0, self::MAX_FIELDS);
    }

    /** One HTML fragment reduced to its words, or null when it holds none. */
    private static function words(mixed $html, int $limit): ?string
    {
        if (!is_string($html)) {
            return null;
        }

        // Collapsed to one line: this renders as a row in a list, and a value
        // that arrived as a paragraph would break the shape of it.
        $text = trim((string) preg_replace('/\s+/u', ' ', Delta::plainText(HTMLToDelta::convert($html))));

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }
}
