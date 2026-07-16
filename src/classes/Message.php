<?php

declare(strict_types=1);

class Message extends HTMLObject
{
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
     * Builds a Message from a row when the sender User is already loaded, so
     * list callers can batch-load senders instead of querying per message.
     */
    public static function fromRowWithSender(array $row, ?User $sender): self
    {
        $message = new self();

        foreach ($row as $key => $value) {
            $message -> $key = $value;
        }

        $message -> sender = $sender;

        return $message;
    }

    /**
     * Fetches messages between two users, newest-first internally, trimmed and
     * reordered to oldest-first for display. Fetches $limit + 1 rows so an
     * extra leftover row (if present) signals more history without a separate count query.
     *
     * @return array{rows: array[], hasMore: bool}
     */
    public static function rowsBetween(int $user_a, int $user_b, int $limit, ?int $before_message_id = null): array
    {
        $fetch_limit = $limit + 1;

        // No cursor means "from the newest" - a sentinel above any real
        // messageId keeps it one query instead of a cursorless duplicate.
        $cursor = $before_message_id ?? PHP_INT_MAX;

        // The two directions run as separate UNION ALL halves rather than one
        // OR: each half walks its (senderId, recipientId, messageId) index
        // backward and stops at the limit, so only the merged 2x-limit rows
        // ever get sorted - an OR forces collecting and filesorting the whole
        // conversation before the LIMIT can apply.
        $stmt = DB::run('
(SELECT *
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ? AND `messageId` < ?
    ORDER BY `messageId` DESC
    LIMIT ?)
UNION ALL
(SELECT *
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ? AND `messageId` < ?
    ORDER BY `messageId` DESC
    LIMIT ?)
    ORDER BY `messageId` DESC
    LIMIT ?
', 'iiiiiiiii', $user_a, $user_b, $cursor, $fetch_limit, $user_b, $user_a, $cursor, $fetch_limit, $fetch_limit);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
        }

        return ['rows' => array_reverse($rows), 'hasMore' => $has_more];
    }
}
