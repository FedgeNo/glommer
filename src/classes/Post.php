<?php

declare(strict_types=1);

class Post extends Article
{
    protected const DESCRIPTION_SUMMARY_MAX_LENGTH = 160;

    // A post (and a reply, which is just a post with a parentId) is a
    // self-contained, independently distributable item of content - the textbook
    // <article>. The .Post/.Card styling is class-based, so the tag is free to
    // carry the right semantics.
    public ?string $class = 'Post';

    public ?int $postId = null;
    public ?int $userId = null;
    public ?int $parentId = null;
    public ?string $title = null;
    // The derived plaintext form (the "document": <meta>/OG description, RSS
    // summary, FULLTEXT search). The rich content lives in descriptionDelta.
    public ?string $description = null;
    // The complete Quill Delta (JSON), the source both renderers build from.
    public ?string $descriptionDelta = null;
    public ?string $keywords = null;
    public ?string $linkURL = null;
    public ?string $createdAt = null;
    // Set the first time the author edits this post (api/edit-post.php) -
    // null for a never-edited post. Shown as a small "(edited)" marker next
    // to the timestamp; there's no edit history, just this one flag.
    public ?string $editedAt = null;

    // What language this was written in, when the sender said so - null for
    // anything typed here, since there is no way to declare one yet. Read
    // only by translatable() below.
    public ?string $language = null;

    // The id this post has on the server that wrote it - null for writing
    // composed here, which is what makes it publishable to the Fediverse and
    // editable by its author. Declared like every other column so a post built
    // by hand rather than hydrated from a row (api/create-post.php) still
    // answers the question, instead of reading as an undefined property.
    public ?string $remoteObjectURI = null;

    // From the PostLocations side table, attached by fromRowsWithItems - null
    // for a post filed without a place. Shown as a link into the nearby feed
    // under the timestamp.
    public ?float $latitude = null;
    public ?float $longitude = null;
    // The nearest named place, resolved from the local gazetteer by
    // fromRowsWithItems - null when the post has no location, nowhere is close
    // enough to name it, or the Places table hasn't been loaded yet.
    public ?string $placeLabel = null;
    // The post this one quotes, when it is a quote post - see schema.sql's
    // Posts.quotedPostId. The quoted post itself is attached at hydration.
    public ?int $quotedPostId = null;
    public ?QuotedPost $quotedPost = null;
    // What this reply answers and where its thread began, attached at
    // hydration for the whole page at once - null on a post that starts one.
    public ?ThreadContext $threadContext = null;
    // From the Polls side table, attached by fromRowsWithItems - null for the
    // overwhelming majority of posts, which are not polls.
    public ?Poll $poll = null;
    // Who put this post in the feed row it came from, when that was a repost
    // rather than the author being followed - hydrated by the feed queries,
    // null everywhere else.
    public ?string $repostedBySlug = null;
    public ?string $repostedByTitle = null;

    // Hydrated for a whole page at once by fromRowsWithItems, so the action
    // bar can be built without asking anything of its own. Null means nobody
    // filled them in - a lone Post built by hand, which asks for itself.
    public ?bool $reposted = null;
    public ?int $repostCount = null;
    public ?bool $pinned = null;
    // Set once a moderator dismisses a report on this post - blocks it from
    // being reported again (see api/report.php).
    public ?int $reportsDismissed = null;

    // Marks this post's media as something to opt into seeing rather than be
    // shown unasked. Set by the author when posting or editing, and by a
    // moderator on someone else's post; travels both ways over ActivityPub as
    // `sensitive`. It hides media, not words - which is what the rest of the
    // network does with the same flag, and what keeps a post readable.
    public ?int $sensitive = null;

    // The warning to read before this post, where its author wrote one -
    // ActivityStreams' `summary`, which is how the rest of the network carries
    // a content warning. Where there is one it gates the whole body rather
    // than only the media: the commonest warning of all is a spoiler, and the
    // thing being spoiled is usually the words.
    public ?string $contentWarning = null;

    // What the words are in, read off the text rather than taken from what a
    // sender declared (see Posts.language). Null where nothing could tell.
    public ?string $detectedLanguage = null;

    // Whether a media post's description is truncated (with a "See More" link)
    // rather than shown in full. True in the feed, where a post is a preview;
    // PostPage flips it off so the permalink page shows the whole description.
    public bool $truncateDescription = true;

