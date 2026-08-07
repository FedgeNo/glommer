<?php

declare(strict_types=1);

/**
 * One draft or scheduled post on the /drafts page: what it says, when (or
 * whether) it goes out, and the two things that can happen to it - publish
 * now, or discard. There is no edit in this first cut; a draft is resumed by
 * publishing or discarding it.
 */
class StagedPostCard extends Card
{
    public ?string $class = 'StagedPostCard';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public ?int $stagedPostId = null;
    public ?int $userId = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $descriptionDelta = null;
    public ?string $linkURL = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public int $sensitive = 0;
    public ?string $publishAt = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-staged-post-id'] = (string) $this -> stagedPostId;

        // What the in-place editor prefills from - the owner's own page, so
        // nothing here is anyone else's to see. The schedule travels as an
        // epoch so the browser can show it in the reader's clock and hand it
        // back without ever guessing the server's zone.
        $this -> attributes['data-title'] = (string) ($this -> title ?? '');
        $this -> attributes['data-description-delta'] = (string) ($this -> descriptionDelta ?? '');
        $this -> attributes['data-link-url'] = (string) ($this -> linkURL ?? '');

        if ($this -> publishAt !== null) {
            $this -> attributes['data-publish-at-epoch'] = (string) strtotime((string) $this -> publishAt);
        }

        if ($this -> latitude !== null && $this -> longitude !== null) {
            $this -> attributes['data-latitude'] = (string) $this -> latitude;
            $this -> attributes['data-longitude'] = (string) $this -> longitude;
        }

        if ((string) $this -> title !== '') {
            $title = new StagedPostTitle();
            $title -> contents[] = (string) $this -> title;
            $this -> contents[] = $title;
        }

        if ((string) $this -> description !== '') {
            $this -> contents[] = new Paragraph(truncate((string) $this -> description, 200));
        }

        if ((string) $this -> linkURL !== '') {
            $link = new Paragraph((string) $this -> linkURL);
            $link -> mixins = ['muted', 'text-sm'];
            $this -> contents[] = $link;
        }

        $this -> contents[] = new StagedPostWhen($this -> publishAt);

        $actions = new Div();
        $actions -> mixins = ['d-flex', 'gap-2'];
        $actions -> addContent(new StagedPostPublishButton());
        $actions -> addContent(new StagedPostEditButton());
        $actions -> addContent(new StagedPostDiscardButton());
        $this -> contents[] = $actions;

        return parent::toDOM();
    }
}
