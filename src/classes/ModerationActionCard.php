<?php

declare(strict_types=1);

/**
 * One entry in the moderation log: who did what, to whom, and when.
 *
 * The record has always been written - every ban, promotion, dismissal and
 * deletion - and until now nothing read it. It is here so the admin can answer
 * "why was I banned?" and "which moderator deleted that?" from the site rather
 * than from a database client.
 *
 * Read-only by design. An audit log with an edit button is not one, and
 * nothing here offers a way to change what it says.
 */
class ModerationActionCard extends Article
{
    public ?string $class = 'ModerationActionCard';
    public array $mixins = ['d-flex', 'flex-column', 'gap-1'];

    public ?int $actionId = null;
    public ?string $action = null;
    public ?string $moderatorUsername = null;
    public ?string $targetUsername = null;
    public ?int $targetUserId = null;
    public ?string $type = null;
    public ?int $targetId = null;
    public ?int $reportId = null;
    public ?string $createdAt = null;
    public ?int $moderatorId = null;

    /**
     * What each recorded action is called in words. Every value
     * ModerationAction::log() is ever given has an entry, so an unfamiliar one
     * showing up raw is a sign the two have drifted apart.
     */
    private const DESCRIPTIONS = [
        'ban' => 'banned',
        'unban' => 'unbanned',
        'setMod' => 'made a moderator',
        'unsetMod' => 'removed as a moderator',
        'dismissReport' => 'dismissed a report about',
        'deleteReportedContent' => 'deleted reported',
        'classifyReportedContent' => 'marked as sensitive',
        'banTrendingEntity' => 'banned the trending topic',
        'unbanTrendingEntity' => 'unbanned the trending topic',
    ];

    /**
     * Whether there are words for an action, rather than its raw camelCase
     * name. A new one recorded by ModerationAction::log and not described here
     * would be printed as "classifyReportedContent" at the admin.
     */
    public static function describes(string $action): bool
    {
        return isset(self::DESCRIPTIONS[$action]);
    }

    public static function fromRow(ModerationActionData $row): self
    {
        $card = new self();

        foreach (get_object_vars($row) as $property => $value) {
            $card -> $property = $value;
        }

        return $card;
    }

    public function toDOM(): \DOMElement
    {
        $line = new Paragraph();
        $line -> class = 'ModerationActionSummary';

        $line -> addContent($this -> moderatorLink());
        $line -> addContent(' ' . (self::DESCRIPTIONS[(string) $this -> action] ?? (string) $this -> action) . ' ');
        $line -> addContent($this -> subject());

        $this -> addContent($line);

        $when = new Paragraph();
        $when -> class = 'ModerationActionWhen';
        $when -> mixins = ['muted', 'text-sm'];
        $when -> addContent(new RelativeTime((string) $this -> createdAt));

        $this -> addContent($when);

        return parent::toDOM();
    }

    /** Who acted. A moderator whose account has since gone is still named. */
    private function moderatorLink(): HTMLObject
    {
        if ($this -> moderatorUsername === null) {
            return self::words('A moderator since deleted');
        }

        return new Anchor(
            ServerURL::absolute('/users/' . $this -> moderatorUsername . '/'),
            $this -> moderatorUsername
        );
    }

    private static function words(string $text): Span
    {
        $span = new Span();
        $span -> contents[] = $text;

        return $span;
    }

    /**
     * What was acted on - a member, or a piece of content named by its kind
     * and id. A row carries one or the other, never both.
     */
    private function subject(): HTMLObject
    {
        if ($this -> targetUsername !== null) {
            return new Anchor(
                ServerURL::absolute('/users/' . $this -> targetUsername . '/'),
                $this -> targetUsername
            );
        }

        if ($this -> targetUserId !== null) {
            return self::words('an account since deleted');
        }

        if ($this -> type !== null && $this -> targetId !== null) {
            return self::words($this -> type . ' #' . $this -> targetId);
        }

        return self::words($this -> type ?? 'something');
    }
}