    /** @var FeedItem[] */
    public array $items = [];

    public ?User $author = null;

    // The engagement counts the action bar shows, hydrated as correlated
    // subqueries by the feed-list query that loads the page. Null on a bare
    // Post (e.g. a report snapshot) rendered with showActions off, where the
    // bar - and these - are never used; the action bar falls back to its own
    // per-post lookups when a count is null but a bar is still shown (a
    // standalone PostPage, which loads the post without them).
    public ?int $replyCount = null;
    public ?int $likeCount = null;
    public ?bool $liked = null;
    public ?bool $bookmarked = null;

    // The permalink shows one focused post: its Delete redirects home rather
    // than removing a card in place (PostActionBar reads this). standalone
    // pages also render the description untruncated (truncateDescription off).
    // A permalink's body is not height-capped either - that cap belongs to
    // .FeedList .PostBody, so being outside a feed is what lifts it.
    public bool $standalone = false;

    // A report snapshot embeds only the post's content, no action bar; every
    // feed/permalink render leaves this on so the bar appears.
    public bool $showActions = true;

    public function toDOM(): \DOMElement
    {
        // The post's own columns, carried once on the card that represents it -
        // the content, the action bar's buttons and the JS behind them all read
        // them from here. Attribute names match the column names.
        if ($this -> postId !== null) {
            $this -> attributes['data-post-id'] = (string) $this -> postId;
        }

        if ($this -> parentId !== null) {
            $this -> attributes['data-parent-id'] = (string) $this -> parentId;
        }

        if ($this -> userId !== null) {
            $this -> attributes['data-user-id'] = (string) $this -> userId;
        }

        if ($this -> keywords !== null) {
            $this -> attributes['data-keywords'] = $this -> keywords;
        }

        if ($this -> createdAt !== null) {
            $this -> attributes['data-created-at'] = date(DATE_ATOM, strtotime($this -> createdAt));
        }

        // The raw, untruncated Delta an edit needs to repopulate Quill -
        // toPayload()'s descriptionDelta is truncated for feed display, so
        // editing needs this separately. Only present for the viewer's own
        // post: nobody else can ever open the edit form, and everyone else's
        // feed shouldn't ship data they'll never use. Data, not markup - the
        // client reads it and feeds it straight to Quill.setContents(), no
        // HTML crosses the wire.
        if ($this -> userId !== null && Auth::id() === $this -> userId) {
            $this -> attributes['data-description-delta'] = $this -> descriptionDelta ?? '';
            $this -> attributes['data-title'] = $this -> title ?? '';
            $this -> attributes['data-link-url'] = $this -> linkURL ?? '';

            // The edit form hides the Link field for a media post: attached
            // media and a link are mutually exclusive (api/edit-post.php
            // enforces the same XOR create-post.php always has), and a media
            // post never had a link to begin with, so there's nothing to edit.
            //
            // A link post's preview picture is a FeedItem too, so this asks
            // whether the post IS a media post rather than whether it holds an
            // item. Counting the item alone hid the Link field from the one
            // kind of post whose link is the whole point, and saving then wrote
            // the link away and left the picture behind - a link post silently
            // became an image post.
            $this -> attributes['data-has-media'] = count($this -> items) > 0 && $this -> linkURL === null ? '1' : '';

            // So the edit form opens with the classification the post already
            // carries, rather than silently clearing it on every save.
            $this -> attributes['data-sensitive'] = $this -> sensitive === 1 ? '1' : '';
            $this -> attributes['data-content-warning'] = (string) $this -> contentWarning;
        }

        $this -> contents[] = $this -> contentElement();

        // A report snapshot embeds the content alone; every feed/permalink post
        // carries its action bar (like/reply/bookmark/edit/delete/report).
        if ($this -> showActions) {
            $action_bar = new PostActionBar();
            $action_bar -> postId = (int) $this -> postId;
            $action_bar -> postUserId = (int) $this -> userId;
            $action_bar -> postUsername = $this -> author ?-> slug;
            $action_bar -> replyCount = $this -> replyCount;
            $action_bar -> likeCount = $this -> likeCount;
            $action_bar -> liked = $this -> liked;
            $action_bar -> bookmarked = $this -> bookmarked;
            $action_bar -> reposted = $this -> reposted;
            $action_bar -> repostCount = $this -> repostCount;
            $action_bar -> pinned = $this -> pinned;
            $action_bar -> remote = $this -> remoteObjectURI !== null;
            $action_bar -> standalone = $this -> standalone;
            $action_bar -> translatable = $this -> translatable();

            $this -> contents[] = $action_bar;
        }

        return parent::toDOM();
    }

