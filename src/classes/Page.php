<?php

declare(strict_types=1);

class Page extends HTMLDocument
{
    // The description meta (and its og/twitter twins) is capped near the length
    // search engines actually display, on a word boundary so it never ends
    // mid-word.
    public const META_DESCRIPTION_MAX_LENGTH = 160;

    public ?string $title = null;
    public ?string $description = null;
    public ?string $image = null;
    public ?array $jsonLD = null;

    // Metadata-in-spirit link (an RSS <link rel="alternate">) - a property like
    // any other page metadata, emitted in the head right after the meta block.
    public ?RSSLink $rssLink = null;

    public bool $needsEditor = false;
    public bool $needsMath = false;
    public bool $needsEmoji = false;
    public bool $needsHelp = false;
    public bool $needsTagGraph = false;

    public ?string $bodyClass = null;

    // The head and body are assembled from the properties above the first time
    // the page renders, not at construction - so a caller can seed a Page from
    // an object (new Page($profileUser)) and adjust properties afterwards, and
    // the final values are the ones that get built. Guarded so a second render
    // (e.g. toDOM() then __toString()) doesn't append everything twice.
    private bool $assembled = false;

    public function toDOM(): \DOMElement
    {
        if (!$this -> assembled) {
            $this -> assembled = true;
            $this -> assembleHead();
            $this -> assembleBody();
        }

        return parent::toDOM();
    }

    private function assembleHead(): void
    {
        $site_title = Config::get('siteTitle');
        if (!$this -> title) {
            $this -> title = $site_title;
        }
        $full_title = $this -> title === $site_title ? $site_title : $this -> title . ' - ' . $site_title;
        $description = self::truncateAtWordBoundary(
            $this -> description ?? SiteInfo::description(),
            self::META_DESCRIPTION_MAX_LENGTH
        );
        $url = self::currentURL();

        $charset = new Meta();
        $charset -> charset = 'utf-8';
        $this -> head -> addContent($charset);

        $viewport = new Meta();
        $viewport -> name = 'viewport';
        $viewport -> content = 'width=device-width, initial-scale=1';
        $this -> head -> addContent($viewport);

        $title_element = new Title();
        $title_element -> contents[] = $full_title;
        $this -> head -> addContent($title_element);

        $favicon = new Link();
        $favicon -> rel = 'icon';
        $favicon -> href = Favicon::URL();
        $this -> head -> addContent($favicon);

        foreach (self::metaTags($full_title, $description, $this -> image, $url) as $meta) {
            $this -> head -> addContent($meta);
        }

        $json_ld = $this -> jsonLD ?? [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site_title,
            'url' => $url,
        ];

        $json_ld_script = new Script();
        $json_ld_script -> attributes['type'] = 'application/ld+json';
        $json_ld_script -> contents[] = self::safeJSONForScript($json_ld);
        $this -> head -> addContent($json_ld_script);

        // Metadata in spirit (an RSS alternate link), added by a page that has a
        // feed - sits right after the metadata block and before any stylesheet.
        if ($this -> rssLink !== null) {
            $this -> head -> addContent($this -> rssLink);
        }

        // Base layer - loaded before style.css so local rules win the cascade.
        $bootstrap = new Link();
        $bootstrap -> rel = 'stylesheet';
        $bootstrap -> href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
        $this -> head -> addContent($bootstrap);

        if ($this -> needsEditor) {
            $this -> head -> addContent(QuillAssets::CSSLink());
        }

        $stylesheet = new Link();
        $stylesheet -> rel = 'stylesheet';
        $stylesheet -> href = ServerURL::absolute('/style.css');
        $this -> head -> addContent($stylesheet);

        // KaTeX's CSS and core JS load before Quill's JS: Quill's formula module
        // reads window.katex at construction, so the editor's formula button
        // needs KaTeX present even on a page that isn't otherwise rendering math.
        // The auto-render pass (typed/pasted delimiters) is only needed where
        // posted math is actually shown, so it stays gated on needsMath alone.
        if ($this -> needsMath || $this -> needsEditor) {
            $this -> head -> addContent(KaTeXAssets::CSSLink());
            $this -> head -> addContent(KaTeXAssets::JSScript());
        }

        if ($this -> needsEditor) {
            $this -> head -> addContent(QuillAssets::JSScript());
        }

        if ($this -> needsMath) {
            $this -> head -> addContent(KaTeXAssets::autoRenderScript());
        }

        if ($this -> needsEmoji) {
            $this -> head -> addContent(EmojiPickerAssets::initScript());
        }
    }

