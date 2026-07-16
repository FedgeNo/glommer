<?php

declare(strict_types=1);

/**
 * One row on the moderation "Banned Trending Entities" list: the banned
 * entity (value + type), who banned it and when, the reason if given, and an
 * Unban button. Mirrored by the .UnbanTrendingEntityButton handler in main.js
 * that lifts the ban via api/unban-trending-entity. Fetched directly off
 * BannedTrendingEntitiesList's DB::rows(); bannedByUsername comes from the
 * join to Users so the moderator's name is shown, not their id.
 */
class BannedTrendingEntity extends Div
{
    public ?string $class = 'Card d-flex align-items-center gap-3 BannedTrendingEntity';

    public ?string $entityType = null;
    public ?string $entityValue = null;
    public ?string $reason = null;
    public ?string $bannedByUsername = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-entity-type'] = (string) $this -> entityType;
        $this -> attributes['data-entity-value'] = (string) $this -> entityValue;

        $info = new Div();
        $info -> class = 'd-flex flex-column gap-1';
        $info -> addContent(new Paragraph($this -> entityValue . ' (' . $this -> entityType . ')'));

        $detail = new Paragraph();
        $detail -> class = 'Muted';
        $detail -> addContent('Banned by ' . $this -> bannedByUsername . ' ');
        $detail -> addContent(new RelativeTime($this -> createdAt));

        if ($this -> reason !== null && $this -> reason !== '') {
            $detail -> addContent(' - ' . $this -> reason);
        }

        $info -> addContent($detail);
        $this -> addContent($info);

        $unban = new UnbanTrendingEntityButton((string) $this -> entityType, (string) $this -> entityValue);
        $unban -> class = 'ms-auto ' . $unban -> class;
        $this -> addContent($unban);

        return parent::toDOM();
    }
}
