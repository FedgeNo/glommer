<?php

declare(strict_types=1);

class Message extends Article implements \JsonSerializable
{
    // How many messages one side of a conversation can send in a row before
    // the other person has replied. Resets to 0 the moment the recipient
    // sends anything back - a real back-and-forth is never throttled, only a
    // one-sided flood.
    public const MAX_UNANSWERED = 20;

    public ?string $class = 'Message';

    public ?int $messageId = null;
    public ?int $senderId = null;
    public ?int $recipientId = null;
    public ?string $body = null;
    // The end-to-end encrypted alternative to body (see MessageEnvelope) and
    // the relay-time commitment to it (see MessageFranking). A message has a
    // body or a ciphertext, never both.
    public ?string $bodyCiphertext = null;
    public ?string $frankingTag = null;
    public ?string $remoteObjectURI = null;
    public ?string $createdAt = null;
    // Set once a moderator dismisses a report on this message - blocks it from
    // being reported again (see api/report.php).
    public ?int $reportsDismissed = null;
    public ?User $sender = null;

    /**
     * What a Message is when it's encoded as JSON - the fields HTMLObjects.js's Message
     * reads, and nothing about who reported it or how it renders.
     */
    public function jsonSerialize(): array
    {
        return [
            'messageId' => (int) $this -> messageId,
            'senderId' => (int) $this -> senderId,
            'recipientId' => (int) $this -> recipientId,
            'body' => $this -> body,
            'bodyCiphertext' => $this -> bodyCiphertext,
            'createdAt' => $this -> createdAt,
            // Each message carries its sender the way any list item carries
            // its user, so the client needs no side channel to build a byline.
            'sender' => $this -> sender !== null ? [
                'slug' => $this -> sender -> slug,
                'title' => $this -> sender -> title,
                'image' => $this -> sender -> avatarURL(),
            ] : null,
        ];
    }

    public function toDOM(): \DOMElement
    {
        if (Auth::check() && Auth::id() === $this -> senderId) {
            $this -> class .= ' Own';
        }

        // Byline row: header + time (mirrors PostByline)
        $byline = new MessageByline();

        if ($this -> sender !== null) {
            $byline -> addContent($this -> senderHeader());
        }

        $meta = new RelativeTime($this -> createdAt);
        $byline -> addContent($meta);

        $this -> contents[] = $byline;

        // Body and (for other people's messages) the report button sit on one
        // row - text on the left, button hugging the right - so the button
        // never overlaps the text.
        $line = new MessageLine();

        $body = new MessageBody();

        if ($this -> bodyCiphertext !== null) {
            // The server can't read this one - it ships the envelope on the
            // element for Controllers.js's MessageUnlockForm to open in the browser, and
            // renders as a locked placeholder until it does.
            $this -> class .= ' Encrypted Locked';
            $this -> attributes['data-cipher-envelope'] = $this -> bodyCiphertext;
            $this -> attributes['data-message-id'] = (string) $this -> messageId;
            $body -> contents[] = (string) (Strings::for(self::class)['encrypted'] ?? '');
        } else {
            // Same last-step expansion posts get, on the path messages take -
            // a message body is plain text and never goes near DeltaRenderer.
            $body -> contents[] = EmojiShortcode::expand((string) $this -> body);
        }

        $line -> addContent($body);

        // Only on what arrived, never on what this member wrote: reading a
        // message in another language is the whole point, and their own words
        // need no decoding. Nothing happens until it is pressed.
        if (Auth::check() && Auth::id() === $this -> recipientId && Translator::canTranslate()) {
            $line -> addContent(new MessageTranslateButton((int) $this -> messageId));
        }

        // No report button on the admin's messages - api/report.php rejects
        // reports about the admin, since nobody could act on one anyway.
        if (Auth::check() && Auth::id() !== $this -> senderId && $this -> senderId !== 1) {
            $line -> addContent(new ReportButton('message', $this -> messageId));
        }

        $this -> contents[] = $line;

        return parent::toDOM();
    }

    protected function senderHeader(): HTMLObject
    {
        return $this -> sender -> header();
    }

    /**
     * How many messages $sender_id has sent to $recipient_id since
     * $recipient_id last replied (or since the start of the conversation, if
     * they never have) - messageId is monotonic with send order, so
     * comparing against the recipient's own latest messageId in the other
     * direction is exact and needs no separate "last reply" bookkeeping.
     * Both halves are covered by the existing (senderId, recipientId,
     * messageId) / (recipientId, senderId, messageId) indexes.
     */
    public static function unansweredCount(int $sender_id, int $recipient_id): int
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
        AND `messageId` > (
            SELECT COALESCE(MAX(`messageId`), 0)
                FROM `Messages`
                WHERE `senderId` = ? AND `recipientId` = ?
        )
', 'iiii', $sender_id, $recipient_id, $recipient_id, $sender_id));

