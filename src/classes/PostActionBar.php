<?php

declare(strict_types=1);

/**
 * The row of controls under a post. Every value it shows - the counts, and
 * whether the viewer has liked, bookmarked, reposted or pinned this post -
 * arrives from the Post that builds it, hydrated once for the whole page (see
 * Post::fromRowsWithItems). This class runs no queries of its own: a feed is
 * twenty of these, and anything asked here is asked twenty times.
 */
class PostActionBar extends Footer
{
    public ?string $class = 'PostActionBar';

    public const REPLY_GLYPH = '💬';
    public array $mixins = ['d-flex', 'align-items-center', 'gap-3'];

    public ?int $postId = null;
    public ?int $postUserId = null;
    public ?string $postUsername = null;
    public bool $standalone = false;
    public ?int $replyCount = null;
    public ?int $likeCount = null;
    public ?bool $liked = null;
    public ?bool $bookmarked = null;
    public ?bool $reposted = null;
    // A post that came from another server. Its permalink here is a copy, not
    // the address of the thing itself.
    public bool $remote = false;
    public ?int $repostCount = null;
    public ?bool $pinned = null;
    // Whether there is body text a translation could work on, and whether
    // this server can translate at all - both settled by the builder, since
    // this class runs no queries (and Settings reads are queries).
    public bool $translatable = false;

    public function toDOM(): \DOMElement
    {
        // Left-aligned, not pushed to the trailing edge. Against the right
        // edge every button is positioned from the end of the row, so any one
        // of them changing width slides all of its neighbours - and the
        // buttons here are exactly the ones whose wording changes. Anchored at
        // the start, a width that does move only moves what follows it.
        $actions = new Div();
        $actions -> mixins = ['d-flex', 'align-items-center', 'gap-2', 'flex-wrap'];

        // Visible to everyone, signed in or not - but never on a post from
        // another server: sharing is handing someone the permalink, and for
        // one of those the address worth passing on is the original, not this
        // server's copy of it.
        if (!$this -> remote) {
            $actions -> addContent(new PostShareButton(ServerURL::absolute(
                '/users/' . ($this -> postUsername ?? '') . '/' . $this -> postId
            )));
        }

        if ($this -> replyCount !== null && (Auth::check() || $this -> replyCount > 0)) {
            $actions -> addContent($this -> replyButton());
        }

        if (Auth::check()) {
            if ($this -> translatable) {
                $actions -> addContent(new PostTranslateButton());
            }

            $actions -> addContent($this -> likeButton());

            // Not on your own post - passing on your own writing is what your
            // profile is for.
            if ($this -> postUserId !== Auth::id()) {
                $actions -> addContent($this -> repostButton());
            }

            // Beside Repost, being its talkative sibling: pass the post on
            // WITH something to say about it.
            $actions -> addContent(new PostQuoteButton((int) $this -> postId));

            $actions -> addContent($this -> bookmarkButton());

            if ($this -> postUserId === Auth::id()) {
                $actions -> addContent($this -> pinButton());
                $actions -> addContent(new PostEditButton());
                $actions -> addContent($this -> deleteButton());
            } elseif ($this -> postUserId !== 1) {
                // The admin's posts can't be reported (api/report.php rejects
                // it - nobody could act on the report anyway).
                $actions -> addContent($this -> reportButton());
            }
        }

        $this -> contents[] = $actions;

        return parent::toDOM();
    }

    protected function likeButton(): HTMLObject
    {
        return new PostLikeButton((bool) $this -> liked, (int) $this -> likeCount);
    }

    protected function bookmarkButton(): HTMLObject
    {
        return new PostBookmarkButton((bool) $this -> bookmarked);
    }

    protected function replyButton(): HTMLObject
    {
        $link = new Anchor(ServerURL::absolute('/users/' . $this -> postUsername . '/' . $this -> postId), self::replyLabel($this -> replyCount));
        $link -> class = 'Button';

        $name = $this -> replyCount === 0 ? 'Reply' : 'Replies (' . $this -> replyCount . ')';
        $link -> attributes['aria-label'] = $name;
        $link -> attributes['title'] = $name;

        return $link;
    }

    /** The glyph, with the count beside it once there is one. */
    public static function replyLabel(int $reply_count): string
    {
        return $reply_count === 0 ? self::REPLY_GLYPH : self::REPLY_GLYPH . ' ' . $reply_count;
    }


    protected function deleteButton(): HTMLObject
    {
        return new PostDeleteButton($this -> standalone);
    }

    protected function repostButton(): HTMLObject
    {
        return new PostRepostButton((bool) $this -> reposted, (int) $this -> repostCount);
    }

    /** Only ever on your own post - the caller has already checked that. */
    protected function pinButton(): HTMLObject
    {
        return new PostPinButton((bool) $this -> pinned);
    }

    protected function reportButton(): HTMLObject
    {
        return new PostReportButton((int) $this -> postId);
    }
}
