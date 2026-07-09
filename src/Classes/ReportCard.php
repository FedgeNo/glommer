<?php

declare(strict_types=1);

class ReportCard extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'Card d-flex flex-column gap-2 ReportCard';

    public ?int $reportId = null;
    public ?int $reporterId = null;
    public ?string $reporterUsername = null;
    public ?string $targetType = null;
    public ?int $targetId = null;
    public ?string $reason = null;
    public ?string $createdAt = null;
    public ?int $targetUserId = null;
    public ?string $targetUsername = null;

    public function toDOM(): \DOMElement
    {
        $summary = new Div();
        $summary -> contents[] = ucfirst((string) $this -> targetType) . ' #' . $this -> targetId . ' reported by ';
        $summary -> addContents(new Anchor(URL::absolute('/users/' . $this -> reporterUsername . '/'), $this -> reporterUsername));
        $this -> contents[] = $summary;

        if ($this -> reason !== null) {
            $reason_line = new Paragraph();
            $reason_line -> contents[] = 'Reason: ' . $this -> reason;
            $this -> contents[] = $reason_line;
        }

        $meta = new Time();
        $meta -> class = 'Muted text-sm RelativeTime';
        $meta -> datetime = date(DATE_ATOM, strtotime((string) $this -> createdAt));
        $meta -> contents[] = date('F j, Y g:i A', strtotime((string) $this -> createdAt));
        $this -> contents[] = $meta;

        $actions = new Div();
        $actions -> class = 'd-flex gap-2';

        $ban_reporter_button = new Button();
        $ban_reporter_button -> class = 'Btn BanButton';
        $ban_reporter_button -> attributes['data-user-id'] = (string) $this -> reporterId;
        $ban_reporter_button -> contents[] = 'Ban Reporter (' . $this -> reporterUsername . ')';
        $actions -> addContents($ban_reporter_button);

        if ($this -> targetUserId !== null && $this -> targetUsername !== null && $this -> targetUserId !== $this -> reporterId) {
            $ban_target_button = new Button();
            $ban_target_button -> class = 'Btn BanButton';
            $ban_target_button -> attributes['data-user-id'] = (string) $this -> targetUserId;
            $ban_target_button -> contents[] = 'Ban Reported User (' . $this -> targetUsername . ')';
            $actions -> addContents($ban_target_button);
        }

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

        return $card;
    }
}
