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
        $summary -> addContents(new Anchor(ServerURL::absolute('/users/' . $this -> reporterUsername . '/'), $this -> reporterUsername));
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

        ['userId' => $card -> targetUserId, 'content' => $card -> targetContent] = self::resolveTarget($card -> targetType, $card -> targetId);

        if ($card -> targetUserId !== null) {
            $card -> targetUsername = ($card -> targetContent instanceof User ? $card -> targetContent : User::load($card -> targetUserId)) ?-> username;
        }

        return $card;
    }

    /**
     * Resolves the reported item in one query against its own table (rather
     * than a separate query just to find its owning userId, then a second one
     * to load the row itself) and renders it so a moderator can judge it: a
     * message's body in a blockquote (it's private and can't be viewed any
     * other way), a post as the post itself (byline + text + media, no action
     * bar), a user as their profile card. A deleted target becomes a plain
     * notice.
     *
     * @return array{userId: ?int, content: HTMLObject}
     */
    private static function resolveTarget(string $target_type, int $target_id): array
    {
        if ($target_type === 'message') {
            $row = self::loadRow('Messages', 'messageId', $target_id);

            if ($row === null) {
                return ['userId' => null, 'content' => new Notice('This message no longer exists.')];
            }

            $quote = new Blockquote((string) $row['body']);
            $quote -> class = 'ReportedContent';

            return ['userId' => (int) $row['senderId'], 'content' => $quote];
        }

        if ($target_type === 'post') {
            $row = self::loadRow('Posts', 'postId', $target_id);

            if ($row === null) {
                return ['userId' => null, 'content' => new Notice('This post no longer exists.')];
            }

            return ['userId' => (int) $row['userId'], 'content' => Post::fromRowWithItems($row)];
        }

        if ($target_type === 'user') {
            $user = User::load($target_id);

            return ['userId' => $target_id, 'content' => $user ?? new Notice('This user no longer exists.')];
        }

        return ['userId' => null, 'content' => new Notice('Unknown content type.')];
    }

    private static function loadRow(string $table, string $id_column, int $id): ?array
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `' . $table . '`
    WHERE `' . $id_column . '` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }
}