    /**
     * The post's content on its own - byline, media/link, title, body - as a
     * .PostContent element, without the surrounding card or action bar. The
     * feed/permalink card (toDOM) wraps this plus an action bar; a report
     * snapshot and the client-side edit swap render just this piece.
     */
    public function contentElement(): HTMLObject
    {
        $content = new Div();
        $content -> class = 'PostContent';

        // First of all, because a reply read without it is an answer to a
        // question that is not on the page.
        if ($this -> threadContext !== null) {
            $content -> contents[] = $this -> threadContext;
        }

        // Above the byline, because it answers the question the byline raises:
        // why an unfollowed author's post is in this feed at all.
        if ($this -> repostedBySlug !== null) {
            $content -> contents[] = new RepostAttribution($this -> repostedBySlug, $this -> repostedByTitle);
        }

        if ($this -> author !== null) {
            $content -> contents[] = $this -> authorByline();
        }

        // Everything below is what the author wrote, and where they wrote a
        // warning it is built into that instead of straight onto the card - so
        // a spoiler in the words is covered along with one in the pictures.
        // The byline above stays out: who posted it is not the spoiler, and a
        // gate with nothing identifying it is a gate nobody can judge.
        $warning = trim((string) $this -> contentWarning);
        $body = $warning === '' ? $content : new ContentWarning($warning);

        if ($this -> linkURL !== null) {
            $link_image = null;

            foreach ($this -> items as $item) {
                if ($item instanceof ImageItem) {
                    $link_image = $item;
                    break;
                }
            }

            $body -> contents[] = new LinkItem($this -> linkURL, $this -> title, $this -> description, $link_image);
        } else {
            if ($this -> title !== null) {
                $heading = new Heading3();
                $heading -> contents[] = $this -> title;

                if ($this -> postId !== null && $this -> author !== null) {
                    $title_link = new Anchor(ServerURL::absolute('/users/' . $this -> author -> slug . '/' . $this -> postId));
                    $title_link -> addContent($heading);
                    $body -> contents[] = $title_link;
                } else {
                    $body -> contents[] = $heading;
                }
            }

            foreach ($this -> items as $item) {
                // Only where the item hasn't brought its own: a remote server
                // sends alt text per attachment, which describes that picture,
                // where ours is derived from the post and describes all of them.
                $item -> altText ??= $this -> imageAltText();
            }

            $media = null;

            if (count($this -> items) > 1) {
                $carousel = new Carousel();
                $carousel -> items = $this -> items;
                $media = $carousel;
            } elseif (count($this -> items) === 1) {
                $this -> items[0] -> showFullscreenButton = true;
                $media = $this -> items[0];
            }

            if ($media !== null) {
                // Not under a warning: opening that gate is already the reader
                // asking for what is behind it, and a second cover inside the
                // first only makes them say so twice.
                if ($this -> sensitive === 1 && $warning === '' && !SensitiveMedia::shownByDefault()) {
                    $cover = new SensitiveMedia();
                    $cover -> contents[] = $media;
                    $media = $cover;
                }

                $body -> contents[] = $media;
            }

            if ($this -> descriptionDelta !== null) {
                $body -> contents[] = $this -> truncateDescription
                    ? $this -> summarizedDescription()
                    : $this -> fullDescription();
            }

            // Under the words, since the poll is what the words are asking
            // about.
            if ($this -> poll !== null) {
                $this -> poll -> viewerId = Auth::id();
                $body -> contents[] = $this -> poll;
            }
        }

        // Under everything the author wrote: the commentary is the post, the
        // quoted material is its context. Absent when the quoted post has
        // been deleted or its author banned - the words above stand alone.
        if ($this -> quotedPost !== null) {
            $body -> contents[] = $this -> quotedPost;
        }

        if ($body !== $content) {
            $content -> contents[] = $body;
        }

        return $content;
    }

    protected function hasVisualMedia(): bool
    {
        foreach ($this -> items as $item) {
            if ($item instanceof ImageItem || $item instanceof VideoItem) {
                return true;
            }
        }

        return false;
    }

