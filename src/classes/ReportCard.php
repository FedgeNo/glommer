<?php

declare(strict_types=1);

class ReportCard extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Card ReportCard d-flex gap-3 align-items-start';

    public ?int $reportId = null;
    public ?int $reporterId = null;
    public ?string $reporterUsername = null;
    public ?string $type = null;
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

    // Whether the live post/message still exists - a deleted one still shows
    // from its snapshot, but its card drops the (now pointless) Delete button.
    public bool $targetLive = false;

    // For a deleted post, the reported attachment (FeedItem) ids, whose kept
    // originals are streamed by the mod-only api/report-attachment.php - the live
    // display copies are gone, so this is the only way to see the media.
    /** @var int[] */
    public array $forensicAttachmentIds = [];

    public function toDOM(): \DOMElement
    {
        // Left: who reported what, the content in question, the reason, and when.
        $details = new Div();
        $details -> class = 'ReportDetails d-flex flex-column gap-2';

        $summary = new Div();
        $summary -> contents[] = ucfirst((string) $this -> type) . ' #' . $this -> targetId . ' reported by ';
        $summary -> addContent(new Anchor(ServerURL::absolute('/users/' . $this -> reporterUsername . '/'), $this -> reporterUsername));
        $details -> addContent($summary);

        $details -> addContent($this -> targetContentElement());

        if ($this -> forensicAttachmentIds !== []) {
            $details -> addContent($this -> forensicAttachmentsElement());
        }

        if ($this -> reason !== null) {
            $reason_line = new Paragraph();
            $reason_line -> contents[] = 'Reason: ' . $this -> reason;
            $details -> addContent($reason_line);
        }

        $meta = new RelativeTime((string) $this -> createdAt);
        $meta -> class = 'muted text-sm ' . $meta -> class;
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

        // Only offer Delete when the live post/message still exists (a snapshot
        // of already-deleted content still shows, but has nothing to delete).
        if ($this -> targetLive && ($this -> type === 'post' || $this -> type === 'message')) {
            $actions -> addContent(new DeleteContentButton((int) $this -> reportId, 'Delete ' . ucfirst($this -> type)));
        }

        $actions -> addContent(new DismissReportButton((int) $this -> reportId));

        $this -> contents[] = $actions;

        return parent::toDOM();
    }

    public static function fromRow(ReportData $row): self
    {
        $card = new self();
        $card -> reportId = (int) $row -> reportId;
        $card -> reporterId = (int) $row -> reporterId;
        $card -> reporterUsername = $row -> reporterUsername;
        $card -> type = $row -> type;
        $card -> targetId = (int) $row -> targetId;
        $card -> reason = $row -> reason;
        $card -> createdAt = $row -> createdAt;

        $snapshot = $row -> snapshot !== null ? json_decode($row -> snapshot, true) : null;
        $snapshot = is_array($snapshot) ? $snapshot : null;

        // A live existence check, not the snapshot: only live post/message content
        // is deletable, and a deleted post renders its reported media forensically.
        $card -> targetLive = Report::contentExists($card -> type, $card -> targetId);

        ['userId' => $card -> targetUserId, 'kind' => $card -> targetKind, 'data' => $card -> targetData] = self::resolveFromSnapshot($card -> type, $snapshot, $card -> targetLive);

        if ($card -> targetKind === 'post' && !$card -> targetLive && $snapshot !== null) {
            $card -> forensicAttachmentIds = array_map('intval', $snapshot['attachmentIds'] ?? []);
        }

        // The target user must still exist to be bannable.
        if ($card -> targetUserId !== null) {
            $card -> targetUsername = User::load($card -> targetUserId) ?-> slug;
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
            'targetType' => $this -> type,
            'targetId' => $this -> targetId,
            'reason' => $this -> reason,
            'createdAt' => $this -> createdAt,
            'targetUserId' => $this -> targetUserId,
            'targetUsername' => $this -> targetUsername,
            'targetLive' => $this -> targetLive,
            'target' => $this -> targetPayload(),
        ];
    }


    /** The reported media of a deleted post, streamed from the kept originals. */
    private function forensicAttachmentsElement(): HTMLObject
    {
        $wrap = new Div();
        $wrap -> class = 'ReportedAttachments d-flex flex-column gap-2';

        foreach ($this -> forensicAttachmentIds as $item_id) {
            $wrap -> addContent(self::forensicAttachmentElement($item_id));
        }

        return $wrap;
    }

    private static function forensicAttachmentElement(int $item_id): HTMLObject
    {
        $url = ServerURL::absolute('/api/report-attachment?itemId=' . $item_id);
        $original = UploadProcessor::originalForItem($item_id);
        $media_type = $original['mediaType'] ?? null;

        if ($media_type === 'image') {
            $image = new Image();
            $image -> class = 'ReportedMedia';
            $image -> src = $url;
            $image -> alt = 'Reported image';

            return $image;
        }

        if ($media_type === 'video') {
            $video = new Video();
            $video -> class = 'ReportedMedia';
            $video -> attributes['controls'] = 'controls';
            $video -> src = $url;

            return $video;
        }

        if ($media_type === 'audio') {
            $audio = new Audio();
            $audio -> attributes['controls'] = 'controls';
            $audio -> src = $url;

            return $audio;
        }

        // No original on disk (deleted before originals were kept), or an
        // unrecognised type - a plain note, and a link if the file is there.
        if ($media_type === null) {
            return new Notice('A reported attachment is no longer available.');
        }

        $link = new Anchor($url, 'View reported attachment');
        $link -> attributes['target'] = '_blank';
        $link -> attributes['rel'] = 'noopener';

        return $link;
    }

    /** The reported item rendered so a moderator can assess it (see resolveTarget). */
    private function targetContentElement(): HTMLObject
    {
        if ($this -> targetKind === 'message') {
            $quote = new Blockquote((string) $this -> targetData);
            $quote -> class = 'ReportedContent';

            return $quote;
        }

        // A reported post is embedded as its bare content (no card, no action
        // bar) - a moderator reviews it, they don't like/reply/bookmark from
        // the report queue.
        if ($this -> targetData instanceof Post) {
            return $this -> targetData -> contentElement();
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
            // A bare post on the client (no action bar) - the 0/0/false/false
            // counts its payload carries go unused there.
            $payload = ['kind' => 'post', 'post' => $this -> targetData -> toPayload(0, 0, false, false)];

            if ($this -> forensicAttachmentIds !== []) {
                // Media type is resolved here (one lookup) so the client just
                // builds the element and points it at the passthrough.
                $payload['attachments'] = array_map(fn ($item_id) => [
                    'itemId' => $item_id,
                    'mediaType' => UploadProcessor::originalForItem($item_id)['mediaType'] ?? null,
                    'url' => ServerURL::absolute('/api/report-attachment?itemId=' . $item_id),
                ], $this -> forensicAttachmentIds);
            }

            return $payload;
        }

        if ($this -> targetKind === 'user' && $this -> targetData instanceof User) {
            // Explicit allowlist - a User object also carries email and
            // passwordHash, which must never reach a moderator's console.
            return ['kind' => 'user', 'user' => [
                'userId' => (int) $this -> targetData -> userId,
                'slug' => $this -> targetData -> slug,
                'title' => $this -> targetData -> title,
                'image' => $this -> targetData -> avatarURL(),
                'createdAt' => $this -> targetData -> createdAt,
            ]];
        }

        return ['kind' => 'missing', 'message' => (string) $this -> targetData];
    }

    /**
     * Builds the reported item from its report-time snapshot (Report::buildSnapshot)
     * so a moderator sees what was reported, not what it's since become: a
     * message's body, a post as the post itself (byline + text + media, no action
     * bar - the post's own text/Delta comes from the snapshot, its author and any
     * surviving media are resolved live), a user as their profile card. A report
     * with no snapshot (created before snapshots, target already gone) resolves to
     * a 'missing' notice.
     *
     * @param array<string, mixed>|null $snapshot
     * @return array{userId: ?int, kind: string, data: User|Post|string}
     */
    private static function resolveFromSnapshot(string $target_type, ?array $snapshot, bool $live): array
    {
        if ($snapshot === null) {
            return ['userId' => null, 'kind' => 'missing', 'data' => 'The reported content is no longer available.'];
        }

        if ($target_type === 'message') {
            $sender_id = isset($snapshot['senderId']) ? (int) $snapshot['senderId'] : null;

            return ['userId' => $sender_id, 'kind' => 'message', 'data' => (string) ($snapshot['body'] ?? '')];
        }

        if ($target_type === 'post') {
            $user_id = isset($snapshot['userId']) ? (int) $snapshot['userId'] : null;

            // attachmentIds is snapshot metadata, not a Post property.
            unset($snapshot['attachmentIds']);

            if ($live) {
                // The post still exists: show its current media (live items).
                return ['userId' => $user_id, 'kind' => 'post', 'data' => Post::fromRowWithItems(Post::fromRow($snapshot))];
            }

            // Deleted: text/byline from the snapshot, media rendered forensically
            // from the kept originals (see forensicAttachmentsElement).
            $post = Post::fromRow($snapshot);
            $post -> author = $user_id !== null ? User::load($user_id) : null;

            return ['userId' => $user_id, 'kind' => 'post', 'data' => $post];
        }

        if ($target_type === 'user') {
            $user_id = isset($snapshot['userId']) ? (int) $snapshot['userId'] : null;

            return ['userId' => $user_id, 'kind' => 'user', 'data' => User::fromRow($snapshot)];
        }

        return ['userId' => null, 'kind' => 'missing', 'data' => 'Unknown content type.'];
    }
}
