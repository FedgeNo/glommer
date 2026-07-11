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

        $body = new Paragraph();
        $body -> contents[] = $this -> body;
        $this -> contents[] = $body;

        if (Auth::check() && Auth::id() !== $this -> senderId) {
            $this -> contents[] = new ReportButton('message', $this -> messageId);
        }

        return parent::toDOM();
    }

    protected function senderHeader(): HTMLObject
    {
        return $this -> sender -> header();
    }

    public static function fromRow(array $row): self
    {
        return self::fromRowWithSender($row, User::load((int) $row['senderId']));
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
        $mysqli = Database::connection();
        $fetch_limit = $limit + 1;

        if ($before_message_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Messages`
    WHERE ((`senderId` = ? AND `recipientId` = ?) OR (`senderId` = ? AND `recipientId` = ?))
        AND `messageId` < ?
    ORDER BY `messageId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iiiiii', $user_a, $user_b, $user_b, $user_a, $before_message_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Messages`
    WHERE (`senderId` = ? AND `recipientId` = ?) OR (`senderId` = ? AND `recipientId` = ?)
    ORDER BY `messageId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iiiii', $user_a, $user_b, $user_b, $user_a, $fetch_limit);
        }

        mysqli_stmt_execute($stmt);
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
