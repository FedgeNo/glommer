<?php

declare(strict_types=1);

class Page
{
    public static function create(
        string $title,
        ?string $description = null,
        ?string $image = null,
        ?array $json_ld = null,
        bool $needsEditor = false,
        bool $needsMath = false,
        bool $needsEmoji = false,
        ?string $body_class = null
    ): HTMLDocument {
        $page = new HTMLDocument();

        $config = require __DIR__ . '/../config.php';
        $site_title = $config['siteTitle'];

        $full_title = $title . ' - ' . $site_title;
        $description ??= $site_title . ' - a place to publish.';
        $url = self::currentUrl();

        $charset = new Meta();
        $charset -> charset = 'utf-8';
        $page -> addHeadContent($charset);

        $viewport = new Meta();
        $viewport -> name = 'viewport';
        $viewport -> content = 'width=device-width, initial-scale=1';
        $page -> addHeadContent($viewport);

        $title_element = new Title();
        $title_element -> contents[] = $full_title;
        $page -> addHeadContent($title_element);

        foreach (self::metaTags($full_title, $description, $image, $url) as $meta) {
            $page -> addHeadContent($meta);
        }

        $json_ld ??= [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site_title,
            'url' => $url,
        ];

        $json_ld_script = new Script();
        $json_ld_script -> attributes['type'] = 'application/ld+json';
        $json_ld_script -> contents[] = self::safeJsonForScript($json_ld);
        $page -> addHeadContent($json_ld_script);

        $page -> metaContentEndIndex = count($page -> head -> contents);

        // Base layer - loaded before style.css so local rules win the cascade.
        $bootstrap = new Link();
        $bootstrap -> rel = 'stylesheet';
        $bootstrap -> href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
        $page -> addHeadContent($bootstrap);

        if ($needsEditor) {
            $page -> addHeadContent(QuillAssets::cssLink());
        }

        $stylesheet = new Link();
        $stylesheet -> rel = 'stylesheet';
        $stylesheet -> href = URL::absolute('/style.css');
        $page -> addHeadContent($stylesheet);

        if ($needsEditor) {
            $page -> addHeadContent(QuillAssets::jsScript());
        }

        if ($needsMath) {
            $page -> addHeadContent(KaTeXAssets::cssLink());
            $page -> addHeadContent(KaTeXAssets::jsScript());
            $page -> addHeadContent(KaTeXAssets::autoRenderScript());
        }

        if ($needsEmoji) {
            $page -> addHeadContent(EmojiPickerAssets::initScript());
        }

        $page -> body -> class = $body_class !== null ? 'PageBody ' . $body_class : 'PageBody';
        $page -> addContents(new MainNavigation());

        $page -> addContents(new PageTitle($title));

        $current_user = Auth::user();

        $page -> addContents(new JSGlobals([
            'currentUserId' => $current_user ?-> userId,
            'currentUserUsername' => $current_user ?-> username,
            'currentUserSkinTone' => $current_user ?-> skinTone,
            'currentUserCanModerate' => Auth::canModerate(),
            'csrfToken' => CSRF::token(),
            'siteURL' => URL::absolute(''),
            'serverTime' => time() * 1000,
            'wsPort' => $config['wsPort'],
        ]));

        $post_script = new Script();
        $post_script -> src = URL::absolute('/post.js');
        $page -> addContents($post_script);

        $message_script = new Script();
        $message_script -> src = URL::absolute('/message.js');
        $page -> addContents($message_script);

        $other_user_script = new Script();
        $other_user_script -> src = URL::absolute('/other-user.js');
        $page -> addContents($other_user_script);

        $notification_script = new Script();
        $notification_script -> src = URL::absolute('/notification.js');
        $page -> addContents($notification_script);

        $main_script = new Script();
        $main_script -> src = URL::absolute('/main.js');
        $page -> addContents($main_script);

        return $page;
    }

    public static function safeJsonForScript(mixed $data): string
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

    public static function currentUrl(): string
    {
        return URL::absolute($_SERVER['REQUEST_URI'] ?? '/');
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
