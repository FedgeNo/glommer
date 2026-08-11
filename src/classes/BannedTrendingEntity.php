<?php

declare(strict_types=1);

/**
 * One row on the moderation "Banned Trending Entities" list: the banned
 * entity (value + type), who banned it and when, the reason if given, and an
 * Unban button. Mirrored by the .TrendingEntityUnbanButton handler in main.js
 * that lifts the ban via api/unban-trending-entity. Fetched directly off
 * BannedTrendingEntityList's DB::rows(); bannedByUsername comes from the
 * join to Users so the moderator's name is shown, not their id.
 */
class BannedTrendingEntity extends Div
{
    public ?string $class = 'BannedTrendingEntity';
    public array $mixins = ['d-flex', 'align-items-center', 'gap-3'];

    public ?string $type = null;
    public ?string $title = null;
    public ?string $reason = null;
    public ?string $bannedByUsername = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-entity-type'] = (string) $this -> type;
        $this -> attributes['data-entity-value'] = (string) $this -> title;

        $info = new Div();
        $info -> mixins = ['d-flex', 'flex-column', 'gap-1'];
        $info -> addContent(new Paragraph($this -> title . ' (' . $this -> type . ')'));

        $words = Strings::for(self::class);
        $sentence = $words['bannedBy'] ?? [];

        $detail = new Paragraph();
        $detail -> mixins = ['muted'];
        // The time is an element of its own between two text nodes, so a
        // language can put it anywhere in the sentence - the same shape an
        // inline link takes.
        $detail -> addContent(str_replace(
            '{name}',
            (string) $this -> bannedByUsername,
            (string) ($sentence['before'] ?? '')
        ));
        $detail -> addContent(new RelativeTime($this -> createdAt));
        $detail -> addContent((string) ($sentence['after'] ?? ''));

        if ($this -> reason !== null && $this -> reason !== '') {
            $detail -> addContent(str_replace('{reason}', $this -> reason, (string) ($words['reason'] ?? '')));
        }

        $info -> addContent($detail);
        $this -> addContent($info);

        $unban = new TrendingEntityUnbanButton((string) $this -> type, (string) $this -> title);
        $unban -> mixins[] = 'ms-auto';
        $this -> addContent($unban);

        return parent::toDOM();
    }
}
