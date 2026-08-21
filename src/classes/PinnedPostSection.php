<?php

declare(strict_types=1);

/**
 * A profile's "Pinned" section. Absent entirely when nothing is pinned - an
 * empty heading would take space to say nothing.
 */
class PinnedPostSection extends Section
{
    public ?string $class = 'PinnedPostSection';

    public ?int $userId = null;

    public function toDOM(): \DOMElement
    {
        if (PinnedPost::countFor((int) $this -> userId) === 0) {
            return parent::toDOM();
        }

        $this -> contents[] = new Heading2((string) (Strings::for(self::class)['heading'] ?? ''));
        $this -> contents[] = new PinnedPostList(['userId' => $this -> userId]);

        return parent::toDOM();
    }
}
