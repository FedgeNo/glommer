<?php

declare(strict_types=1);

/**
 * How to build one of each converted post-chrome class - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * The classes below whose only words are a name on a glyph - a button that
 * shows an emoji and says what it is in aria-label - are here because that
 * test reads those attributes as well as the text. Post is still absent: its
 * pageTitle() is not called by toDOM() at all, so rendering one proves nothing
 * about it either way.
 *
 * Input is not here either, for an unrelated reason: its only English is an
 * Exception message thrown on a programmer error (an empty input name),
 * never rendered into a page, so there is nothing for a locale to say.
 *
 * ReceivedFriendRequestSection is not here: building one constructs its List
 * (ListSection::__construct calls list() unconditionally), and a List queries
 * the database in its own constructor - it cannot be built from a plain test
 * without one.
 */
return [
    EmojiPickerTriggerButton::class => static fn (): HTMLObject => new EmojiPickerTriggerButton(),

    LinkImageRemoveButton::class => static fn (): HTMLObject => new LinkImageRemoveButton(),

    PostBookmarkButton::class => static fn (): HTMLObject => new PostBookmarkButton(false),

    PostRepostButton::class => static fn (): HTMLObject => new PostRepostButton(false, 0),

    SearchClearButton::class => static fn (): HTMLObject => new SearchClearButton(),

    MapScrubber::class => static fn (): HTMLObject => new MapScrubber(),

    QuotedPost::class => static function (): HTMLObject {
        $quoted = new QuotedPost();
        $quoted -> postId = 1;
        $quoted -> slug = 'alice';
        $quoted -> authorTitle = 'Alice';
        $quoted -> title = 'Example title';
        $quoted -> description = 'Example description';

        return $quoted;
    },

    ScrollToTopButton::class => static fn (): HTMLObject => new ScrollToTopButton(),

    SitePolicyLinks::class => static fn (): HTMLObject => new SitePolicyLinks(),

    SkipLink::class => static fn (): HTMLObject => new SkipLink(),

    // TopicHeading reads entity -> title/type as plain properties; the chip
    // itself is never rendered, so it needs nothing beyond that.
    TopicHeading::class => static function (): HTMLObject {
        $entity = new TrendingEntityChip();
        $entity -> type = 'hashtag';
        $entity -> title = 'example';

        return new TopicHeading($entity);
    },
];
