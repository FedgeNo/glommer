<?php

declare(strict_types=1);

/**
 * Fetches Open Graph metadata for a post's link field so the composer can
 * offer to pre-fill the title/description/image - the user can still edit
 * or remove any of it before posting. The HTML fetch and the image fetch
 * both go through SafeHTTPFetcher, since both URLs are user-submitted.
 */
class LinkPreviewFetcher
{
    private const MAX_HTML_BYTES = 262144;
    private const MAX_IMAGE_BYTES = 5242880;

    /**
     * @return array{title: ?string, description: ?string, image: array{seed: string, thumbnailURL: string}|null}|null
     */
    public static function fetch(string $url): ?array
    {
        // Same lottery approach as RateLimiter/storeCache pruning: staged
        // images whose composer session ended without a post or an explicit
        // discard would otherwise pile up on disk forever.
        if (mt_rand(1, 100) === 1) {
            UploadProcessor::sweepStagedLinkImages();
        }

        $metadata = self::cachedMetadata($url) ?? self::fetchAndCacheMetadata($url);

        if ($metadata === null) {
            return null;
        }

        $image = $metadata['imageURL'] !== null ? self::stageImage($metadata['imageURL']) : null;

        return [
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'image' => $image,
        ];
    }

    /**
     * A failed fetch (network blip, target site's own transient outage, a
     * timeout under load) is far more likely to succeed if retried soon than
     * a successful fetch is to have gone stale - caching a failure for as
     * long as a success would turn one bad moment into a day-long lockout
     * for that URL. Confirmed live: a fetch that failed under concurrent
     * load kept returning null on every subsequent request for the same URL,
     * even though the exact same fetch succeeded immediately when retried by
     * hand - the cache, not connectivity, was the problem.
     */
    private const SUCCESS_CACHE_SECONDS = 86400;
    private const FAILURE_CACHE_SECONDS = 120;

    /**
     * @return array{title: ?string, description: ?string, imageURL: ?string}|null
     */
    private static function cachedMetadata(string $url): ?array
    {
        $succeeded_flag = 1;
        $failed_flag = 0;
        $success_cache_seconds = self::SUCCESS_CACHE_SECONDS;
        $failure_cache_seconds = self::FAILURE_CACHE_SECONDS;

        $preview = DB::row('
SELECT `title`, `description`, `imageURL`, `succeeded`
    FROM `LinkPreviews`
    WHERE `url` = ?
        AND (
            (`succeeded` = ? AND `fetchedAt` > NOW() - INTERVAL ? SECOND)
            OR (`succeeded` = ? AND `fetchedAt` > NOW() - INTERVAL ? SECOND)
        )
', 'LinkPreviewData', 'siiii', $url, $succeeded_flag, $success_cache_seconds, $failed_flag, $failure_cache_seconds);

        if ($preview === null) {
            return null;
        }

        if (!$preview -> succeeded) {
            return ['title' => null, 'description' => null, 'imageURL' => null];
        }

        return ['title' => $preview -> title, 'description' => $preview -> description, 'imageURL' => $preview -> imageURL];
    }

    // The first attempt plus up to this many retries - a blip (a timeout
    // under load, the target site's own transient hiccup) has a real chance
    // of clearing up within a couple of seconds; a permanent failure (a bad
    // domain, an SSRF-blocked host) fails the same way every time and these
    // extra attempts just cost a few fast no-op checks, not real time.
    private const MAX_FETCH_RETRIES = 3;

    /**
     * @return array{title: ?string, description: ?string, imageURL: ?string}|null
     */
    private static function fetchAndCacheMetadata(string $url): ?array
    {
        $fetched = null;

        for ($attempt = 0; $fetched === null && $attempt <= self::MAX_FETCH_RETRIES; $attempt++) {
            $fetched = SafeHTTPFetcher::get($url, self::MAX_HTML_BYTES);
        }

        if ($fetched === null) {
            self::storeCache($url, null, false);

            return null;
        }

        $content_type = (string) $fetched['contentType'];

        // Anything that isn't an HTML document has no title/description to pull
        // out of it - the filename is the best title available. If it's
        // specifically an image, the linked resource is also its own preview
        // image (thumbnailed the same as any other attached image); for other
        // non-HTML types (PDFs, etc.) there's just a title and no image.
        $metadata = str_starts_with($content_type, 'text/html')
            ? self::parseHTML(self::toUtf8($fetched['body'], $content_type), $url)
            : [
                'title' => self::filenameFromURL($url),
                'description' => null,
                'imageURL' => str_starts_with($content_type, 'image/') ? $url : null,
            ];

        // A successful fetch that yielded no usable metadata (a page with no
        // OG/meta tags → parseHTML returns null) is still a SUCCESS - there's
        // genuinely nothing to show, so cache it under the long success TTL
        // rather than re-fetching the same empty page every 2 minutes forever.
        self::storeCache($url, $metadata, true);

        return $metadata;
    }

    private static function filenameFromURL(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return null;
        }

        $filename = self::cleanText(rawurldecode(basename($path)), 255);

        return $filename !== '' ? $filename : null;
    }

