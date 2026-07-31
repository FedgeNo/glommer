<?php

declare(strict_types=1);

class PostActionBar extends Footer
{
    public ?string $class = 'PostActionBar';
    public array $mixins = ['d-flex', 'align-items-center', 'gap-3'];

    public ?int $postId = null;
    public ?int $postUserId = null;
    public ?string $postUsername = null;
    public bool $standalone = false;
    public ?int $replyCount = null;
    public ?int $likeCount = null;
    public ?bool $liked = null;
    public ?bool $bookmarked = null;

    public function toDOM(): \DOMElement
    {
        $actions = new Div();
        $actions -> mixins = ['d-flex', 'align-items-center', 'gap-2', 'ms-auto'];

        // Visible to everyone, signed in or not.
        $actions -> addContent(new PostShareButton(ServerURL::absolute(
            '/users/' . ($this -> postUsername ?? '') . '/' . $this -> postId
        )));

        if ($this -> replyCount !== null && (Auth::check() || $this -> replyCount > 0)) {
            $actions -> addContent($this -> replyButton());
        }

        if (Auth::check()) {
            $actions -> addContent($this -> likeButton());
            $actions -> addContent($this -> bookmarkButton());

            if ($this -> postUserId === Auth::id()) {
                $actions -> addContent($this -> editButton());
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
        // Feed-list callers hydrate these in the page query (correlated
        // subqueries); fall back to per-post queries for one-off use (a
        // standalone PostPage, which loads the post without them).
        $count = $this -> likeCount;

        if ($count === null) {
            $count_stmt = DB::run('
SELECT COUNT(*) AS `likeCount`
    FROM `Likes`
    WHERE `postId` = ?
', 'i', $this -> postId);
            $count_result = mysqli_stmt_get_result($count_stmt);
            $count = (int) mysqli_fetch_assoc($count_result)['likeCount'];
        }

        $already_liked = $this -> liked;

        if ($already_liked === null) {
            $current_user_id = Auth::id();

            $liked_stmt = DB::run('
SELECT 1
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
', 'ii', $this -> postId, $current_user_id);
            mysqli_stmt_store_result($liked_stmt);
            $already_liked = mysqli_stmt_num_rows($liked_stmt) > 0;
        }

        return new PostLikeButton($already_liked, $count);
    }

    protected function bookmarkButton(): HTMLObject
    {
        // Feed-list callers hydrate this in the page query (a correlated
        // subquery); fall back to a live per-post query for one-off use (a
        // standalone PostPage, which loads the post without it).
        $already_bookmarked = $this -> bookmarked;

        if ($already_bookmarked === null) {
            $current_user_id = Auth::id();
            $already_bookmarked = isset(Bookmark::bookmarkedByUserForPosts([$this -> postId], (int) $current_user_id)[$this -> postId]);
        }

        return new PostBookmarkButton($already_bookmarked);
    }

    protected function replyButton(): HTMLObject
    {
        $link = new Anchor(ServerURL::absolute('/users/' . $this -> postUsername . '/' . $this -> postId), self::replyLabel($this -> replyCount));
        $link -> class = 'Button';

        return $link;
    }

    public static function replyLabel(int $reply_count): string
    {
        return $reply_count === 0 ? 'Reply' : 'Replies (' . $reply_count . ')';
    }

    protected function editButton(): HTMLObject
    {
        return new PostEditButton();
    }

    protected function deleteButton(): HTMLObject
    {
        return new PostDeleteButton($this -> standalone);
    }

    protected function reportButton(): HTMLObject
    {
        return new PostReportButton((int) $this -> postId);
    }
}