    /** @return array[] the stored Delta's ops (empty if there's no rich content) */
    protected function descriptionOps(): array
    {
        return Delta::decode($this -> descriptionDelta);
    }

    /** The permalink to this post, used as the "See More" target and RSS/link. */
    protected function seeMoreURL(): ?string
    {
        return $this -> postId !== null && $this -> author !== null
            ? ServerURL::absolute('/users/' . $this -> author -> slug . '/' . $this -> postId)
            : null;
    }

    protected function fullDescription(): HTMLObject
    {
        return new DeltaRenderer($this -> descriptionOps(), $this -> customEmoji(), $this -> mentionsAreLocal());
    }

    /**
     * The custom emoji this post's shortcodes may mean.
     *
     * Scoped to the server the post came from, because that is the only place a
     * custom name has a meaning - and empty for a local post, since nothing
     * here defines any yet.
     *
     * @return array<string, string>
     */
    protected function customEmoji(): array
    {
        return CustomEmoji::forObject(is_string($this -> remoteObjectURI) ? $this -> remoteObjectURI : null);
    }

    protected function summarizedDescription(): HTMLObject
    {
        return new TruncatedDeltaRenderer($this -> descriptionOps(), $this -> seeMoreURL(), TruncatedDeltaRenderer::DEFAULT_MAX_LENGTH, $this -> customEmoji(), $this -> mentionsAreLocal());
    }

    /**
     * Whether a bare "@name" in this post addresses somebody here.
     *
     * Only in a post written here. Elsewhere it names one of the writer's own
     * neighbours, and the account of that name here - if there is one at all -
     * is a different person.
     */
    protected function mentionsAreLocal(): bool
    {
        return $this -> remoteObjectURI === null;
    }

