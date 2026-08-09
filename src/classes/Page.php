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

    /**
     * Configuration this one page adds to the site-wide block - a setting that
     * only matters here, rather than one every page carries. The data of
     * whatever is being looked at does not belong here: the config travels as
     * a cookie, which every later request sends back up, so it rides on the
     * element that needs it instead (see ClientConfig).
     *
     * @var array<string, mixed>
     */
    public array $clientConfig = [];
    public bool $needsEmoji = false;
    public bool $needsHelp = false;
    public bool $needsTagGraph = false;
    public bool $needsMap = false;

    public ?string $bodyClass = null;

    // The head and body are assembled from the properties above the first time
    // the page renders, not at construction - so a caller can seed a Page from
    // an object (new Page($profileUser)) and adjust properties afterwards, and
    // the final values are the ones that get built. Guarded so a second render
    // (e.g. toDOM() then __toString()) doesn't append everything twice.
    private bool $assembled = false;

    public function toDOM(): \DOMElement
    {
        $this -> assembleHead();
        $this -> assembleBody();
        return parent::toDOM();
    }

    private function assembleHead(): void
    {
        $site_title = Config::get('siteTitle');
        if (!$this -> title) {
            $this -> title = $site_title;
        }
        $full_title = $this -> title === $site_title ? $site_title : $this -> title . ' - ' . $site_title;
        $description = truncate(
            $this -> description ?? SiteInfo::description(),
            self::META_DESCRIPTION_MAX_LENGTH
        );
        $url = current_url();

        $charset = new Meta;
        $charset -> charset = 'utf-8';
        $this -> addHeadContent($charset);

        $viewport = new Meta;
        $viewport -> name = 'viewport';
        $viewport -> content = 'width=device-width, initial-scale=1';
        $this -> addHeadContent($viewport);

        $title_element = new Title;
        $title_element -> contents[] = $full_title;
        $this -> addHeadContent($title_element);

        $favicon = new Link();
        $favicon -> rel = 'icon';
        $favicon -> href = Favicon::URL();
        $this -> addHeadContent($favicon);

        // Makes the site installable and gives the service worker (registered
        // in main.js) something to belong to. Both are static, same for every
        // page and visitor.
        $manifest = new Link();
        $manifest -> rel = 'manifest';
        $manifest -> href = ServerURL::absolute('/manifest.webmanifest');
        $this -> addHeadContent($manifest);

        foreach (self::metaTags($full_title, $description, $this -> image, $url) as $meta) {
            $this -> addHeadContent($meta);
        }

        $json_ld = $this -> jsonLD ?? [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site_title,
            'url' => $url,
        ];

        $json_ld_script = new Script;
        $json_ld_script -> attributes['type'] = 'application/ld+json';
        $json_ld_script -> contents[] = safe_json_for_script($json_ld);
        $this -> addHeadContent($json_ld_script);

        // Metadata in spirit (an RSS alternate link), added by a page that has a
        // feed - sits right after the metadata block and before any stylesheet.
        if ($this -> rssLink !== null) {
            $this -> addHeadContent($this -> rssLink);
        }

        // Base layer - loaded before the site's own sheets so local rules win
        // the cascade.
        $bootstrap = new Link;
        $bootstrap -> rel = 'stylesheet';
        $bootstrap -> href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
        // Pinned to this version's exact bytes, like the other CDN assets.
        $bootstrap -> attributes['integrity'] = 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH';
        $bootstrap -> attributes['crossorigin'] = 'anonymous';
        $this -> addHeadContent($bootstrap);

        if ($this -> needsEditor) {
            $this -> addHeadContent(QuillAssets::CSSLink());
        }

        // The stylesheet lives on googleapis but the font files it names live
        // on gstatic - an origin the browser only discovers once that CSS has
        // arrived. Warming it now runs the handshake during the CSS download
        // instead of after it.
        $font_origin = new Link;
        $font_origin -> rel = 'preconnect';
        $font_origin -> href = 'https://fonts.gstatic.com';
        $font_origin -> attributes['crossorigin'] = 'anonymous';
        $this -> addHeadContent($font_origin);

        $inter_font = new Link;
        $inter_font -> rel = 'stylesheet';
        $inter_font -> href = 'https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&display=swap';
        $this -> addHeadContent($inter_font);

        foreach (['themes', 'base', 'utilities', 'components', 'layout', 'mobile'] as $sheet) {
            $stylesheet = new Link;
            $stylesheet -> rel = 'stylesheet';
            $stylesheet -> href = ServerURL::absolute('/styles/' . $sheet . '.css');
            $this -> addHeadContent($stylesheet);
        }

        // KaTeX's CSS and core JS load before Quill's JS: Quill's formula module
        // reads window.katex at construction, so the editor's formula button
        // needs KaTeX present even on a page that isn't otherwise rendering math.
        // The auto-render pass (typed/pasted delimiters) is only needed where
        // posted math is actually shown, so it stays gated on needsMath alone.
        if ($this -> needsMath || $this -> needsEditor) {
            $this -> addHeadContent(KaTeXAssets::CSSLink());
            $this -> addHeadContent(KaTeXAssets::JSScript());
        }

        if ($this -> needsEditor) {
            $this -> addHeadContent(QuillAssets::JSScript());
        }

        if ($this -> needsMath) {
            $this -> addHeadContent(KaTeXAssets::autoRenderScript());
        }

        if ($this -> needsEmoji) {
            $this -> addHeadContent(EmojiPickerAssets::initScript());
        }

        if ($this -> needsMap) {
            foreach (MapAssets::CSSLinks() as $link) {
                $this -> addHeadContent($link);
            }

            foreach (MapAssets::JSScripts() as $script) {
                $this -> addHeadContent($script);
            }
        }
    }

    /**
     * Whether to render the site navigation. Off only for a page shown because
     * the database is not safe to read - see ErrorDocument::sendWithoutChrome.
     */
    public bool $showNavigation = true;

    private function assembleBody(): void
    {
        $this -> body -> class = $this -> bodyClass !== null ? 'PageBody ' . $this -> bodyClass : 'PageBody';

        // Everything the page added becomes the content of a <main> landmark,
        // with the nav left outside it as a landmark of its own. That gives
        // assistive tech a "skip to the content" target, which a page whose
        // content sat loose in <body> after the nav had no way to offer.
        $main = new Main;
        // What the skip link jumps to, and what it needs focusing on when it
        // does: a browser moves focus to the target of an in-page link only if
        // that target can hold focus.
        $main -> attributes['id'] = SkipLink::TARGET;
        $main -> attributes['tabindex'] = '-1';
        $main -> addContent(new PageTitle((string) $this -> title));
        $main -> addContents($this -> body -> contents);

        // The navigation reads the database - it asks whether this member has
        // unseen notifications - so a page sent because the database cannot be
        // trusted leaves it off rather than querying tables the running code
        // may no longer agree with.
        // The skip link leads, because a thing that lets somebody past the
        // navigation has to come before it.
        $this -> body -> contents = $this -> showNavigation
            ? [new SkipLink, new MainNavigation, $main]
            : [$main];

        $this -> addContent(new ScrollToTopButton);

        $main_module = new ModuleScript;
        $main_module -> src = ServerURL::absolute('/scripts/main.js');
        $this -> addContent($main_module);

        // Last of all, and only for the admin: what this page cost to build.
        // After the script tag rather than before it, so the figure covers as
        // much of the work as a thing inside the page can.
        if (PageGenerationTime::isForViewer()) {
            $this -> addContent(new PageGenerationTime());
        }
    }

    public function send(): void
    {
        ClientConfig::send(array_merge($this -> clientConfig, [
            'needsMath' => $this -> needsMath,
        ]));

        parent::send();
    }

    protected static function metaTags(string $title, string $description, ?string $image, string $url): array
    {
        $tags = [];

        $description_tag = new Meta;
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
            $tag = new Meta;
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
            $tag = new Meta;
            $tag -> name = $name;
            $tag -> content = $content;
            $tags[] = $tag;
        }

        $rta = new Meta;
        $rta -> name = 'RATING';
        $rta -> content = 'RTA-5042-1996-1400-1577-RTA';
        $tags[] = $rta;

        return $tags;
    }

}