        return (int) mysqli_fetch_assoc($result)['count'];
    }

    /**
     * The newest message this person has been sent, or 0 if none - what the
     * nav's unread dot compares against their lastMessageId.
     *
     * Served by the (recipientId, messageId) index, where equality on the
     * whole prefix makes MAX a single dive to the last entry. That index
     * exists for exactly this: under (recipientId, senderId, messageId) the
     * unbound senderId turns the same MAX into a scan of every message the
     * person was ever sent - measured at thousands of entries per page render
     * on an ordinary mailbox, and MariaDB declines the per-sender loose scan
     * that would have avoided it.
     */
    public static function newestReceivedId(int $user_id): int
    {
        $result = mysqli_stmt_get_result(DB::run('
SELECT COALESCE(MAX(`messageId`), 0) AS `newestId`
    FROM `Messages`
    WHERE `recipientId` = ?
', 'i', $user_id));

        return (int) mysqli_fetch_assoc($result)['newestId'];
    }

    /**
     * Marks everything received so far as seen. Opening the conversations list
     * is enough - seeing that a thread has something new in it is the whole
     * job of the dot, and having to open every thread to clear it would make
     * it nag about messages already known about. Message-only notifications
     * are acknowledged at the same time.
     */
    public static function markSeen(int $user_id): void
    {
        DB::transaction(static function () use ($user_id): void {
            $none = 0;

            // Same single-dive MAX as newestReceivedId(), off the same index.
            DB::run('
UPDATE `Users`
    SET `lastMessageId` = (
        SELECT COALESCE(MAX(`messageId`), ?)
            FROM `Messages`
            WHERE `recipientId` = ?
    )
    WHERE `userId` = ?
', 'iii', $none, $user_id, $user_id);

            Notification::markMessagesSeen($user_id);
        });

        // The page has already loaded Auth::user(); its navigation must read
        // the updated counters, including MessageDot's per-User cached answer.
        Auth::clearUserCache();
    }

    /** Store the message and both inbox entries together, for local and remote sends. */
    public static function create(int $sender_id, int $recipient_id, ?string $body, ?string $ciphertext = null, ?string $franking_tag = null, ?string $remote_uri = null): int
    {
        return DB::transaction(static function () use ($sender_id, $recipient_id, $body, $ciphertext, $franking_tag, $remote_uri): int {
            self::lockParticipants($sender_id, $recipient_id);

            DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`, `bodyCiphertext`, `frankingTag`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'iissss', $sender_id, $recipient_id, $body, $ciphertext, $franking_tag, $remote_uri);
            $message_id = (int) mysqli_insert_id(DB::connection());
            self::recordConversation($sender_id, $recipient_id, $message_id);

            return $message_id;
        });
    }

    /** Lock in a fixed order before inserting/deleting, including a new pair. */
    private static function lockParticipants(int $sender_id, int $recipient_id): void
    {
        DB::rows('
SELECT `userId`
    FROM `Users`
    WHERE `userId` IN (?, ?)
    ORDER BY `userId`
    FOR UPDATE
', 'User', 'ii', $sender_id, $recipient_id);
    }

    /** Called only while the participants are locked in the message transaction. */
    private static function recordConversation(int $sender_id, int $recipient_id, int $message_id): void
    {
        DB::run('
INSERT INTO `Conversations` (`userId`, `partnerId`, `lastMessageId`)
    VALUES (?, ?, ?), (?, ?, ?)
    ON DUPLICATE KEY UPDATE `lastMessageId` = VALUES(`lastMessageId`)
', 'iiiiii', $sender_id, $recipient_id, $message_id, $recipient_id, $sender_id, $message_id);
    }

    /**
     * Deletes one message and restores the preceding inbox entry if needed.
     * Caller is responsible for authorization (moderator content removal).
     */
    public static function delete(int $message_id): void
    {
        $message = DB::row('SELECT `senderId`, `recipientId` FROM `Messages` WHERE `messageId` = ?', self::class, 'i', $message_id);

        if ($message === null) {
            return;
        }

        DB::transaction(static function () use ($message_id, $message): void {
            $sender_id = (int) $message -> senderId;
            $recipient_id = (int) $message -> recipientId;
            self::lockParticipants($sender_id, $recipient_id);

            DB::run('
DELETE
    FROM `Messages`
    WHERE `messageId` = ?
', 'i', $message_id);

            // Deleting a last message cascades its two summary rows. Restore
            // them from the two covering indexes while new sends are locked.
            $latest = DB::row('
SELECT GREATEST(
    COALESCE((SELECT MAX(`messageId`) FROM `Messages` WHERE `senderId` = ? AND `recipientId` = ?), 0),
    COALESCE((SELECT MAX(`messageId`) FROM `Messages` WHERE `senderId` = ? AND `recipientId` = ?), 0)
) AS `messageId`
', self::class, 'iiii', $sender_id, $recipient_id, $recipient_id, $sender_id);

            if ($latest -> messageId > 0) {
                self::recordConversation($sender_id, $recipient_id, $latest -> messageId);
            }
        });
    }

}