    /**
     * Collapses whitespace in the description, untruncated. The column is
     * already plaintext (Delta::plainText derives it), so - unlike before the
     * Delta migration - there's no markup to strip; doing so would eat literal
     * '<'/'>' a user legitimately typed (or LaTeX like "$x < y$").
     */
    protected function plainTextDescription(): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $this -> description));
    }

    /**
     * The stripped, truncated description shown alongside a post's image(s)
     * or video in the feed - also reused as those images' alt text (via
     * imageAltText()).
     */
    public function shortDescription(): string
    {
        // Same rule as pageTitle(): this summarises the post for places that
        // cannot gate it - structured data, a share card - so a warned post
        // offers its warning instead of the writing it covers.
        if ((string) $this -> contentWarning !== '') {
            return (string) $this -> contentWarning;
        }

        $text = $this -> plainTextDescription();

        if (mb_strlen($text) <= self::DESCRIPTION_SUMMARY_MAX_LENGTH) {
            return $text;
        }

        // mb_substr, not substr - a byte-based cut can split a multibyte
        // character, and the resulting invalid UTF-8 makes json_encode()
        // return false for any payload this ends up in (alt text travels
        // through the create-post and feed JSON responses).
        return rtrim(mb_substr($text, 0, self::DESCRIPTION_SUMMARY_MAX_LENGTH)) . '…';
    }

    /** How much of a post stands in for a title it does not have. */
    private const PAGE_TITLE_MAX_LENGTH = 50;

    /**
     * What to head this post's page with.
     *
     * Its own title when it has one. Otherwise its opening line, which is
     * usually a sentence somebody meant as an opening - far better as a title
     * than the body cut mid-word at fifty characters, which is what a post
     * written as one long paragraph still gets, there being nothing else to
     * take. Only when that line is short enough to be a title on its own: a
     * first line longer than the cut would be truncated anyway, so the whole
     * body may as well be.
     *
     * The lines are only in the delta - description is the flattened form and
     * has none left - so that is what this reads.
     */
    public function pageTitle(): string
    {
        // A warned post names itself by its warning, and does so before
        // anything else - including its own title, which sits behind the gate
        // on the page like the rest of the body. The title is printed in the
        // tab, in the heading above the post, and on every card a share
        // produces, none of which have a gate to sit behind; taking it from
        // the writing would publish the words the warning exists to withhold.
        if ((string) $this -> contentWarning !== '') {
            return (string) $this -> contentWarning;
        }

        if ((string) $this -> title !== '') {
            return (string) $this -> title;
        }

        $paragraphs = Delta::paragraphs(Delta::decode($this -> descriptionDelta));

        if (count($paragraphs) > 1 && mb_strlen($paragraphs[0]) < self::PAGE_TITLE_MAX_LENGTH) {
            return $paragraphs[0];
        }

        $flattened = $this -> plainTextDescription();

        if ($flattened !== '') {
            return truncate($flattened, self::PAGE_TITLE_MAX_LENGTH);
        }

        $name = $this -> author !== null ? ($this -> author -> title ?: $this -> author -> slug) : null;
        $words = Strings::for(self::class);

        return $name !== null
            ? str_replace('{name}', $name, (string) ($words['pageTitleByAuthor'] ?? ''))
            : (string) ($words['pageTitleUntitled'] ?? '');
    }

    /**
     * Alt text for this post's attached image(s). All images in a multi-image
     * carousel share the same alt text, since there's no per-image caption to
     * draw a distinct one from.
     */
    protected function imageAltText(): string
    {
        $text = $this -> description !== null ? $this -> shortDescription() : '';

        if ($text !== '') {
            return $text;
        }

        $name = $this -> author !== null ? ($this -> author -> title ?: $this -> author -> slug) : null;
        $words = Strings::for(self::class);

        return $name !== null
            ? str_replace('{name}', $name, (string) ($words['imageAltByAuthor'] ?? ''))
            : (string) ($words['imageAltUntitled'] ?? '');
    }

    protected function authorByline(): HTMLObject
    {
        $byline = new Header();
        $byline -> class = 'PostByline';

        $byline -> addContent($this -> author -> header());
        $byline -> addContent(new PostMeta($this));

        return $byline;
    }

    public static function fromRow(array $row): self
    {
        $post = new self();

        foreach ($row as $key => $value) {
            $post -> $key = $value;
        }

        return $post;
    }

    public static function fromRowWithItems(self $post): self
    {
        return self::fromRowsWithItems([$post])[0];
    }

    /**
     * How many posts a member has published, for the size their ActivityPub
     * outbox reports. Their own writing only: a row carrying a remoteObjectURI
     * came in from elsewhere and is not theirs to publish back out.
     */
    public static function publishedCountFor(int $user_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Posts`
    WHERE `userId` = ? AND `remoteObjectURI` IS NULL
', 'PostCountData', 'i', $user_id);

        return $row === null ? 0 : (int) $row -> total;
    }

    /**
     * Sets or clears the sensitive classification on its own. The author
     * reclassifies through the ordinary edit, which rewrites the whole row;
     * this is for a moderator acting on somebody else's post, where nothing
     * else about it is theirs to change.
     */
    public static function classify(int $post_id, bool $sensitive): void
    {
        DB::run('
UPDATE `Posts`
    SET `sensitive` = ?
    WHERE `postId` = ?
', 'ii', $sensitive ? 1 : 0, $post_id);
    }

    /**
     * Deletes a post and (via the parentId cascade) its whole reply subtree,
     * cleaning up every descendant's media files - which the row cascade can't
     * do. The single place a post is destroyed, used both by the owner's own
     * delete and by a moderator deleting reported content. Caller is
     * responsible for the authorization check.
     */
    /**
     * Whether to offer this post for translation: there are words, and a
     * translator is configured.
     *
     * Not narrowed by the language the post declares, though it declares one.
     * Mastodon fills that in from the poster's own account setting rather than
     * from the words, so an account set to English writing in French says
     * English - and hiding the button on that reading leaves a reader looking
     * at a language they cannot read with no way to ask. Offering it once too
     * often costs a click; withholding it costs the post.
     */
    public function translatable(): bool
    {
        return (string) $this -> description !== '' && Translator::canTranslate();
    }

    public static function delete(int $post_id): void
    {
        // Collect the post plus all descendant replies, since the row DELETE
        // cascades through them and their media files would otherwise be
        // orphaned on disk.
        $all_post_ids = [$post_id];
        $frontier = [$post_id];

        while ($frontier !== []) {
            $placeholders = implode(', ', array_fill(0, count($frontier), '?'));

            $children_stmt = DB::run('
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
', str_repeat('i', count($frontier)), ...$frontier);
            $children_result = mysqli_stmt_get_result($children_stmt);

            $frontier = [];

            while ($row = mysqli_fetch_assoc($children_result)) {
                $all_post_ids[] = (int) $row['postId'];
                $frontier[] = (int) $row['postId'];
            }
        }

        $doomed_items = [];

        foreach (FeedItem::itemsForPosts($all_post_ids) as $post_items) {
            foreach ($post_items as $item) {
                $doomed_items[] = $item;
            }
        }

        // Notifications.postId carries no FK (it's a loose, per-type
        // reference - not every notification type even uses it - not a
        // strict single-table FK candidate), so nothing cascades these on
        // its own. Without this, a reply/like/postReady notification for a
        // deleted post would point at a 404'ing permalink forever.
        $post_id_placeholders = implode(', ', array_fill(0, count($all_post_ids), '?'));

        DB::run('
DELETE
    FROM `Notifications`
    WHERE `postId` IN (' . $post_id_placeholders . ')
', str_repeat('i', count($all_post_ids)), ...$all_post_ids);

        // A remote-origin post being deleted here (owner delete, moderator
        // delete, report resolution - this is the one place all of them go
        // through) gets tombstoned first: the origin server redelivering the
        // same Create later is expected ActivityPub behavior, not a bug, and
        // a tombstone is what stops that redelivery from copying it back in.
        $remote_object_uris_stmt = DB::run('
SELECT `remoteObjectURI`
    FROM `Posts`
    WHERE `postId` IN (' . $post_id_placeholders . ') AND `remoteObjectURI` IS NOT NULL
', str_repeat('i', count($all_post_ids)), ...$all_post_ids);
        $remote_object_uris_result = mysqli_stmt_get_result($remote_object_uris_stmt);

        while ($row = mysqli_fetch_assoc($remote_object_uris_result)) {
            RemoteObjectTombstone::tombstone((string) $row['remoteObjectURI'], 'post deleted on this site');
        }

        DB::run('
DELETE
    FROM `Posts`
    WHERE `postId` = ?
', 'i', $post_id);

        // Only remove files once the rows are actually gone.
        foreach ($doomed_items as $item) {
            UploadProcessor::deleteForItem((int) $item -> itemId, (string) $item -> type);
        }
    }

    /**
     * Attaches items and authors (batched, one query each rather than a pair
     * per post) to a whole page of already-built Posts at once.
     *
     * @param self[] $posts
     * @return self[]
     */
    public static function fromRowsWithItems(array $posts): array
    {
        if ($posts === []) {
            return [];
        }

        $post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);
        $items_by_post = FeedItem::itemsForPosts($post_ids);

        $user_ids = array_values(array_unique(array_map(fn ($post) => (int) $post -> userId, $posts)));
        $authors = User::loadMany($user_ids);

        $locations = PostLocation::forPosts($post_ids);
        $polls = Poll::forPosts($post_ids);

        // The action bar's own state, gathered for the page rather than per
        // card: whether the viewer reposted it, how many times it has been
        // passed on, and whether it is pinned are three questions each, and a
        // feed would otherwise ask all of them once per post.
        $viewer_id = Auth::id();
        $repost_state = Repost::stateForPosts($post_ids, $viewer_id);
        $pinned = $viewer_id === null ? [] : PinnedPost::pinnedForPosts($post_ids, $viewer_id);
        $quoted = QuotedPost::forPosts($posts);
        $thread_context = ThreadContext::forPosts($posts);

        foreach ($posts as $post) {
            $post -> items = $items_by_post[(int) $post -> postId] ?? [];
            $post -> author = $authors[(int) $post -> userId] ?? null;
            $post -> quotedPost = $quoted[(int) $post -> postId] ?? null;
            $post -> threadContext = $thread_context[(int) $post -> postId] ?? null;

            $location = $locations[(int) $post -> postId] ?? null;
            $post -> latitude = $location['latitude'] ?? null;
            $post -> longitude = $location['longitude'] ?? null;

            if ($location !== null) {
                $post -> placeLabel = Place::nearest($location['latitude'], $location['longitude']) ?-> label();
            }

            $post -> poll = $polls[(int) $post -> postId] ?? null;

            $post -> reposted = $repost_state[(int) $post -> postId]['reposted'] ?? false;
            $post -> repostCount = $repost_state[(int) $post -> postId]['count'] ?? 0;
            $post -> pinned = isset($pinned[(int) $post -> postId]);
        }

        return $posts;
    }

    /**
     * The JSON representation used by AJAX endpoints (create-post, feed)
     * that feed the client-side Post class, which rebuilds the body from the
     * Delta ops via DeltaRenderer.render() - no HTML crosses the wire.
     */
    public function toPayload(int $reply_count, int $like_count, bool $liked, bool $bookmarked): array
    {
        $description_delta = null;
        $description_truncated = false;

        // Whether the poll comes back as controls or as answers depends on who
        // is asking, so it has to be told before it builds its payload - the
        // same thing toDOM() does before rendering it.
        if ($this -> poll !== null) {
            $this -> poll -> viewerId = Auth::id();
        }

        // toPayload only ever feeds the client-side feed, never the permalink
        // page, so it truncates the ops exactly like the server-rendered feed
        // does - and ships the very same truncated ops (one truncate pass, so
        // the '…' and the truncated flag can't drift). The client renders them
        // and appends its own "See More" when descriptionTruncated is set.
        if ($this -> descriptionDelta !== null) {
            $renderer = new TruncatedDeltaRenderer($this -> descriptionOps(), $this -> seeMoreURL());
            $description_delta = $renderer -> ops();
            $description_truncated = $renderer -> wasTruncated();
        }

        $items = [];

        foreach ($this -> items as $item) {
            $items[] = [
                'itemId' => $item -> itemId,
                'itemType' => $item -> type,
                'src' => $item -> srcURL(),
                'image' => $item -> imageURL(),
                'altText' => $item -> altText,
            ];
        }

        $is_own_post = $this -> userId !== null && Auth::id() === $this -> userId;

        return [
            'postId' => (int) $this -> postId,
            'userId' => (int) $this -> userId,
            'parentId' => $this -> parentId !== null ? (int) $this -> parentId : null,
            'title' => $this -> title,
            // Plaintext, used only by the client's link-preview card (its
            // description is shown as flat text, never rich). A regular post
            // body renders from descriptionDelta instead.
            'description' => $this -> description,
            'customEmoji' => (object) $this -> customEmoji(),
            'descriptionDelta' => $description_delta,
            'descriptionTruncated' => $description_truncated,
            // The raw, untruncated Delta an edit needs to repopulate Quill -
            // owner-only, same reasoning as toDOM()'s data-description-delta.
            'rawDescriptionDelta' => $is_own_post ? $this -> descriptionDelta : null,
            'seeMoreURL' => $this -> seeMoreURL(),
            'linkURL' => $this -> linkURL,
            'createdAt' => $this -> createdAt,
            'editedAt' => $this -> editedAt,
            'latitude' => $this -> latitude,
            'longitude' => $this -> longitude,
            'placeLabel' => $this -> placeLabel,
            'poll' => $this -> poll?-> toPayload(),
            'translatable' => $this -> translatable(),
            'language' => $this -> language,
            'quotedPost' => $this -> quotedPost ?-> toPayloadArray(),
            'threadContext' => $this -> threadContext ?-> toPayloadArray(),
            // Whether this came from another server, which decides the share
            // button the same way it does server-side.
            'remote' => $this -> remoteObjectURI !== null,
            'repostedBy' => $this -> repostedBySlug === null ? null : [
                'slug' => $this -> repostedBySlug,
                'title' => $this -> repostedByTitle,
            ],
            // Both come with the page where the post was loaded for one -
            // Repost::stateForPosts answers a screen of them in two queries,
            // and asking again here made it two per post again on every list
            // that goes out as JSON. Asked only by a post built rather than
            // selected.
            'reposted' => $this -> reposted ?? (Auth::check() && Repost::exists((int) Auth::id(), (int) $this -> postId)),
            'repostCount' => $this -> repostCount ?? ActivityPubReaction::announceCount((int) $this -> postId),
            'items' => $items,
            'sensitive' => $this -> sensitive === 1,
            'contentWarning' => $this -> contentWarning,
            'imageAltText' => $this -> imageAltText(),
            'replyCount' => $reply_count,
            'likeCount' => $like_count,
            'liked' => $liked,
            'bookmarked' => $bookmarked,
            // A nested user object with row-named keys so Post.js builds the
            // byline straight through User.fromData, no field-by-field transcode.
            'author' => $this -> author !== null ? [
                'userId' => (int) $this -> author -> userId,
                'slug' => $this -> author -> slug,
                'title' => $this -> author -> title,
                'image' => $this -> author -> avatarURL(),
            ] : null,
        ];
    }
}
