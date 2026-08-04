<?php

declare(strict_types=1);

/**
 * The Help section's articles, authored here in source (they're static,
 * developer-written reference pages, so PHP is their single source of truth -
 * no database table to seed or keep in sync). HelpArticle wraps each one for
 * rendering; this class is the corpus and the search over it.
 *
 * Article bodies are trusted HTML written here, rendered through HelpArticleBody
 * (an HTMLLoader). The site's own name is the one thing in them that isn't
 * written here - see siteTitle(), which escapes it.
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
     * The site's own name, for the article bodies that talk about it. Escaped
     * because those bodies are parsed as HTML and this is the one part of them
     * an admin writes rather than us.
     */
    private static function siteTitle(): string
    {
        return htmlspecialchars((string) Config::get('siteTitle'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

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
        // array_filter's default callback drops "0" as falsy - explicit
        // check so searching for exactly "0" doesn't silently fall through
        // to "no terms" (which returns every article instead).
        $terms = array_values(array_filter(
            preg_split('/\s+/', strtolower(trim($query))),
            static fn (string $term): bool => $term !== ''
        ));

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
<p>You can add a <strong>display name</strong> later by editing <a href="/help/your-profile">your profile</a> - that\'s the friendlier name shown above your username on your posts and profile. If you leave it blank, your username is shown instead.</p>
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
<li><strong>The site name</strong> on the left always takes you home to the main feed.</li>
<li><strong>Friends Feed</strong> shows posts from just the people you\'re friends with.</li>
<li><strong>Friends</strong> is where you accept requests and manage who you\'re connected to.</li>
<li><strong>Users</strong> lets you search for people and see suggestions of who to connect with.</li>
<li><strong>Tags</strong> and <strong>Trending</strong> help you discover posts by topic and see what\'s popular right now.</li>
<li><strong>Search</strong> looks across the site\'s posts.</li>
<li><strong>Messages</strong> holds your private conversations.</li>
<li><strong>Bookmarks</strong> is your private list of saved posts.</li>
<li><strong>Notifications</strong> lights up when someone interacts with you.</li>
<li><strong>Help</strong> (these articles) and <strong>About</strong> are there whether or not you\'re logged in.</li>
</ul>
<p>Your own name sits at the top-right - it links to your profile, and opens your <strong>Settings</strong> and the <strong>log out</strong> option.</p>
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
<li>A <strong>poll</strong>, if you\'re asking a question rather than making a statement (see <a href="/help/polls">Asking a question with a poll</a>).</li>
</ul>
<p>You can dress your writing up with formatting, emoji, and even math, and you can attach photos, video, or audio. Those each have their own article. Note that a single post carries a link, attached media, <em>or</em> a poll - one of the three, never a combination.</p>
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
<p>Use the attach control in the composer to add <strong>images, video, or audio</strong> to a post. You can attach more than one file - several images become a swipeable gallery on the finished post. You can attach up to ' . (int) ini_get('max_file_uploads') . ' files to a single post.</p>
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
<p>A post can carry a link, attached media, or a poll - one of the three, not several.</p>
',
            ],
            [
                'slug' => 'polls',
                'title' => 'Asking a question with a poll',
                'category' => 'Posting',
                'summary' => 'Attach a poll to a post and let people answer it, here and across the Fediverse.',
                'body' => '
<p>Press <strong>Add Poll</strong> in the composer to turn a post into a question. You get up to four options, and the post\'s own writing is the question - a poll with no words asks nothing, so it won\'t publish without them.</p>
<p>Two choices to make before you post:</p>
<ul>
<li><strong>Allow more than one choice</strong> - tick it and people can pick several options; leave it and they pick exactly one.</li>
<li><strong>How long it runs</strong> - from five minutes to seven days. Every poll ends, because a result nobody can call final isn\'t a result.</li>
</ul>
<p>Two options can\'t read the same, even in different capitals. An answer names the option by its text rather than by its position, so two that look alike couldn\'t be told apart when one came back.</p>
<p>A poll goes in place of media or a link, not alongside them - the options are the thing to interact with.</p>
<h2>Answering one</h2>
<p>Pick your option or options and press <strong>Vote</strong>. Your answer is final: there\'s no changing it afterwards, since being able to switch after seeing the running total would make the total the thing being voted on.</p>
<p>Once you\'ve answered - or once the poll closes - the options are replaced by the results: each one\'s share of the vote with the number behind it, and your own choice marked. Underneath, how many people have answered and how long is left. On a poll that takes several choices those are different numbers, which is why the count is of people rather than of votes.</p>
<p>If you haven\'t voted you\'ll still see the results once it closes, and so will anyone reading while logged out.</p>
<h2>Across the Fediverse</h2>
<p>Polls travel. People on other servers can answer yours and their votes count here, and you can answer polls from accounts you follow elsewhere. For a poll from another server the numbers are that server\'s to keep - we show what it last told us, so a total can lag slightly behind the vote you just cast.</p>
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
<p>Before you\'ve typed anything, the page shows <strong>suggestions</strong> - people you might know. These are friends of your friends, ranked by how many friends you have in common, so the more connected you are, the more relevant they get.</p>
<p>If it\'s empty, that isn\'t a fault: it means there\'s nobody left to suggest, either because you haven\'t added anyone yet or because you\'ve already added everyone your friends know. Search by name to find anyone else - the search covers everybody, suggested or not.</p>
<p>From any person\'s card you can add them as a friend, send a message, or open their profile to see their posts.</p>
',
            ],
            [
                'slug' => 'friends-and-friend-requests',
                'title' => 'Friends and friend requests',
                'category' => 'Connecting',
                'summary' => 'Send, accept, and manage friend requests, and remove friends.',
                'body' => '
<p>Friendship on ' . self::siteTitle() . ' is mutual and starts with a request. Use <strong>Add Friend</strong> on someone\'s card or profile to send one. While it\'s pending you can <strong>Cancel</strong> it; once they accept, you\'re friends.</p>
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
<p>The Messages page lists your conversations with the most recent at the top, so it\'s easy to pick up where you left off.</p>
<p>Messages are private from other members. How private they are from the server itself depends on the conversation, and the thread always says which case it is:</p>
<ul>
<li><strong>End-to-end encrypted</strong> - once both of you have turned on <a href="/help/encrypted-messages">encrypted messages</a>, new messages are locked and unlocked in your browsers, and what this server stores and keeps in its backups is ciphertext.</li>
<li><strong>Plain</strong> - otherwise, like most social sites, the server stores messages readably, so the operator of a server could technically access them.</li>
<li><strong>Federated</strong> - a conversation with someone on another Fediverse server is also stored on <em>their</em> server, under its operator, and can never be end-to-end encrypted - the Fediverse\'s messaging protocol has no way to carry it. Don\'t put anything in a federated message that must stay secret from everyone but the recipient.</li>
</ul>
<p>If someone is bothering you, you can <a href="/help/blocking-someone">block</a> them or <a href="/help/reporting-abuse">report</a> a specific message - encrypted ones included.</p>
',
            ],
            [
                'slug' => 'encrypted-messages',
                'title' => 'Encrypted messages',
                'category' => 'Connecting',
                'summary' => 'Turn on end-to-end encryption for your conversations, and what the passphrase means.',
                'body' => '
<p>Turn on <strong>Encrypted Messages</strong> in <a href="/settings">Settings</a> by choosing a passphrase. Your browser creates an encryption key of its own, locks it under that passphrase, and stores only the locked copy on the server - the passphrase itself never leaves your device, and the server never holds a key it could read your messages with.</p>
<p>A conversation becomes end-to-end encrypted once <em>both</em> people have turned this on - it takes both keys. From then on, new messages in that thread are unlocked and read in your browsers only; older messages from before stay as they were. Conversations with people on other Fediverse servers are never encrypted, because the protocol between servers has no way to carry it - those threads say so.</p>
<p>Opening an encrypted conversation asks for your passphrase once per browser tab. Because the locked copy of your key lives on the server, the same passphrase works from any browser or device.</p>
<p><strong>There is no way to recover a lost passphrase</strong> - not by you, not by the administrator; that is the point of the design. If you lose it, the reset option in Settings creates fresh keys under a new passphrase, but messages encrypted with the old keys are gone for good. You can change your passphrase any time in Settings without losing anything, as long as you still know the current one.</p>
<p><strong>Check the safety code.</strong> Every encrypted conversation shows a short code standing for the two keys it is locked with. Read it to each other some other way - out loud, in person, over a call - and if it matches on both sides, nobody is sitting between you. It is worth doing once, because the server is what tells each of your browsers the other\'s key, and a dishonest one could hand over a key of its own; it cannot make both of your codes agree, so a mismatch is the tell. Mark it verified once you have checked, and you will be warned if it ever changes - which happens when one of you resets your keys, and is also what an interception would look like.</p>
<p>What this does <em>not</em> cover: the code is worked out by the page you are reading, so it can only tell you about the keys, not about the software itself. Nobody reading the database, the backups, or the traffic can read your messages - that part is unconditional.</p>
<p>You can still <a href="/help/reporting-abuse">report</a> an encrypted message. Reporting reveals to the moderators the content of that one message only - never your key, and never the rest of the conversation - and the server checks the revealed message against a seal it made when it relayed it, so a report can\'t be faked either.</p>
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
                'slug' => 'video-calling',
                'title' => 'Video calling',
                'category' => 'Connecting',
                'summary' => 'Start a private video call with someone while you both have the conversation open.',
                'body' => '
<p>You can have a one-to-one video call with someone from a message thread. The call is <strong>peer-to-peer</strong>: your video and audio go directly between the two browsers, so nobody else - this site included - can see or hear it.</p>
<h2>Starting a call</h2>
<p>A <strong>Video call</strong> button appears while both of you have the thread open. Before showing it, the site quietly checks that your two browsers can actually reach each other; if they cannot, the call is simply not offered rather than being routed through anything else. The other person can accept or decline.</p>
<h2>During a call</h2>
<p>Answering replaces the thread with the call: the other person fills the space the messages were in, with your own camera in a small inset. <strong>End call</strong> hangs up. Declining, hanging up, or either of you leaving the page ends the call, and the conversation comes back exactly as it was.</p>
<h2>If the button never appears</h2>
<p>That means one of the two browsers could not be reached, and it is usually the network rather than the site - a browser without WebRTC, or a firewall blocking the port the two use to find each other. <a href="/settings">Settings</a> has a <strong>Video Calling</strong> check that runs the setup steps against your own browser and says which one fails, so you can tell a browser problem from a network one.</p>
<h2>Privacy</h2>
<p>No video or audio ever passes through this site. The only thing relayed is the short setup message the two browsers use to find each other, and nothing is recorded.</p>
',
            ],
            [
                'slug' => 'blocking-someone',
                'title' => 'Blocking someone',
                'category' => 'Staying safe',
                'summary' => 'Cut off contact with a user and hide them from your view.',
                'body' => '
<p>Blocking is the tool for when you simply don\'t want anything more to do with someone. Use the <strong>Block</strong> button on their card or profile.</p>
<p>When you block someone, any existing friendship between you ends, and direct interaction (messaging, likes, comments) is restricted. Blocks do not affect visibility as the site is entirely public and simply logging out would circumvent that. It\'s a personal setting - it changes your own experience rather than penalising the other person.</p>
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
                'summary' => 'A tour of your Settings page - password, email, security, themes, and more.',
                'body' => '
<p>Open <strong>Settings</strong> from your name in the top-right corner. It gathers everything about your account in one place:</p>
<ul>
<li><strong>Change Password</strong> and <a href="/help/changing-your-email">Change Email</a> - both ask for your current password to confirm it\'s you.</li>
<li><a href="/help/two-factor-authentication">Two-Factor Authentication</a> - an extra step at login for security.</li>
<li><strong>Theme</strong> - change how the site looks (see below).</li>
<li><a href="/help/signed-in-devices">Remembered Devices and Sessions</a> - review where you\'re signed in, and sign out everywhere.</li>
<li><a href="/help/video-calling">Video Calling</a> - check whether calls can work from this browser and network.</li>
<li><a href="/help/following-on-the-fediverse">Fediverse</a> - follow accounts on other servers.</li>
<li><strong>Moving Servers</strong> - take your followers with you to an account elsewhere on the Fediverse.</li>
<li><a href="/help/deleting-your-account">Delete Account</a> - close your account for good.</li>
</ul>
<h2>Themes</h2>
<p>The <strong>Theme</strong> picker restyles the whole site, and your choice is remembered. Choose <strong>Match System</strong> to follow whatever your device is set to, or lock in one of the built-in looks: Light, Dark, Sepia, Midnight, Sunset, Rose, Forest, Ocean, Lavender, Gold, and Hacker.</p>
<p>Forgotten your password and can\'t log in? Use the <strong>Forgot password?</strong> link on the login page to get a reset link by email.</p>
',
            ],
            [
                'slug' => 'following-with-rss',
                'title' => 'Following along with RSS',
                'category' => 'Your account',
                'summary' => 'Subscribe to the site or a person in any RSS reader.',
                'body' => '
<p>Prefer to read in a feed reader? ' . self::siteTitle() . ' publishes <strong>RSS feeds</strong> you can subscribe to:</p>
<ul>
<li>The whole site\'s recent posts are at <code>/feed.xml</code>.</li>
<li>Any person\'s posts have their own feed at <code>/users/their-username/feed.xml</code>.</li>
</ul>
<p>Most browsers and feed readers also pick these up automatically when you visit the site or a profile, thanks to the discovery links on each page - so you can often just paste the page\'s address into your reader and it will find the feed for you.</p>
',
            ],
            [
                'slug' => 'editing-a-post',
                'title' => 'Editing a post',
                'category' => 'Posting',
                'summary' => 'Fix a typo or reword one of your own posts after publishing.',
                'body' => '
<p>Made a mistake? On any post you wrote, you\'ll find an <strong>edit</strong> action. It reopens the post in the editor so you can fix a typo, reword something, or adjust its formatting, then save your changes.</p>
<p>The edit replaces the post in place for everyone - there\'s no separate "edited" copy kept. You can change the title, the writing, and its formatting this way. Swapping a post\'s attached media or its link isn\'t part of editing, though; if that\'s what you need, it\'s cleaner to <a href="/help/deleting-a-post">delete</a> the post and put up a new one.</p>
<p>You can only edit your own posts.</p>
',
            ],
            [
                'slug' => 'reposting-posts',
                'title' => 'Reposting',
                'category' => 'Posting',
                'summary' => 'Pass someone\'s post on to your friends and followers.',
                'body' => '
<p>Press <strong>Repost</strong> on a post to pass it on. It shows up in your friends\' feeds and on your own profile, marked with your name and sorted by when you passed it on - and your Fediverse followers are told too. The button becomes <strong>Unrepost</strong>, which takes it back.</p>
<p>The count beside the button adds reposts made here and boosts from other servers into one number. You can\'t repost your own post - your profile already carries it.</p>
',
            ],
            [
                'slug' => 'bookmarking-posts',
                'title' => 'Bookmarking posts',
                'category' => 'Posting',
                'summary' => 'Save posts to your private Bookmarks list to find them again later.',
                'body' => '
<p>Every post has a <strong>bookmark</strong> action. Tap it to save the post to your <strong>Bookmarks</strong>; tap again to remove it. Bookmarking is private - no one else can see what you\'ve saved, and the author isn\'t notified.</p>
<p>Open <strong>Bookmarks</strong> from the menu to see everything you\'ve kept, most recent first. It\'s the place to stash a post you want to read properly later, or one you suspect you\'ll want to find again.</p>
<p>If a post you bookmarked is later deleted by its author, it simply drops out of your list.</p>
',
            ],
            [
                'slug' => 'hashtags-and-mentions',
                'title' => 'Hashtags and mentions',
                'category' => 'Posting',
                'summary' => 'Tag posts with #hashtags and bring people in with @mentions.',
                'body' => '
<p>Two kinds of shortcut turn into links automatically when you post - just type them into your writing.</p>
<h2>Hashtags</h2>
<p>Write a <strong>#hashtag</strong> - like <code>#music</code> - and it becomes a link. Tapping it opens that tag\'s page, showing every post using it, so hashtags are a good way to group a topic and to discover posts about things you care about. Browse the active tags any time from <strong>Tags</strong> in the menu, and see which are catching on under <a href="/help/trending-topics">Trending</a>.</p>
<h2>Mentions</h2>
<p>Write an <strong>@username</strong> to mention someone. It links to their profile, and they get a notification that you mentioned them - handy for bringing a specific person into a conversation. You can mention people elsewhere on the Fediverse too, using their full <code>@user@server</code> handle.</p>
',
            ],
            [
                'slug' => 'trending-topics',
                'title' => 'Trending topics',
                'category' => 'Connecting',
                'summary' => 'See which topics and names are being talked about most right now.',
                'body' => '
<p>Open <strong>Trending</strong> from the menu to see what\'s catching on across the site. It gathers the hashtags, names, and topics cropping up in a lot of recent posts and ranks them, so you can tell at a glance what people are talking about.</p>
<p>Each trending item links through to the posts behind it, making it an easy way to jump into an active conversation or find something new. The list is recalculated as activity on the site shifts, so it\'s worth looking back now and then.</p>
',
            ],
            [
                'slug' => 'following-on-the-fediverse',
                'title' => 'Following people on the Fediverse',
                'category' => 'Connecting',
                'summary' => 'Follow accounts on Mastodon and other Fediverse servers.',
                'body' => '
<p>' . self::siteTitle() . ' speaks <strong>ActivityPub</strong>, the standard behind Mastodon and the wider Fediverse, so you can follow people on other servers and read their posts here.</p>
<p>Open <strong>Settings</strong> from your name in the top-right, find the <strong>Fediverse</strong> section, and paste in one or more handles - written like <code>@user@example.social</code> - then press <strong>Follow</strong>. Any separator between multiple handles works, so you can paste a whole list at once.</p>
<p>The same section lists the accounts you already follow so you can keep track of them. Mentioning a remote account by its full <code>@user@server</code> handle in a post works too - see <a href="/help/hashtags-and-mentions">hashtags and mentions</a>.</p>
<h2>Being followed from out there</h2>
<p>You have a Fediverse address of your own: <strong>@your-username@this-site\'s-domain</strong>. Anyone on Mastodon, Threads, or another ActivityPub server can search that handle and follow you - no approval step, since everything here is public anyway.</p>
<p>Your posts travel to your followers as you publish them: text, pictures, video, <a href="/help/polls">polls</a>, sensitive-media covers, even your <a href="/help/your-profile">pinned posts</a> and profile edits. Their likes, boosts, and replies land back here and count like anyone else\'s. Reposting a remote post tells your followers about it too.</p>
<p>If you ever leave for another server, <strong>Moving Servers</strong> in Settings sends your followers along - see the section there for the handshake it needs.</p>
',
            ],
            [
                'slug' => 'searching',
                'title' => 'Searching',
                'category' => 'Connecting',
                'summary' => 'Search the site\'s posts from the Search page.',
                'body' => '
<p><strong>Search</strong> in the menu looks through the site\'s <strong>posts</strong>. Type into the box and matching posts appear as you go, so it\'s the quickest way to find a half-remembered post or dig up something you read a while ago.</p>
<p>Looking for <em>people</em> rather than posts? <a href="/help/finding-people">The Users page</a> is built for that, with its own search and suggestions of who to connect with.</p>
',
            ],
            [
                'slug' => 'two-factor-authentication',
                'title' => 'Two-factor authentication',
                'category' => 'Staying safe',
                'summary' => 'Add a login code emailed to you for an extra layer of security.',
                'body' => '
<p>Two-factor authentication (2FA) adds a second step to signing in, so your password alone isn\'t enough to get into your account. Turn it on from <strong>Settings</strong>, in the <strong>Two-Factor Authentication</strong> section.</p>
<p>Here it works by <strong>email</strong>: once it\'s on, each time you log in we email a short numeric code to your address, and you enter that code to finish signing in. There\'s no authenticator app to enrol - it uses the address you already confirmed, so you\'ll need a <a href="/help/verifying-your-email">verified email</a> for this to be available.</p>
<p>You can switch 2FA off again from the same place whenever you like.</p>
',
            ],
            [
                'slug' => 'signed-in-devices',
                'title' => 'Signed-in devices and sessions',
                'category' => 'Staying safe',
                'summary' => 'Review where you\'re logged in and sign out everywhere at once.',
                'body' => '
<p>Your <strong>Settings</strong> page has two tools for managing where you\'re logged in.</p>
<h2>Remembered devices</h2>
<p>When you choose to stay signed in, that device is remembered so you don\'t have to log in every visit. <strong>Remembered Devices</strong> lists them, and you can revoke any one - useful if you signed in on a shared or public computer, or lost a device.</p>
<h2>Sessions</h2>
<p>The <strong>Sessions</strong> section has a single button that <strong>signs you out everywhere at once</strong>, ending every active session and remembered device. Reach for it if you think someone else may have access to your account - then follow up by <a href="/help/settings-and-appearance">changing your password</a>.</p>
',
            ],
            [
                'slug' => 'your-profile',
                'title' => 'Your profile',
                'category' => 'Your account',
                'summary' => 'Set your display name, bio, and avatar so people know who you are.',
                'body' => '
<p>Your profile is your own page on the site, showing your posts along with a little about you. To edit it, open your <strong>own profile</strong> - your name in the top-right links there - and use the <strong>edit</strong> control on your profile card. You can click your name or bio directly to start editing.</p>
<ul>
<li>Your <strong>display name</strong> is the friendly name shown above your username. Leave it blank and your username is shown instead.</li>
<li>Your <strong>bio</strong> is a short line about yourself. Any links, #hashtags, and @mentions in it become clickable.</li>
</ul>
<p>You can also set an <strong>avatar</strong> - the small picture shown next to your name - by uploading an image from your profile. Your username itself is fixed once you sign up, but your display name, bio, and avatar are yours to change any time.</p>
<h2>Pinned posts</h2>
<p>Every post of yours carries a <strong>Pin</strong> button. Pinning lifts it to a <strong>Pinned</strong> section at the top of your profile - up to five at a time, with <strong>Unpin</strong> to swap them out. Pins travel to the Fediverse too, so other servers show them on your profile there.</p>
',
            ],
            [
                'slug' => 'changing-your-email',
                'title' => 'Changing your email',
                'category' => 'Your account',
                'summary' => 'Update your account\'s email address, with re-verification.',
                'body' => '
<p>To change your email address, open <strong>Settings</strong> and use the <strong>Change Email</strong> section. Enter the new address and confirm with your current password. We\'ll send a verification link to the <strong>new</strong> address - open it to confirm the change, just as you did when you first signed up.</p>
<p>As a safeguard, we also send a note to your <strong>old</strong> address letting it know the email was changed, with a link to reverse it. So if a change ever happens that wasn\'t you, you can undo it from that message. Until the new address is verified, it\'s worth confirming promptly, since things like password resets rely on a verified address.</p>
',
            ],
            [
                'slug' => 'deleting-your-account',
                'title' => 'Deleting your account',
                'category' => 'Your account',
                'summary' => 'Permanently close your account and remove your content.',
                'body' => '
<p>If you want to leave, open <strong>Settings</strong> and use the <strong>Delete Account</strong> section. You\'ll confirm with your password, because this can\'t be undone.</p>
<p>Deleting your account is <strong>permanent</strong>. Your posts, replies, messages, likes, and connections are removed, and your username is freed up. There\'s no way to recover an account once it\'s gone, so be sure it\'s really what you want.</p>
<p>The site\'s administrator account can\'t be deleted, since a site needs at least one admin to keep running.</p>
',
            ],
        ];
    }
}
