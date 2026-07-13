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

    // The reported item, resolved once (resolveTarget) into a kind plus its raw
    // data, so the server render (toDOM) and the AJAX payload (toPayload) build
    // from one source and can't diverge. kind is 'message'|'post'|'user'|
    // 'missing'; data is the message body, a Post, a User, or the notice text.
    public ?string $targetKind = null;
    public User|Post|string|null $targetData = null;

    public function toDOM(): \DOMElement
    {
        // Left: who reported what, the content in question, the reason, and when.
        $details = new Div();
        $details -> class = 'ReportDetails d-flex flex-column gap-2';

        $summary = new Div();
        $summary -> contents[] = ucfirst((string) $this -> targetType) . ' #' . $this -> targetId . ' reported by ';
        $summary -> addContent(new Anchor(ServerURL::absolute('/users/' . $this -> reporterUsername . '/'), $this -> reporterUsername));
        $details -> addContent($summary);

        $details -> addContent($this -> targetContentElement());

        if ($this -> reason !== null) {
            $reason_line = new Paragraph();
            $reason_line -> contents[] = 'Reason: ' . $this -> reason;
            $details -> addContent($reason_line);
        }

        $meta = new RelativeTime((string) $this -> createdAt);
        $meta -> class = 'Muted text-sm ' . $meta -> class;
        $details -> addContent($meta);

        $this -> contents[] = $details;

        // Right: the moderation actions, stacked. The admin (userId 1) can't
        // be banned, so never offer a Ban Reporter button when the admin is
        // the one who filed the report. (The reported user is never the admin -
        // api/report.php rejects reports about admin content - so that side
        // needs no such guard.)
        $actions = new Div();
        $actions -> class = 'ReportActions d-flex flex-column gap-2 ms-auto';

        if ($this -> reporterId !== 1) {
            $actions -> addContent(new BanButton($this -> reporterId, 'Ban Reporter'));
        }

        if ($this -> targetUserId !== null && $this -> targetUsername !== null && $this -> targetUserId !== $this -> reporterId) {
            $actions -> addContent(new BanButton($this -> targetUserId, 'Ban Reported User'));
        }

        // Gate on the resolved kind, not the declared targetType: a target
        // deleted after the queue was fetched resolves to 'missing', and there's
        // nothing left to delete.
        if ($this -> targetKind === 'post' || $this -> targetKind === 'message') {
            $actions -> addContent(new DeleteContentButton((int) $this -> reportId, 'Delete ' . ucfirst($this -> targetKind)));
        }

        $actions -> addContent(new DismissReportButton((int) $this -> reportId));

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

        ['userId' => $card -> targetUserId, 'kind' => $card -> targetKind, 'data' => $card -> targetData] = self::resolveTarget($card -> targetType, $card -> targetId);

        if ($card -> targetUserId !== null) {
            $card -> targetUsername = ($card -> targetData instanceof User ? $card -> targetData : User::load($card -> targetUserId)) ?-> username;
        }

        return $card;
    }

    /**
     * The JSON payload for one report, used by api/report-history.php to feed
     * the client-side ReportCard on scroll. Mirrors the fields toDOM() renders;
     * the reported item rides under `target` as a small kind-tagged union the
     * client rebuilds (a bare Post payload, a message body, an allowlisted user
     * card, or a missing-notice message) - never rendered HTML.
     */
    public function toPayload(): array
    {
        return [
            'reportId' => $this -> reportId,
            'reporterId' => $this -> reporterId,
            'reporterUsername' => $this -> reporterUsername,
            'targetType' => $this -> targetType,
            'targetId' => $this -> targetId,
            'reason' => $this -> reason,
            'createdAt' => $this -> createdAt,
            'targetUserId' => $this -> targetUserId,
            'targetUsername' => $this -> targetUsername,
            'target' => $this -> targetPayload(),
        ];
    }

    /**
     * @param array[] $rows
     * @return array[]
     */
    public static function rowsToPayload(array $rows): array
    {
        $payloads = [];

        foreach ($rows as $row) {
            $payloads[] = self::fromRow($row) -> toPayload();
        }

        return $payloads;
    }

    /** The reported item rendered so a moderator can assess it (see resolveTarget). */
    private function targetContentElement(): HTMLObject
    {
        if ($this -> targetKind === 'message') {
            $quote = new Blockquote((string) $this -> targetData);
            $quote -> class = 'ReportedContent';

            return $quote;
        }

        if ($this -> targetData instanceof HTMLObject) {
            return $this -> targetData;
        }

        return new Notice((string) $this -> targetData);
    }

    /**
     * @return array<string, mixed> the kind-tagged target union for toPayload()
     */
    private function targetPayload(): array
    {
        if ($this -> targetKind === 'message') {
            return ['kind' => 'message', 'body' => (string) $this -> targetData];
        }

        if ($this -> targetKind === 'post' && $this -> targetData instanceof Post) {
            // A bare post on the client (no action bar) - the 0/0/false counts
            // its payload carries go unused there.
            return ['kind' => 'post', 'post' => $this -> targetData -> toPayload(0, 0, false)];
        }

        if ($this -> targetKind === 'user' && $this -> targetData instanceof User) {
            $user = $this -> targetData;

            // Explicit allowlist - a User object also carries email and
            // passwordHash, which must never reach a moderator's console.
            return ['kind' => 'user', 'user' => [
                'userId' => (int) $user -> userId,
                'username' => $user -> username,
                'displayName' => $user -> displayName,
                'image' => $user -> avatarURL(),
                'createdAt' => $user -> createdAt,
            ]];
        }

        return ['kind' => 'missing', 'message' => (string) $this -> targetData];
    }

    /**
     * Resolves the reported item in one query against its own table, returning
     * its kind and raw data so toDOM() and toPayload() build from one
     * resolution: a message's body, a post as the post itself (byline + text +
     * media, no action bar), a user as their profile card. A deleted target
     * resolves to a 'missing' notice.
     *
     * @return array{userId: ?int, kind: string, data: User|Post|string}
     */
    private static function resolveTarget(string $target_type, int $target_id): array
    {
        if ($target_type === 'message') {
            $row = self::loadRow('Messages', 'messageId', $target_id);

            if ($row === null) {
                return ['userId' => null, 'kind' => 'missing', 'data' => 'This message no longer exists.'];
            }

            return ['userId' => (int) $row['senderId'], 'kind' => 'message', 'data' => (string) $row['body']];
        }

        if ($target_type === 'post') {
            $row = self::loadRow('Posts', 'postId', $target_id);

            if ($row === null) {
                return ['userId' => null, 'kind' => 'missing', 'data' => 'This post no longer exists.'];
            }

            return ['userId' => (int) $row['userId'], 'kind' => 'post', 'data' => Post::fromRowWithItems($row)];
        }

        if ($target_type === 'user') {
            $user = User::load($target_id);

            if ($user === null) {
                return ['userId' => null, 'kind' => 'missing', 'data' => 'This user no longer exists.'];
            }

            return ['userId' => $target_id, 'kind' => 'user', 'data' => $user];
        }

        return ['userId' => null, 'kind' => 'missing', 'data' => 'Unknown content type.'];
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
