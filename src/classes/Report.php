<?php

declare(strict_types=1);

class Report extends Article
{
    public ?string $class = 'Report';

    public ?int $reportId = null;
    public ?int $reporterId = null;
    public ?string $reporterUsername = null;
    public ?string $type = null;
    public ?int $targetId = null;
    public ?string $reason = null;
    public ?string $snapshot = null;
    public ?string $createdAt = null;
    public ?int $targetUserId = null;
    public ?string $targetUsername = null;

    // The reported item, resolved once (resolveFromSnapshot) into a kind plus
    // its raw data, so the server render (toDOM) and the AJAX payload
    // (toPayload) build from one source and can't diverge. kind is
    // 'message'|'post'|'user'|'missing'; data is the message body, a Post, a
    // User, or a locale key naming the missing-content notice.
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

    private bool $prepared = false;

    public function toDOM(): \DOMElement
    {
        $this -> prepare();

        $words = Strings::for(self::class);
        $type_label = (string) ($words['targetTypes'][$this -> type] ?? ucfirst((string) $this -> type));

        // Left: who reported what, the content in question, the reason, and when.
        $details = new ReportDetails();

        $summary_words = is_array($words['summary'] ?? null) ? $words['summary'] : [];

        $summary = new Div();
        $summary -> contents[] = str_replace(
            ['{type}', '{id}'],
            [$type_label, (string) $this -> targetId],
            (string) ($summary_words['before'] ?? '')
        );
        $summary -> addContent(new Anchor(ServerURL::absolute('/users/' . $this -> reporterUsername . '/'), $this -> reporterUsername));
        $summary -> contents[] = (string) ($summary_words['after'] ?? '');
        $details -> addContent($summary);

        $details -> addContent($this -> targetContentElement());

        if ($this -> forensicAttachmentIds !== []) {
            $details -> addContent($this -> forensicAttachmentsElement());
        }

        if ($this -> reason !== null) {
            $reason_line = new Paragraph();
            $reason_line -> contents[] = str_replace('{reason}', $this -> reason, (string) ($words['reasonLine'] ?? ''));
            $details -> addContent($reason_line);
        }

        $meta = new RelativeTime((string) $this -> createdAt);
        $details -> addContent($meta);

        $this -> contents[] = $details;

        // Right: the moderation actions, stacked. The admin (userId 1) can't
        // be banned, so never offer a Ban Reporter button when the admin is
        // the one who filed the report. (The reported user is never the admin -
        // api/report.php rejects reports about admin content - so that side
        // needs no such guard.)
        $actions = new ReportActions();

        if ($this -> reporterId !== 1) {
            $actions -> addContent(new UserBanButton($this -> reporterId, (string) ($words['banReporterLabel'] ?? '')));
        }

        if ($this -> targetUserId !== null && $this -> targetUsername !== null && $this -> targetUserId !== $this -> reporterId) {
            $actions -> addContent(new UserBanButton($this -> targetUserId, (string) ($words['banReportedUserLabel'] ?? '')));
        }

        // Only offer Delete when the live post/message still exists (a snapshot
        // of already-deleted content still shows, but has nothing to delete).
        if ($this -> targetLive && ($this -> type === 'post' || $this -> type === 'message')) {
            $delete_label = str_replace('{type}', $type_label, (string) ($words['deleteLabel'] ?? ''));
            $actions -> addContent(new ReportedContentDeleteButton((int) $this -> reportId, $delete_label));
        }

        // Only a post has media to put behind a cover.
        if ($this -> targetLive && $this -> type === 'post') {
            $actions -> addContent(new ReportedContentClassifyButton((int) $this -> reportId));
        }

        $actions -> addContent(new ReportDismissButton((int) $this -> reportId));

        $this -> contents[] = $actions;

        return parent::toDOM();
    }

    private function prepare(): void
    {
        if ($this -> prepared) {
            return;
        }

        $this -> prepared = true;

        // A hand-built presentation fixture may already carry its resolved
        // target. Database-hydrated reports carry the raw snapshot instead.
        if ($this -> targetKind !== null) {
            return;
        }

        $snapshot = $this -> snapshot !== null ? json_decode($this -> snapshot, true) : null;
        $snapshot = is_array($snapshot) ? $snapshot : null;

        // A live existence check, not the snapshot: only live post/message content
        // is deletable, and a deleted post renders its reported media forensically.
        $this -> targetLive = ReportManager::contentExists((string) $this -> type, (int) $this -> targetId);

        ['userId' => $this -> targetUserId, 'kind' => $this -> targetKind, 'data' => $this -> targetData] = self::resolveFromSnapshot((string) $this -> type, $snapshot, $this -> targetLive);

        if ($this -> targetKind === 'post' && !$this -> targetLive && $snapshot !== null) {
            $this -> forensicAttachmentIds = array_map('intval', $snapshot['attachmentIds'] ?? []);
        }

        // The target user must still exist to be bannable.
        if ($this -> targetUserId !== null) {
            $this -> targetUsername = User::load($this -> targetUserId) ?-> slug;
        }
    }

    /**
     * The JSON payload for one report, used by api/report-history.php to feed
     * the client-side Report on scroll. Mirrors the fields toDOM() renders;
     * the reported item rides under `target` as a small kind-tagged union the
     * client rebuilds (a bare Post payload, a message body, an allowlisted user
     * card, or a missing-notice message) - never rendered HTML.
     */
    public function toPayload(): array
    {
        $this -> prepare();

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
        $wrap = new ReportedAttachments();

        foreach ($this -> forensicAttachmentIds as $item_id) {
            $wrap -> addContent(self::forensicAttachmentElement($item_id));
        }

        return $wrap;
    }

    private static function forensicAttachmentElement(int $item_id): HTMLObject
    {
        $words = Strings::for(self::class);
        $url = ServerURL::absolute('/api/report-attachment?itemId=' . $item_id);
        $original = UploadProcessor::originalForItem($item_id);
        $media_type = $original['mediaType'] ?? null;

        if ($media_type === 'image') {
            $image = new ReportedMedia();
            $image -> src = $url;
            $image -> alt = (string) ($words['reportedImageAlt'] ?? '');

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
            return new Notice((string) ($words['attachmentUnavailable'] ?? ''));
        }

        $link = new Anchor($url, (string) ($words['viewAttachment'] ?? ''));
        $link -> attributes['target'] = '_blank';
        $link -> attributes['rel'] = 'noopener';

        return $link;
    }

    /** The reported item rendered so a moderator can assess it (see resolveFromSnapshot). */
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

        // Only reachable when targetKind is 'missing': resolveFromSnapshot
        // leaves one of its two keys in targetData rather than English, so
        // this follows the reader's language like everything else here.
        $words = Strings::for(self::class);

        return new Notice((string) ($words['missing'][$this -> targetData] ?? ''));
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

        // Only reachable when targetKind is 'missing' - see targetContentElement.
        // Resolved here rather than sent as a bare key: toPayload() is the AJAX
        // counterpart of toDOM(), so this is this response's one render step,
        // in whatever locale is serving the request.
        $words = Strings::for(self::class);

        return ['kind' => 'missing', 'message' => (string) ($words['missing'][$this -> targetData] ?? '')];
    }

    /**
     * Builds the reported item from its report-time snapshot (ReportManager::buildSnapshot)
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
            return ['userId' => null, 'kind' => 'missing', 'data' => 'noSnapshot'];
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

        return ['userId' => null, 'kind' => 'missing', 'data' => 'unknownType'];
    }
}
