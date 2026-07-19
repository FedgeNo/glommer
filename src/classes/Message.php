<?php

declare(strict_types=1);

class Message extends HTMLObject
{
    // How many messages one side of a conversation can send in a row before
    // the other person has replied. Resets to 0 the moment the recipient
    // sends anything back - a real back-and-forth is never throttled, only a
    // one-sided flood.
    public const MAX_UNANSWERED = 20;

    public string $tagName = 'div';
    public ?string $class = 'Message Card';

    public ?int $messageId = null;
    public ?int $senderId = null;
    public ?int $recipientId = null;
    public ?string $body = null;
    public ?string $createdAt = null;
    // Set once a moderator dismisses a report on this message - blocks it from
    // being reported again (see api/report.php).
    public ?int $reportsDismissed = null;
    public ?User $sender = null;

    public function toDOM(): \DOMElement
    {
        if (Auth::check() && Auth::id() === $this -> senderId) {
            $this -> class .= ' Own';
        }

        $meta = new RelativeTime($this -> createdAt);
        $meta -> class = 'Muted text-sm ' . $meta -> class;
        $this -> contents[] = $meta;

        if ($this -> sender !== null) {
            $this -> contents[] = $this -> senderHeader();
        }

        // Body and (for other people's messages) the report button sit on one
        // row - text on the left, button hugging the right - so the button
        // never overlaps the text.
        $line = new Div();
        $line -> class = 'MessageLine';

        $body = new Paragraph();
        $body -> contents[] = $this -> body;
        $line -> addContent($body);

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
     * Deletes a single message. Messages have no child rows or media, so this
     * is a plain one-row delete. Caller is responsible for authorization
     * (used by a moderator removing reported content).
     */
    public static function delete(int $message_id): void
    {
        DB::run('
DELETE
    FROM `Messages`
    WHERE `messageId` = ?
', 'i', $message_id);
    }

    /**
     * Fetches messages between two users, newest-first internally, trimmed and
     * reordered to oldest-first for display. Fetches $limit + 1 rows past
     * $offset so an extra leftover row (if present) signals more history
     * without a separate count query.
     *
     * $class lets a caller that only needs the data - not a renderable
     * Message - fetch straight into MessageData instead.
     *
     * @return array{rows: self[]|MessageData[], hasMore: bool}
     */
    public static function rowsBetween(int $user_a, int $user_b, int $limit, int $offset = 0, string $class = self::class): array
    {
        $fetch_limit = $limit + 1;

        // The two directions run as separate UNION ALL halves rather than one
        // OR: each half walks its (senderId, recipientId, messageId) index
        // backward and stops at its limit, so only the merged rows ever get
        // sorted - an OR forces collecting and filesorting the whole
        // conversation before the LIMIT can apply. The outer OFFSET skips rows
        // from the merged set, so each half must produce every row up to
        // offset + fetch_limit for the page to be complete.
        $half_limit = $offset + $fetch_limit;

        $rows = DB::rows('
(SELECT *
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
    ORDER BY `messageId` DESC
    LIMIT ?)
UNION ALL
(SELECT *
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
    ORDER BY `messageId` DESC
    LIMIT ?)
    ORDER BY `messageId` DESC
    LIMIT ? OFFSET ?
', $class, 'iiiiiiii', $user_a, $user_b, $half_limit, $user_b, $user_a, $half_limit, $fetch_limit, $offset);

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
        }

        return ['rows' => array_reverse($rows), 'hasMore' => $has_more];
    }
}
