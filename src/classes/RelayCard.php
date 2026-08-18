<?php

declare(strict_types=1);

/**
 * One row on the Relays page: the relay's address, whether it has answered
 * yet, and the control to withdraw.
 *
 * Hydrated straight off RelayList's query, so its properties are the Relays
 * columns by name.
 */
class RelayCard extends Div
{
    public ?string $class = 'RelayCard';

    public ?string $actorURI = null;
    public ?string $status = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $this -> attributes['data-actor-uri'] = (string) $this -> actorURI;

        $info = new RelayCardInfo();
        $info -> addContent(new Paragraph((string) $this -> actorURI));

        $detail = new RelayCardDetail();

        // A relay that has not answered is not yet delivering anything, and
        // saying so is the difference between "waiting" and "broken" - see
        // TrendingTimerStatus/UploadWorkerStatus for the same match()-to-key,
        // key-to-phrase shape.
        $status_key = match ($this -> status) {
            'accepted' => 'accepted',
            default => 'waiting',
        };

        $detail -> addContent((string) ($words[$status_key] ?? ''));
        $detail -> addContent(new RelativeTime((string) $this -> createdAt));

        $info -> addContent($detail);
        $this -> addContent($info);

        $unsubscribe = new RelayUnsubscribeButton((string) $this -> actorURI);
        $this -> addContent($unsubscribe);

        return parent::toDOM();
    }
}
