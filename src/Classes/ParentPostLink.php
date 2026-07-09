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
        $this -> addContents(new Anchor(URL::absolute('/users/' . $this -> parentUsername . '/' . $this -> parentId), $this -> parentLabel));

        return parent::toDOM();
    }

    public static function fromParentId(int $parent_id): ?self
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `Posts`.`title`, `Posts`.`description`, `Users`.`username`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $parent_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row === null) {
            return null;
        }

        $description = $row['description'] !== null ? trim(strip_tags($row['description'])) : '';

        $link = new self();
        $link -> parentId = $parent_id;
        $link -> parentUsername = $row['username'];
        $link -> parentLabel = $row['title'] ?? ($description !== '' ? substr($description, 0, 60) : 'this post');

        return $link;
    }
}
