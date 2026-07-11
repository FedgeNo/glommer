<?php

declare(strict_types=1);

/**
 * The Help section's articles, authored here in source (they're static,
 * developer-written reference pages, so PHP is their single source of truth -
 * no database table to seed or keep in sync). HelpArticle wraps each one for
 * rendering; this class is the corpus and the search over it.
 *
 * Article bodies are trusted HTML written here, rendered through HelpArticleBody
 * (an HTMLLoader) - not user input, so there's nothing to sanitize.
 */
class HelpContent
{
    /**
     * Category display order for the Help index. Every article's category
     * must appear here; groupedByCategory() walks this list.
     *
     * @var string[]
     */
    private const CATEGORY_ORDER = [
        'Getting started',
        'Posting',
        'Connecting',
        'Staying safe',
        'Your account',
    ];

    /**
     * @return HelpArticle[] every article, in category then authoring order
     */
    public static function all(): array
    {
        static $articles = null;

        if ($articles === null) {
            $articles = array_map(
                fn (array $definition): HelpArticle => new HelpArticle(
                    $definition['slug'],
                    $definition['title'],
                    $definition['category'],
                    $definition['summary'],
                    $definition['body'],
                ),
                self::definitions()
            );
        }

        return $articles;
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

    /**
     * @return array<string, HelpArticle[]> category name => its articles, in
     *                                       CATEGORY_ORDER
     */
    public static function groupedByCategory(): array
    {
        $grouped = [];

        foreach (self::CATEGORY_ORDER as $category) {
            $grouped[$category] = [];
        }

        foreach (self::all() as $article) {
            $grouped[$article -> category][] = $article;
        }

        return array_filter($grouped, static fn (array $articles): bool => $articles !== []);
    }

    /**
     * Field-weighted substring search over the corpus (title matches count for
     * most, body for least). Small, static corpus - a plain in-memory scan is
     * instant and gives predictable ranking without FULLTEXT's min-token-size
     * and stopword surprises. Returns matches ranked by score, best first.
     *
     * @return HelpArticle[]
     */
    public static function search(string $query): array
    {
        $terms = array_values(array_filter(preg_split('/\s+/', strtolower(trim($query)))));

        if ($terms === []) {
            return self::all();
        }

        $scored = [];

        foreach (self::all() as $article) {
            $title = strtolower($article -> title);
            $summary = strtolower($article -> summary);
            $body = strtolower(strip_tags($article -> body));

            $score = 0;

            foreach ($terms as $term) {
                $score += substr_count($title, $term) * 10;
                $score += substr_count($summary, $term) * 4;
                $score += substr_count($body, $term);
            }

            if ($score > 0) {
                $scored[] = ['article' => $article, 'score' => $score];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn (array $entry): HelpArticle => $entry['article'], $scored);
    }

    /**
     * @return array<int, array{slug: string, title: string, category: string, summary: string, body: string}>
     */
    private static function definitions(): array
    {
        return [
            [
                'slug' => 'creating-an-account',
                'title' => 'Creating an account',
                'category' => 'Getting started',
                'summary' => 'Sign up with a username, email, and password to start posting.',
                'body' => '
<p>To join, open the <strong>Sign up</strong> link in the top-right corner and choose a username, enter your email address, and pick a password. Your username is how other people find and mention you, so pick something you\'re happy to be known by.</p>
<p>You can add a <strong>display name</strong> later from your settings - that\'s the friendlier name shown above your username on your posts and profile. If you leave it blank, your username is shown instead.</p>
<p>Once your account exists you can read and post right away. The very first account created on a brand-new site automatically becomes its administrator.</p>
',
            ],
            [
                'slug' => 'verifying-your-email',
                'title' => 'Verifying your email',
                'category' => 'Getting started',
                'summary' => 'Confirm your address from the link we email you, if email is turned on.',
                'body' => '
<p>If this site is set up to send email, we\'ll send you a verification link right after you sign up. Open it and your account is confirmed - this is how we know the address is really yours, so password resets and notifications can reach you.</p>
<p>Didn\'t get it? Check your spam folder first. You can ask for a fresh link from the <strong>Check your inbox</strong> page, which appears until you\'ve verified.</p>
<p>Some sites don\'t send email at all. If that\'s the case here, there\'s nothing to verify and you can start using everything immediately.</p>
',
            ],
            [
                'slug' => 'finding-your-way-around',
                'title' => 'Finding your way around',
                'category' => 'Getting started',
                'summary' => 'A quick tour of the menu: feeds, friends, users, messages, and your account.',
                'body' => '
<p>The bar across the top is how you get everywhere:</p>
<ul>
<li><strong>The site name</strong> on the left always takes you home to the main feed - and its menu is where you\'ll find <strong>Help</strong> (these articles), whether or not you\'re logged in.</li>
<li><strong>Friends Feed</strong> shows posts from just the people you\'re friends with.</li>
<li><strong>Friends</strong> is where you accept requests and manage who you\'re connected to.</li>
<li><strong>Users</strong> lets you search for people and see suggestions of who to connect with.</li>
<li><strong>Messages</strong> holds your private conversations.</li>
<li><strong>Notifications</strong> lights up when someone interacts with you.</li>
</ul>
<p>Your own name sits at the top-right - open it for your <strong>Settings</strong> and to <strong>log out</strong>.</p>
',
            ],
            [
                'slug' => 'composing-a-post',
                'title' => 'Composing a post',
                'category' => 'Posting',
                'summary' => 'Write and publish a post from the box at the top of the feed.',
                'body' => '
<p>The composer sits at the top of the main feed. Type your post in the writing area and press the post button to publish it. Posts appear in the public feed straight away - everything on the site is public, so write with that in mind.</p>
<p>A couple of optional extras sit above the writing area:</p>
<ul>
<li>A <strong>title</strong>, if you want to give your post a headline.</li>
<li>A <strong>link</strong>, if you\'re sharing a web page (see <a href="/help/sharing-a-link">Sharing a link</a>).</li>
</ul>
<p>You can dress your writing up with formatting, emoji, and even math, and you can attach photos, video, or audio. Those each have their own article. Note that a single post carries either a link <em>or</em> attached media, not both.</p>
',
            ],
            [
                'slug' => 'formatting-your-writing',
                'title' => 'Formatting your writing',
                'category' => 'Posting',
                'summary' => 'Use bold, italics, lists, quotes, code, emoji, and math in a post.',
                'body' => '
<p>The writing area is a rich editor. Its toolbar gives you the usual tools - <strong>bold</strong>, <em>italics</em>, underline, strikethrough, bulleted and numbered lists, block quotes, and code blocks - so you can lay a post out clearly.</p>
<h2>Emoji</h2>
<p>Open the emoji picker from the composer to drop emoji into your text. The picker remembers your preferred skin tone, so once you set it, it sticks.</p>
<h2>Math</h2>
<p>You can write mathematical notation using LaTeX. Wrap display math in <code>$$ ... $$</code> or <code>\\[ ... \\]</code>, and inline math in <code>\\( ... \\)</code>. It renders as proper equations when the post is shown. A lone dollar sign in ordinary text is left alone, so writing about prices is safe.</p>
',
            ],
            [
                'slug' => 'adding-photos-video-and-audio',
                'title' => 'Adding photos, video, and audio',
                'category' => 'Posting',
                'summary' => 'Attach one or more images, a video, or audio to a post.',
                'body' => '
<p>Use the attach control in the composer to add <strong>images, video, or audio</strong> to a post. You can attach more than one file - several images become a swipeable gallery on the finished post.</p>
<p>Larger video and audio files are processed after you post, so there may be a short wait before they\'re playable. You\'ll get a notification once your media has finished processing and is live.</p>
<p>If you change your mind before posting, use the cancel control next to the file picker to drop the attachment. Remember that a post with media can\'t also carry a link - pick whichever fits what you\'re sharing.</p>
',
            ],
            [
                'slug' => 'sharing-a-link',
                'title' => 'Sharing a link',
                'category' => 'Posting',
                'summary' => 'Paste a URL into the link field to share a page with a preview.',
                'body' => '
<p>To share a web page, put its address in the <strong>Link</strong> field above the writing area. We\'ll fetch the page and offer a preview - its title, a short description, and an image where one is available - which shows on your published post so people can see what they\'re about to open.</p>
<p>You can still add your own words in the writing area to say why you\'re sharing it. If you don\'t want the preview image, you can remove it before posting.</p>
<p>A post can carry a link or attached media, but not both at once.</p>
',
            ],
            [
                'slug' => 'replies-and-threads',
                'title' => 'Replies and threads',
                'category' => 'Posting',
                'summary' => 'Reply to a post to start a conversation thread beneath it.',
                'body' => '
<p>Every post has a <strong>reply</strong> action. Replying opens a composer right there, and your reply is attached beneath the original post as part of its thread. Replies work just like posts - you can format them, add emoji, and attach media.</p>
<p>Open any post on its own page to read the whole conversation, with the original at the top and replies below it. This is the best way to follow a longer back-and-forth.</p>
',
            ],
            [
                'slug' => 'liking-posts',
                'title' => 'Liking posts',
                'category' => 'Posting',
                'summary' => 'Show appreciation for a post with a single tap of the like button.',
                'body' => '
<p>Every post has a <strong>like</strong> button showing how many likes it has. Tap it to like the post; tap again to take your like back. The count updates immediately.</p>
<p>When you like someone\'s post, they get a notification - a quick, low-effort way to let people know you enjoyed what they shared.</p>
',
            ],
            [
                'slug' => 'deleting-a-post',
                'title' => 'Deleting a post',
                'category' => 'Posting',
                'summary' => 'Remove one of your own posts, and what happens to its replies.',
                'body' => '
<p>On any post you wrote, you\'ll see a <strong>delete</strong> action in place of the report action you\'d see on other people\'s posts. Deleting removes the post for everyone.</p>
<p>Deleting is permanent, so there\'s a confirmation step before it takes effect. If the post had replies beneath it, they go with it - so think of deleting a post that started a busy thread as ending the whole conversation.</p>
<p>You can only delete your own posts. If someone else\'s post is a problem, <a href="/help/reporting-abuse">report it</a> instead.</p>
',
            ],
            [
                'slug' => 'finding-people',
                'title' => 'Finding people',
                'category' => 'Connecting',
                'summary' => 'Search for users by name, or browse suggestions on the Users page.',
                'body' => '
<p>Open <strong>Users</strong> from the menu to find people. Start typing a name or username into the search box and matching people appear as you type.</p>
<p>Before you\'ve typed anything, the page shows <strong>suggestions</strong> - people you might know. These lean on friends of your friends, ranked by how many friends you have in common, so the more connected you are, the more relevant they get. If you\'re new and haven\'t added anyone yet, it shows a few people to get you started.</p>
<p>From any person\'s card you can add them as a friend, send a message, or open their profile to see their posts.</p>
',
            ],
            [
                'slug' => 'friends-and-friend-requests',
                'title' => 'Friends and friend requests',
                'category' => 'Connecting',
                'summary' => 'Send, accept, and manage friend requests, and remove friends.',
                'body' => '
<p>Friendship on Glommer is mutual and starts with a request. Use <strong>Add Friend</strong> on someone\'s card or profile to send one. While it\'s pending you can <strong>Cancel</strong> it; once they accept, you\'re friends.</p>
<p>The <strong>Friends</strong> page is your hub for this:</p>
<ul>
<li>Requests waiting for you appear at the top, each with <strong>Accept</strong> and <strong>Deny</strong>.</li>
<li>Below that are your current friends.</li>
<li>Requests you\'ve sent that haven\'t been answered yet are listed too.</li>
</ul>
<p>Any friend\'s card has a <strong>Remove Friend</strong> button if you want to end the connection. Becoming friends means their posts show up in your <a href="/help/your-feeds">Friends Feed</a>.</p>
',
            ],
            [
                'slug' => 'messaging',
                'title' => 'Messaging',
                'category' => 'Connecting',
                'summary' => 'Send private messages and keep up with your conversations.',
                'body' => '
<p>To message someone, use the <strong>Message</strong> button on their card or profile, or open <strong>Messages</strong> from the menu and pick a conversation. Type in the box at the bottom and send - the other person gets a notification, and new messages appear live without reloading the page.</p>
<p>The Messages page lists your conversations with the most recent at the top, so it\'s easy to pick up where you left off. Messages are private between you and the other person.</p>
<p>If someone is bothering you, you can <a href="/help/blocking-someone">block</a> them or <a href="/help/reporting-abuse">report</a> a specific message.</p>
',
            ],
            [
                'slug' => 'your-feeds',
                'title' => 'Your feeds',
                'category' => 'Connecting',
                'summary' => 'The difference between the main feed and your Friends Feed.',
                'body' => '
<p>There are two feeds:</p>
<ul>
<li><strong>The main feed</strong> - your home page - shows posts from across the whole site. It\'s public and global, a good way to discover people and things you\'re not yet connected to.</li>
<li><strong>Friends Feed</strong> shows posts only from people you\'re friends with, for when you just want to catch up with your circle.</li>
</ul>
<p>Both feeds load more as you scroll, so you can keep going back through older posts. To fill out your Friends Feed, <a href="/help/finding-people">find some people</a> and <a href="/help/friends-and-friend-requests">add them as friends</a>.</p>
',
            ],
            [
                'slug' => 'notifications',
                'title' => 'Notifications',
                'category' => 'Connecting',
                'summary' => 'How you hear about likes, replies, friend requests, and messages.',
                'body' => '
<p>The <strong>Notifications</strong> item in the menu lights up when something happens that involves you - someone likes or replies to your post, sends or accepts a friend request, messages you, or your uploaded media finishes processing.</p>
<p>Notifications arrive <strong>live</strong>: you don\'t need to refresh the page to see them. Open the notifications area for a quick list of the most recent, or the full Notifications page for your whole history, which loads more as you scroll.</p>
<p>Each notification links straight to whatever it\'s about, so a tap takes you to the post, conversation, or profile in question.</p>
',
            ],
            [
                'slug' => 'blocking-someone',
                'title' => 'Blocking someone',
                'category' => 'Staying safe',
                'summary' => 'Cut off contact with a user and hide them from your view.',
                'body' => '
<p>Blocking is the tool for when you simply don\'t want anything more to do with someone. Use the <strong>Block</strong> button on their card or profile.</p>
<p>When you block someone, any existing friendship between you ends, and their content stops appearing for you. It\'s a personal setting - it changes your own experience rather than penalising the other person.</p>
<p>You can reverse it at any time: a blocked person\'s card shows an <strong>Unblock</strong> button.</p>
<p>Blocking is about your comfort. If someone is being abusive - not just annoying - please also <a href="/help/reporting-abuse">report the abusive content</a> so it can be dealt with.</p>
',
            ],
            [
                'slug' => 'reporting-abuse',
                'title' => 'Reporting abuse',
                'category' => 'Staying safe',
                'summary' => 'Report the specific abusive post, message, or user so moderators can act.',
                'body' => '
<p>If you come across something abusive, you can report it for a moderator to review. The most important thing to know is this:</p>
<p><strong>Report the specific piece of content that\'s abusive.</strong> If a particular post is the problem, use the <strong>Report</strong> button on <em>that post</em>. If it\'s a message, report <em>that message</em>. Reporting the exact item - rather than just complaining about a person in general - is what lets a moderator see precisely what you saw and act on it quickly.</p>
<h2>How to report</h2>
<ul>
<li><strong>A post:</strong> use the Report action on the post itself.</li>
<li><strong>A message:</strong> use the Report action on that message in your conversation.</li>
<li><strong>A user:</strong> if the whole account is the problem, use the Report button on their profile or card - but where a single post or message is what crossed the line, report that item directly instead.</li>
</ul>
<p>You can add a short reason to explain what\'s wrong, which helps whoever reviews it. Again: point the report at <strong>the particular abusive content</strong> whenever you can - a specific post or message is far easier and faster to act on than a general complaint.</p>
<p>Reporting is private; the person you report isn\'t told who reported them. If you also just want them out of your own view, you can <a href="/help/blocking-someone">block them</a> as well.</p>
',
            ],
            [
                'slug' => 'settings-and-appearance',
                'title' => 'Settings and appearance',
                'category' => 'Your account',
                'summary' => 'Change your password and pick a theme that suits you.',
                'body' => '
<p>Open <strong>Settings</strong> from your name in the top-right corner. There you can:</p>
<ul>
<li><strong>Change your password</strong> - you\'ll enter your current one to confirm it\'s you.</li>
<li><strong>Pick a theme</strong> - choose from System, Light, Dark, Sepia, Midnight, and Sunset. System follows whatever your device is set to; the rest lock in a look you like. Your choice is remembered.</li>
</ul>
<p>Forgotten your password and can\'t log in? Use the <strong>Forgot password?</strong> link on the login page to get a reset link by email.</p>
',
            ],
            [
                'slug' => 'following-with-rss',
                'title' => 'Following along with RSS',
                'category' => 'Your account',
                'summary' => 'Subscribe to the site or a person in any RSS reader.',
                'body' => '
<p>Prefer to read in a feed reader? Glommer publishes <strong>RSS feeds</strong> you can subscribe to:</p>
<ul>
<li>The whole site\'s recent posts are at <code>/feed.xml</code>.</li>
<li>Any person\'s posts have their own feed at <code>/users/their-username/feed.xml</code>.</li>
</ul>
<p>Most browsers and feed readers also pick these up automatically when you visit the site or a profile, thanks to the discovery links on each page - so you can often just paste the page\'s address into your reader and it will find the feed for you.</p>
',
            ],
        ];
    }
}
