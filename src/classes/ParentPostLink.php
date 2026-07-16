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
        $stmt = DB::run('
SELECT `Posts`.`title`, `Posts`.`description`, `Users`.`username`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
', 'i', $parent_id);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row === null) {
            return null;
        }

        // description is already plaintext (Delta::plainText derives it), so
        // there's no markup to strip - and stripping would eat any literal
        // '<'/'>' the text legitimately contains.
        $description = $row['description'] !== null ? trim($row['description']) : '';

        $link = new self();
        $link -> parentId = $parent_id;
        $link -> parentUsername = $row['username'];
        $link -> parentLabel = $row['title'] ?? ($description !== '' ? mb_substr($description, 0, 60) : 'this post');

        return $link;
    }
}