    private function assembleBody(): void
    {
        $this -> body -> class = $this -> bodyClass !== null ? 'PageBody ' . $this -> bodyClass : 'PageBody';

        // The page-specific content has already been added to the body (via
        // addContent during the page script); the chrome below belongs in front
        // of it, so it's spliced in at the start rather than appended.
        $chrome = [];

        $chrome[] = new MainNavigation();
        $chrome[] = new PageTitle((string) $this -> title);

        $current_user = Auth::user();

        $chrome[] = new JSGlobals([
            'currentUserId' => $current_user ?-> userId,
            'currentUserUsername' => $current_user ?-> slug,
            'currentUserSkinTone' => $current_user ?-> skinTone,
            'currentUserCanModerate' => Auth::canModerate(),
            'CSRFToken' => CSRF::token(),
            'siteURL' => ServerURL::absolute(''),
            'serverTime' => time() * 1000,
            'WSPort' => Config::get('WSPort'),
            // Single source of truth for how many carousel items load eagerly -
            // post.js reads this rather than hardcoding its own copy, so the
            // client- and server-rendered carousels can't drift apart.
            'carouselEagerItems' => Carousel::INITIAL_EAGER_ITEMS,
        ]);

        // delta.js loads before post.js and main.js, which both call
        // render_delta() to build a post's body from its Delta ops.
        $script_sources = ['delta.js', 'user.js', 'post.js', 'message.js', 'other-user.js', 'notification.js', 'banned-user.js', 'report.js'];

        if ($this -> needsTagGraph) {
            $script_sources[] = 'tag-graph.js';
        }

        $script_sources[] = 'main.js';

        if ($this -> needsHelp) {
            $script_sources[] = 'help.js';
        }

        foreach ($script_sources as $source) {
            $script = new Script();
            $script -> src = ServerURL::absolute('/' . $source);
            $chrome[] = $script;
        }

        array_splice($this -> body -> contents, 0, 0, $chrome);

        // Last in the body so it sits above the page's own content without a
        // stacking context to fight.
        $this -> body -> addContent(new ScrollToTopButton());
    }

    public static function safeJSONForScript(mixed $data): string
    {
        // DOMDocument HTML-escapes text node content (&, <, >) regardless of
        // the parent tag. Browsers don't decode entities inside <script>
        // (it's a "raw text" element), so that escaping would corrupt the
        // JSON. Encoding these characters as JSON \uXXXX escapes first keeps
        // them out of DOMDocument's escaping pass while still round-tripping
        // correctly through JSON.parse().
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        return str_replace(['&', '<', '>'], ['\\u0026', '\\u003C', '\\u003E'], $json);
    }

    public static function currentURL(): string
    {
        return ServerURL::absolute($_SERVER['REQUEST_URI'] ?? '/');
    }

    /**
     * Caps text to a maximum length without splitting a word: cut at the limit,
     * back up to the last space so the result ends on a whole word, and mark the
     * trim with an ellipsis. Text already within the limit is returned as-is.
     */
    private static function truncateAtWordBoundary(string $text, int $max_length): string
    {
        if (mb_strlen($text) <= $max_length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max_length);
        $last_space = mb_strrpos($cut, ' ');

        if ($last_space !== false) {
            $cut = mb_substr($cut, 0, $last_space);
        }

        return rtrim($cut) . '…';
    }

    protected static function metaTags(string $title, string $description, ?string $image, string $url): array
    {
        $tags = [];

        $description_tag = new Meta();
        $description_tag -> name = 'description';
        $description_tag -> content = $description;
        $tags[] = $description_tag;

        $og_pairs = [
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'website',
            'og:url' => $url,
        ];

        if ($image !== null) {
            $og_pairs['og:image'] = $image;
        }

        foreach ($og_pairs as $property => $content) {
            $tag = new Meta();
            $tag -> property = $property;
            $tag -> content = $content;
            $tags[] = $tag;
        }

        $twitter_pairs = [
            'twitter:card' => $image !== null ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
        ];

        if ($image !== null) {
            $twitter_pairs['twitter:image'] = $image;
        }

        foreach ($twitter_pairs as $name => $content) {
            $tag = new Meta();
            $tag -> name = $name;
            $tag -> content = $content;
            $tags[] = $tag;
        }

        return $tags;
    }
}
