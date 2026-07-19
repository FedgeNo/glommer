<?php

declare(strict_types=1);

/**
 * Pulls `user@domain` Fediverse handles out of a free-form textarea paste -
 * any run of whitespace, punctuation, or other separators between handles is
 * just not part of a match, so the exact delimiter the admin used never
 * matters. A bare regex-shaped match still isn't trusted as a real domain -
 * the domain half is re-checked against URL::isValidHostname (the same real
 * IANA-TLD check every other link on the site goes through), so "user@example"
 * or a fake TLD is silently skipped rather than treated as a handle.
 */
class FediverseHandle
{
    /**
     * @return array<int, array{user: string, domain: string, handle: string}>
     */
    public static function parseAll(string $raw): array
    {
        preg_match_all('/@?([A-Za-z0-9_.-]+)@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', $raw, $matches, PREG_SET_ORDER);

        $seen = [];
        $handles = [];

        foreach ($matches as $match) {
            $user = strtolower($match[1]);
            $domain = strtolower($match[2]);

            if (!URL::isValidHostname($domain)) {
                continue;
            }

            $handle = '@' . $user . '@' . $domain;

            if (isset($seen[$handle])) {
                continue;
            }

            $seen[$handle] = true;
            $handles[] = ['user' => $user, 'domain' => $domain, 'handle' => $handle];
        }

        return $handles;
    }
}
