<?php

declare(strict_types=1);

class PostActionBar extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'PostActionBar d-flex align-items-center gap-3';

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
        $actions -> class = 'd-flex align-items-center gap-2 ms-auto';

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
        // Batched callers (Thread::fromRows) provide these up front; fall back
        // to per-post queries for one-off use (a standalone PostPage).
        if ($this -> likeCount !== null) {
            $count = $this -> likeCount;
        } else {
            $count_stmt = mysqli_prepare(Database::connection(), '
SELECT COUNT(*) AS `likeCount`
    FROM `Likes`
    WHERE `postId` = ?
');
            mysqli_stmt_bind_param($count_stmt, 'i', $this -> postId);
            mysqli_stmt_execute($count_stmt);
            $count_result = mysqli_stmt_get_result($count_stmt);
            $count = (int) mysqli_fetch_assoc($count_result)['likeCount'];
        }

        if ($this -> liked !== null) {
            $already_liked = $this -> liked;
        } else {
            $current_user_id = Auth::id();

            $liked_stmt = mysqli_prepare(Database::connection(), '
SELECT 1
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
');
            mysqli_stmt_bind_param($liked_stmt, 'ii', $this -> postId, $current_user_id);
            mysqli_stmt_execute($liked_stmt);
            mysqli_stmt_store_result($liked_stmt);
            $already_liked = mysqli_stmt_num_rows($liked_stmt) > 0;
        }

        $button = new Button();
        $button -> class = 'Btn LikeButton';
        $button -> attributes['data-item-id'] = (string) $this -> postId;
        $button -> attributes['data-liked'] = $already_liked ? '1' : '0';
        $button -> contents[] = self::likeLabel($already_liked, $count);

        return $button;
    }

    public static function likeLabel(bool $liked, int $count): string
    {
        return ($liked ? 'Unlike' : 'Like') . ' (' . $count . ')';
    }

    protected function bookmarkButton(): HTMLObject
    {
        // Batched callers (Thread::fromRows) provide this up front; fall back
        // to a live per-post query for one-off use (a standalone PostPage).
        if ($this -> bookmarked !== null) {
            $already_bookmarked = $this -> bookmarked;
        } else {
            $current_user_id = Auth::id();
            $already_bookmarked = isset(Bookmark::bookmarkedByUserForPosts([$this -> postId], (int) $current_user_id)[$this -> postId]);
        }

        $button = new Button();
        $button -> class = 'Btn BookmarkButton';
        $button -> attributes['data-item-id'] = (string) $this -> postId;
        $button -> attributes['data-bookmarked'] = $already_bookmarked ? '1' : '0';
        $button -> contents[] = self::bookmarkLabel($already_bookmarked);

        return $button;
    }

    public static function bookmarkLabel(bool $bookmarked): string
    {
        return $bookmarked ? 'Bookmarked' : 'Bookmark';
    }

    protected function replyButton(): HTMLObject
    {
        $link = new Anchor(ServerURL::absolute('/users/' . $this -> postUsername . '/' . $this -> postId), self::replyLabel($this -> replyCount));
        $link -> class = 'Btn';

        return $link;
    }

    public static function replyLabel(int $reply_count): string
    {
        return $reply_count === 0 ? 'Reply' : 'Replies (' . $reply_count . ')';
    }

    protected function editButton(): HTMLObject
    {
        $button = new Button();
        $button -> class = 'Btn EditButton';
        $button -> attributes['data-item-id'] = (string) $this -> postId;
        $button -> contents[] = 'Edit';

        return $button;
    }

    protected function deleteButton(): HTMLObject
    {
        $button = new Button();
        $button -> class = 'Btn DeleteButton';
        $button -> attributes['data-item-id'] = (string) $this -> postId;

        if ($this -> standalone) {
            $button -> attributes['data-standalone'] = '1';
        }

        $button -> contents[] = 'Delete';

        return $button;
    }

    protected function reportButton(): HTMLObject
    {
        return new ReportButton('post', $this -> postId);
    }
}