    /**
     * Converts a fetched HTML document to UTF-8 before parsing. The charset
     * comes from the Content-Type header when it names one; otherwise from a
     * <meta charset>/<meta http-equiv> pre-scan of the raw bytes (the same
     * pre-scan browsers do - libxml2 on its own would assume Latin-1 for any
     * page whose charset lives only in its HTTP header, garbling the
     * extracted text); otherwise UTF-8, HTML5's default. The declaration
     * check is format detection, not content extraction - the actual values
     * are still pulled from the parsed DOM. The leading <?xml ...> hint is
     * how libxml is told the (now converted) bytes are UTF-8, overriding any
     * stale meta declaration left in the markup.
     */
    private static function toUtf8(string $html, string $content_type): string
    {
        $charset = null;

        if (preg_match('/charset\s*=\s*["\']?\s*([a-z0-9_\-]+)/i', $content_type, $matches)) {
            $charset = $matches[1];
        } elseif (preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([a-z0-9_\-]+)/i', substr($html, 0, 4096), $matches)) {
            $charset = $matches[1];
        }

        if ($charset !== null && strcasecmp($charset, 'utf-8') !== 0) {
            try {
                $html = mb_convert_encoding($html, 'UTF-8', $charset);
            } catch (\ValueError $error) {
                // Unrecognized charset name - fall through and treat the
                // bytes as UTF-8, scrubbing whatever doesn't decode.
            }
        }

        // Scrub any invalid sequences (mislabeled pages, or a multibyte
        // character cut in half at the download byte cap) so nothing invalid
        // reaches the parser or, later, json_encode().
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

        return '<?xml encoding="utf-8"?>' . $html;
    }

    /**
     * Trims, scrubs to valid UTF-8, and truncates to a character (not byte)
     * count - a byte-based cut can split a multibyte character, and one
     * invalid byte makes json_encode() fail for the entire API response.
     */
    private static function cleanText(string $text, int $max_chars): string
    {
        $text = mb_convert_encoding(trim($text), 'UTF-8', 'UTF-8');

        return mb_substr($text, 0, $max_chars);
    }

    /**
     * Pulls title/description/image out of the document, methodically working
     * through every source of metadata a page might have and taking the first
     * one found for each field independently. JSON-LD goes first since, when
     * present, it's normally the most deliberately curated source (and the
     * one search engines themselves treat as authoritative); OG and Twitter
     * card tags are the next most likely to be deliberately curated; <title>
     * and <meta name="description"> are the plain-HTML fallback every page
     * has, but with the least guarantee of being a good summary.
     *
     * @return array{title: ?string, description: ?string, imageURL: ?string}|null
     */
    private static function parseHTML(string $html, string $base_url): ?array
    {
        $previous_setting = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document -> loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($previous_setting);

        $xpath = new \DOMXPath($document);
        $json_ld = self::extractJSONLD($xpath);

        $title = $json_ld['title']
            ?? self::metaContent($xpath, 'og:title', 'property')
            ?? self::metaContent($xpath, 'twitter:title', 'name')
            ?? self::tagText($xpath, '//title');

        $description = $json_ld['description']
            ?? self::metaContent($xpath, 'og:description', 'property')
            ?? self::metaContent($xpath, 'twitter:description', 'name')
            ?? self::metaContent($xpath, 'description', 'name');

        $image_url = $json_ld['image']
            ?? self::metaContent($xpath, 'og:image', 'property')
            ?? self::metaContent($xpath, 'twitter:image', 'name');

        if ($title === null && $description === null && $image_url === null) {
            return null;
        }

        return [
            // Decoded here as a defensive measure against JSON-LD specifically:
            // a <script> tag is raw text per the HTML spec, so libxml never
            // entity-decodes its contents (same as a real browser) - if a
            // site's structured-data generator HTML-escaped a title before
            // embedding it in the JSON (a real, fairly common bug in SEO
            // plugins/themes), that literal "&amp;" survives json_decode()
            // untouched and would otherwise get re-escaped by our own
            // rendering, showing up as literal "&amp;" text on the page. A
            // no-op for og/twitter/<title>, which DOMDocument already decodes
            // while parsing (verified: querying a normal tag's content never
            // needs this).
            'title' => $title !== null ? self::cleanText(html_entity_decode($title, ENT_QUOTES, 'UTF-8'), 255) : null,
            'description' => $description !== null ? self::cleanText(html_entity_decode($description, ENT_QUOTES, 'UTF-8'), 1000) : null,
            'imageURL' => $image_url !== null ? self::resolveURL(trim($image_url), $base_url) : null,
        ];
    }

