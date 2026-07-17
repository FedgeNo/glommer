<?php

declare(strict_types=1);

class ParentPostLink extends HTMLObject
{
    public string $tagName = 'p';
    public ?string $class = 'Muted text-sm ParentPostLink';

    public ?int $parentId = null;
    public ?string $parentUsername = null;
    public ?string $parentLabel = null;

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = 'In response to ';
        $this -> addContent(new Anchor(ServerURL::absolute('/users/' . $this -> parentUsername . '/' . $this -> parentId), $this -> parentLabel));

        return parent::toDOM();
    }

    public static function fromParentId(int $parent_id): ?self
    {
        $parent = DB::row('
SELECT `Posts`.`title`, `Posts`.`description`, `Users`.`slug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
', 'ParentPostLinkData', 'i', $parent_id);

        if ($parent === null) {
            return null;
        }

        // description is already plaintext (Delta::plainText derives it), so
        // there's no markup to strip - and stripping would eat any literal
        // '<'/'>' the text legitimately contains.
        $description = $parent -> description !== null ? trim($parent -> description) : '';

        $link = new self();
        $link -> parentId = $parent_id;
        $link -> parentUsername = $parent -> slug;
        $link -> parentLabel = $parent -> title ?? ($description !== '' ? mb_substr($description, 0, 60) : 'this post');

        return $link;
    }
}
