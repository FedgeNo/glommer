<?php

declare(strict_types=1);

/**
 * One row on the moderation "Blocked Servers" list: the domain, who blocked it
 * and when, the reason if one was given, and the control to lift it.
 *
 * Hydrated straight off BlockedDomainList's query; blockedByUsername comes from
 * the join to Users so the moderator's name shows rather than their id, and is
 * null when the account that blocked it has since been deleted.
 */
class BlockedDomainCard extends Div
{
    public ?string $class = 'BlockedDomainCard';
    public array $mixins = ['d-flex', 'align-items-center', 'gap-3'];

    public ?string $domain = null;
    public ?string $reason = null;
    public ?string $blockedByUsername = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-domain'] = (string) $this -> domain;

        $info = new Div();
        $info -> mixins = ['d-flex', 'flex-column', 'gap-1'];
        $info -> addContent(new Paragraph((string) $this -> domain));

        $detail = new Paragraph();
        $detail -> mixins = ['muted'];
        $detail -> addContent('Blocked by ' . ($this -> blockedByUsername ?? 'a deleted account') . ' ');
        $detail -> addContent(new RelativeTime((string) $this -> createdAt));

        if ($this -> reason !== null && $this -> reason !== '') {
            $detail -> addContent(' - ' . $this -> reason);
        }

        $info -> addContent($detail);
        $this -> addContent($info);

        $unblock = new DomainUnblockButton((string) $this -> domain);
        $unblock -> mixins[] = 'ms-auto';
        $this -> addContent($unblock);

        return parent::toDOM();
    }
}