    private static function metaContent(\DOMXPath $xpath, string $value, string $attribute): ?string
    {
        $nodes = $xpath -> query('//meta[@' . $attribute . '="' . $value . '"]/@content');

        return $nodes !== false && $nodes -> length > 0 ? $nodes -> item(0) -> nodeValue : null;
    }

    private static function tagText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath -> query($query);

        return $nodes !== false && $nodes -> length > 0 ? $nodes -> item(0) -> textContent : null;
    }

    private const JSON_LD_CONTENT_TYPES = [
        'article', 'newsarticle', 'blogposting', 'webpage', 'product',
        'videoobject', 'recipe', 'event', 'creativework',
    ];

    /**
     * schema.org JSON-LD blocks can be a single object, an array of objects,
     * or an object with an "@graph" array of objects - normalizes all three
     * into a flat list of candidate nodes. A page's graph usually mixes its
     * actual content (an Article/WebPage/Product/etc.) with auxiliary nodes
     * describing the publisher (Organization, WebSite, BreadcrumbList) that
     * also happen to have a "name", so content-typed nodes are considered
     * first; each of title/description/image is independently taken from
     * the first node (content-typed nodes first) that has it, rather than
     * committing to every field from whichever node happens to come first.
     *
     * @return array{title: ?string, description: ?string, image: ?string}
     */
    private static function extractJSONLD(\DOMXPath $xpath): array
    {
        $scripts = $xpath -> query('//script[@type="application/ld+json"]');
        $nodes = [];

        if ($scripts !== false) {
            foreach ($scripts as $script) {
                $decoded = json_decode((string) $script -> textContent, true);

                if (is_array($decoded)) {
                    $nodes = array_merge($nodes, self::flattenJSONLD($decoded));
                }
            }
        }

        usort($nodes, fn (array $a, array $b) => self::isJSONLDContentNode($b) <=> self::isJSONLDContentNode($a));

        $title = null;
        $description = null;
        $image = null;

        foreach ($nodes as $node) {
            $fields = self::JSONLDFields($node);
            $title ??= $fields['title'];
            $description ??= $fields['description'];
            $image ??= $fields['image'];

            if ($title !== null && $description !== null && $image !== null) {
                break;
            }
        }

        return ['title' => $title, 'description' => $description, 'image' => $image];
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isJSONLDContentNode(array $node): bool
    {
        $type = $node['@type'] ?? null;

        foreach (is_array($type) ? $type : [$type] as $candidate) {
            if (is_string($candidate) && in_array(strtolower($candidate), self::JSON_LD_CONTENT_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function flattenJSONLD(array $decoded): array
    {
        if (array_is_list($decoded)) {
            $nodes = [];

            foreach ($decoded as $item) {
                if (is_array($item)) {
                    $nodes = array_merge($nodes, self::flattenJSONLD($item));
                }
            }

            return $nodes;
        }

        $nodes = [$decoded];

        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            $nodes = array_merge($nodes, self::flattenJSONLD($decoded['@graph']));
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     * @return array{title: ?string, description: ?string, image: ?string}
     */
    private static function JSONLDFields(array $node): array
    {
        $title = $node['headline'] ?? $node['name'] ?? null;
        $description = $node['description'] ?? null;

        return [
            'title' => is_string($title) ? $title : null,
            'description' => is_string($description) ? $description : null,
            'image' => self::JSONLDImage($node['image'] ?? null),
        ];
    }

    /**
     * schema.org's "image" property can be a plain URL string, an ImageObject
     * (an associative array with a "url" key), or an array of either.
     */
    private static function JSONLDImage(mixed $image): ?string
    {
        if (is_string($image)) {
            return $image;
        }

        if (!is_array($image)) {
            return null;
        }

        if (isset($image['url']) && is_string($image['url'])) {
            return $image['url'];
        }

        foreach ($image as $item) {
            $resolved = self::JSONLDImage($item);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private static function resolveURL(string $url, string $base_url): ?string
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            return strlen($url) <= 2048 ? $url : null;
        }

        $base_parts = parse_url($base_url);

        if ($base_parts === false || !isset($base_parts['scheme'], $base_parts['host'])) {
            return null;
        }

        // Protocol-relative ("//cdn.example.com/pic.jpg") - keep the URL's own
        // host, inherit only the base's scheme. Must be handled before the
        // root-relative branch below, since it also starts with "/" and would
        // otherwise get the origin glued on to produce a broken
        // "https://base//cdn.example.com/..." and the wrong host.
        if (str_starts_with($url, '//')) {
            $resolved = $base_parts['scheme'] . ':' . $url;

            return strlen($resolved) <= 2048 ? $resolved : null;
        }

        $origin = $base_parts['scheme'] . '://' . $base_parts['host'] . (isset($base_parts['port']) ? ':' . $base_parts['port'] : '');

        if (str_starts_with($url, '/')) {
            $resolved = $origin . $url;
        } else {
            // Path-relative ("pic.jpg", "../pic.jpg") resolves against the
            // base's directory, not the origin root - dropping the base path
            // would point at the wrong location.
            $base_path = $base_parts['path'] ?? '/';
            $last_slash = strrpos($base_path, '/');
            $base_dir = $last_slash !== false ? substr($base_path, 0, $last_slash + 1) : '/';
            $resolved = $origin . $base_dir . $url;
        }

        return strlen($resolved) <= 2048 ? $resolved : null;
    }

    private static function storeCache(string $url, ?array $metadata, bool $fetch_succeeded): void
    {
        // "succeeded" tracks whether the FETCH worked, not whether it found
        // metadata - a successful fetch of a page with no metadata still
        // caches long (nothing to retry), only a genuine fetch failure caches
        // briefly for a soon retry (see the two cache TTLs).
        $succeeded = $fetch_succeeded ? 1 : 0;
        $title = $metadata['title'] ?? null;
        $description = $metadata['description'] ?? null;
        $image_url = $metadata['imageURL'] ?? null;

        DB::run('
INSERT INTO `LinkPreviews` (`url`, `title`, `description`, `imageURL`, `succeeded`)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `description` = VALUES(`description`), `imageURL` = VALUES(`imageURL`), `succeeded` = VALUES(`succeeded`), `fetchedAt` = CURRENT_TIMESTAMP
', 'ssssi', $url, $title, $description, $image_url, $succeeded);

        // Rows older than a day are already treated as cache misses by
        // cachedMetadata() - this just reclaims the dead space they'd
        // otherwise leave behind. Same lottery approach RateLimiter uses for
        // its own pruning, so it's not doing a DELETE scan on every write.
        if (mt_rand(1, 100) === 1) {
            $prune_days = 1;

            DB::run('
DELETE
    FROM `LinkPreviews`
    WHERE `fetchedAt` < NOW() - INTERVAL ? DAY
', 'i', $prune_days);
        }
    }

    /**
     * Downloads $image_url through the same SSRF-guarded fetcher and hands
     * the bytes to the existing upload pipeline (UploadProcessor), which
     * resizes them to the site's normal display/thumbnail dimensions and
     * re-encodes them via GD - the original downloaded bytes are never
     * stored or served, only what GD produces. Uses the same random-seed
     * staging scheme as async video/audio uploads (UploadBatch), so the
     * files can be renamed onto a real itemId if the post is submitted, or
     * deleted outright if the user removes/replaces the preview.
     *
     * @return array{seed: string, thumbnailURL: string}|null
     */
    private static function stageImage(string $image_url): ?array
    {
        $fetched = SafeHTTPFetcher::get($image_url, self::MAX_IMAGE_BYTES);

        if ($fetched === null) {
            return null;
        }

        $tmp_path = tempnam(sys_get_temp_dir(), 'lp');

        if ($tmp_path === false) {
            return null;
        }

        file_put_contents($tmp_path, $fetched['body']);

        $seed = 'lp-' . bin2hex(random_bytes(16));
        $result = UploadProcessor::process($tmp_path, $seed);

        unlink($tmp_path);

        if ($result === null || $result['itemType'] !== 'ImageItem') {
            return null;
        }

        return [
            'seed' => $seed,
            'thumbnailURL' => ServerURL::absolute(UploadProcessor::thumbnailPath($seed, 'ImageItem')),
        ];
    }
}
