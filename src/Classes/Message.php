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

        if ($this -> sender !== null) {
            $this -> contents[] = $this -> senderHeader();
        }

        $body = new Paragraph();
        $body -> contents[] = $this -> body;
        $this -> contents[] = $body;

        $meta = new Time();
        $meta -> class = 'Muted text-sm RelativeTime';
        $meta -> datetime = date(DATE_ATOM, strtotime($this -> createdAt));
        $meta -> contents[] = date('F j, Y g:i A', strtotime($this -> createdAt));
        $this -> contents[] = $meta;

        if (Auth::check() && Auth::id() !== $this -> senderId) {
            $report_button = new Button();
            $report_button -> class = 'Btn ReportButton';
            $report_button -> attributes['data-target-type'] = 'message';
            $report_button -> attributes['data-target-id'] = (string) $this -> messageId;
            $report_button -> contents[] = 'Report';
            $this -> contents[] = $report_button;
        }

        return parent::toDOM();
    }

    protected function senderHeader(): HTMLObject
    {
        $name = $this -> sender -> displayName ?? $this -> sender -> username;

        $header = new Div();
        $header -> class = 'd-flex align-items-center gap-3';

        $header -> addContents(Avatar::forUser($this -> sender));

        $info = new Div();

        $name_line = new Div();
        $name_line -> class = 'fw-semibold';
        $name_line -> contents[] = $name;
        $info -> addContents($name_line);

        $username_line = new Div();
        $username_line -> class = 'Muted text-sm';
        $username_line -> contents[] = '@' . $this -> sender -> username;
        $info -> addContents($username_line);

        $header -> addContents($info);

        return $header;
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
