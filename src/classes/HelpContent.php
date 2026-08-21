<?php

declare(strict_types=1);

/** The localized, searchable Help corpus stored in the shared locale files. */
class HelpContent
{
    /** Stable category keys in display order. */
    private const CATEGORY_ORDER = [
        'gettingStarted',
        'posting',
        'connecting',
        'stayingSafe',
        'yourAccount',
    ];

    /** @return HelpArticle[] every article, in category then authoring order */
    public static function all(): array
    {
        static $articles_by_locale = [];
        $locale = Strings::locale();

        if (!isset($articles_by_locale[$locale])) {
            $words = Strings::help();
            $categories = (array) ($words['categories'] ?? []);
            $definitions = (array) ($words['articles'] ?? []);
            $articles = [];

            foreach ($definitions as $slug => $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $category_key = (string) ($definition['category'] ?? '');
                $body = str_replace(
                    '{site}',
                    htmlspecialchars((string) Config::get('siteTitle'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    (string) ($definition['body'] ?? '')
                );
                $articles[] = new HelpArticle(
                    (string) $slug,
                    (string) ($definition['title'] ?? ''),
                    (string) ($categories[$category_key] ?? ''),
                    (string) ($definition['summary'] ?? ''),
                    $body,
                );
            }

            $articles_by_locale[$locale] = $articles;
        }

        return $articles_by_locale[$locale];
    }

    public static function find(string $slug): ?HelpArticle
    {
        foreach (self::all() as $article) {
            if ($article -> slug === $slug) {
                return $article;
            }
        }

        return null;
    }

    /** @return array<string, HelpArticle[]> localized category => articles */
    public static function groupedByCategory(): array
    {
        $words = Strings::help();
        $categories = (array) ($words['categories'] ?? []);
        $grouped = [];

        foreach (self::CATEGORY_ORDER as $key) {
            $label = (string) ($categories[$key] ?? '');
            $grouped[$label] = [];
        }

        foreach (self::all() as $article) {
            $grouped[$article -> category][] = $article;
        }

        return array_filter($grouped, static fn (array $articles): bool => $articles !== []);
    }

    /** @return HelpArticle[] field-weighted substring matches, best first */
    public static function search(string $query): array
    {
        $terms = array_values(array_filter(
            preg_split('/\s+/', mb_strtolower(trim($query))) ?: [],
            static fn (string $term): bool => $term !== ''
        ));

        if ($terms === []) {
            return self::all();
        }

        $scored = [];

        foreach (self::all() as $article) {
            $title = mb_strtolower($article -> title);
            $summary = mb_strtolower($article -> summary);
            $body = mb_strtolower(strip_tags($article -> body));
            $score = 0;

            foreach ($terms as $term) {
                $score += mb_substr_count($title, $term) * 10;
                $score += mb_substr_count($summary, $term) * 4;
                $score += mb_substr_count($body, $term);
            }

            if ($score > 0) {
                $scored[] = ['article' => $article, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn (array $entry): HelpArticle => $entry['article'], $scored);
    }
}
