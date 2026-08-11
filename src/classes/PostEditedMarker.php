<?php

declare(strict_types=1);

/**
 * The "(edited)" note stacked beneath a post's timestamp; its tooltip is the
 * full edit time.
 */
class PostEditedMarker extends Span
{
    public ?string $class = 'PostEditedMarker';
    public array $mixins = ['muted', 'text-sm'];

    public ?string $editedAt = null;

    public function __construct(?string $edited_at = null)
    {
        parent::__construct();

        $this -> editedAt = $edited_at;
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['title'] = DateFormat::longWithTime((int) strtotime((string) $this -> editedAt));
        $this -> contents[] = (string) (Strings::for(self::class)['label'] ?? '');

        return parent::toDOM();
    }
}
