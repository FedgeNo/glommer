<?php

declare(strict_types=1);

/**
 * A post byline's right-hand meta column: the permalink timestamp, with the
 * "(edited)" note stacked beneath it when the post has been edited. Seeded from
 * the Post (new PostMeta($post)); top-aligned with the display name.
 */
class PostMeta extends Div
{
    public ?string $class = 'PostMeta d-flex flex-column align-items-end ms-auto';

    public ?int $postId = null;
    public ?string $createdAt = null;
    public ?string $editedAt = null;
    public ?User $author = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> createdAt !== null && $this -> postId !== null) {
            $this -> contents[] = new TimestampLink(
                ServerURL::absolute('/users/' . $this -> author -> slug . '/' . $this -> postId),
                $this -> createdAt
            );
        }

        if ($this -> editedAt !== null) {
            $this -> contents[] = new PostEditedMarker($this -> editedAt);
        }

        return parent::toDOM();
    }
}
