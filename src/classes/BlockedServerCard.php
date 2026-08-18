<?php

declare(strict_types=1);

/**
 * One row on the moderation "Blocked Servers" list: the domain, who blocked it
 * and when, the reason if one was given, and the control to lift it.
 *
 * Hydrated straight off BlockedServerList's query; blockedByUsername comes from
 * the join to Users so the moderator's name shows rather than their id, and is
 * null when the account that blocked it has since been deleted.
 */
class BlockedServerCard extends Div
{
    public ?string $class = 'BlockedServerCard';

    public ?string $domain = null;
    public ?string $reason = null;
    public ?string $blockedByUsername = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-domain'] = (string) $this -> domain;

        $info = new BlockedServerCardInfo();
        $info -> addContent(new Paragraph((string) $this -> domain));

        $words = Strings::for(self::class);
        $sentence = $words['blockedBy'] ?? [];
        $who = $this -> blockedByUsername ?? (string) ($words['deletedAccount'] ?? '');

        $detail = new BlockedServerCardDetail();
        // See BannedTrendingEntity: the time is its own element between two
        // text nodes rather than glued to the end of one.
        $detail -> addContent(str_replace('{name}', $who, (string) ($sentence['before'] ?? '')));
        $detail -> addContent(new RelativeTime((string) $this -> createdAt));
        $detail -> addContent((string) ($sentence['after'] ?? ''));

        if ($this -> reason !== null && $this -> reason !== '') {
            $detail -> addContent(str_replace('{reason}', $this -> reason, (string) ($words['reason'] ?? '')));
        }

        $info -> addContent($detail);
        $this -> addContent($info);

        $unblock = new ServerUnblockButton((string) $this -> domain);
        $this -> addContent($unblock);

        return parent::toDOM();
    }
}
