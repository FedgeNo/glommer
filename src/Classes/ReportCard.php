<?php

declare(strict_types=1);

class ReportCard extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Card ReportCard d-flex gap-3 align-items-start';

    public ?int $reportId = null;
    public ?int $reporterId = null;
    public ?string $reporterUsername = null;
    public ?string $targetType = null;
    public ?int $targetId = null;
    public ?string $reason = null;
    public ?string $createdAt = null;
    public ?int $targetUserId = null;
    public ?string $targetUsername = null;

    /** The reported content itself, rendered so a moderator can assess it. */
    public ?HTMLObject $targetContent = null;

    public function toDOM(): \DOMElement
    {
        // Left: who reported what, the content in question, the reason, and when.
        $details = new Div();
        $details -> class = 'ReportDetails d-flex flex-column gap-2';

        $summary = new Div();
        $summary -> contents[] = ucfirst((string) $this -> targetType) . ' #' . $this -> targetId . ' reported by ';
        $summary -> addContents(new Anchor(URL::absolute('/users/' . $this -> reporterUsername . '/'), $this -> reporterUsername));
        $details -> addContents($summary);

        if ($this -> targetContent !== null) {
            $details -> addContents($this -> targetContent);
        }

        if ($this -> reason !== null) {
            $reason_line = new Paragraph();
            $reason_line -> contents[] = 'Reason: ' . $this -> reason;
            $details -> addContents($reason_line);
        }

        $meta = new RelativeTime((string) $this -> createdAt);
        $meta -> class = 'Muted text-sm ' . $meta -> class;
        $details -> addContents($meta);

        $this -> contents[] = $details;

        // Right: the moderation actions, stacked. The admin (userId 1) can't
        // be banned, so never offer a Ban Reporter button when the admin is
        // the one who filed the report. (The reported user is never the admin -
        // api/report.php rejects reports about admin content - so that side
        // needs no such guard.)
        $actions = new Div();
        $actions -> class = 'ReportActions d-flex flex-column gap-2 ms-auto';

        if ($this -> reporterId !== 1) {
            $actions -> addContents(new BanButton($this -> reporterId, 'Ban Reporter (' . $this -> reporterUsername . ')'));
        }

        if ($this -> targetUserId !== null && $this -> targetUsername !== null && $this -> targetUserId !== $this -> reporterId) {
            $actions -> addContents(new BanButton($this -> targetUserId, 'Ban Reported User (' . $this -> targetUsername . ')'));
        }

        if ($this -> targetType === 'post' || $this -> targetType === 'message') {
            $actions -> addContents(new DeleteContentButton((int) $this -> reportId, 'Delete ' . ucfirst((string) $this -> targetType)));
        }

        $actions -> addContents(new DismissReportButton((int) $this -> reportId));

        $this -> contents[] = $actions;

        return parent::toDOM();
    }

    public static function fromRow(array $row): self
    {
        $card = new self();
        $card -> reportId = (int) $row['reportId'];
        $card -> reporterId = (int) $row['reporterId'];
        $card -> reporterUsername = $row['reporterUsername'];
        $card -> targetType = $row['targetType'];
        $card -> targetId = (int) $row['targetId'];
        $card -> reason = $row['reason'];
        $card -> createdAt = $row['createdAt'];

        $target_user_id = Report::resolveTargetUserId($card -> targetType, $card -> targetId);
        $card -> targetUserId = $target_user_id;

        if ($target_user_id !== null) {
            $target_user = User::load($target_user_id);
            $card -> targetUsername = $target_user ?-> username;
        }

        $card -> targetContent = self::buildTargetContent($card -> targetType, $card -> targetId);

        return $card;
    }

    /**
     * Renders the reported item so a moderator can judge it: a message's body
     * in a blockquote (it's private and can't be viewed any other way), a post
     * as the post itself (byline + text + media, no action bar), a user as
     * their profile card. A deleted target becomes a plain notice.
     */
    private static function buildTargetContent(string $target_type, int $target_id): HTMLObject
    {
        if ($target_type === 'message') {
            $body = Report::messageBody($target_id);

            if ($body === null) {
                return new Notice('This message no longer exists.');
            }

            $quote = new Blockquote($body);
            $quote -> class = 'ReportedContent';

            return $quote;
        }

        if ($target_type === 'post') {
            return self::loadPost($target_id) ?? new Notice('This post no longer exists.');
        }

        if ($target_type === 'user') {
            return User::load($target_id) ?? new Notice('This user no longer exists.');
        }

        return new Notice('Unknown content type.');
    }

    private static function loadPost(int $post_id): ?Post
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row === null ? null : Post::fromRowWithItems($row);
    }
}
