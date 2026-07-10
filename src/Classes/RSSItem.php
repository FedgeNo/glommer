<?php

declare(strict_types=1);

class RSSItem
{
    public string $title;
    public string $link;
    public string $description;
    public string $pubDate;

    public function __construct(string $title, string $link, string $description, string $created_at)
    {
        $this -> title = $title;
        $this -> link = $link;
        $this -> description = $description;
        $this -> pubDate = date(DATE_RSS, strtotime($created_at));
    }

    public function toElement(\DOMDocument $document): \DOMElement
    {
        $item = $document -> createElement('item');
        $item -> appendChild($document -> createElement('title', $this -> title));
        $item -> appendChild($document -> createElement('link', $this -> link));

        $description = $document -> createElement('description');
        $description -> appendChild($document -> createCDATASection($this -> description));
        $item -> appendChild($description);

        $item -> appendChild($document -> createElement('pubDate', $this -> pubDate));

        $guid = $document -> createElement('guid', $this -> link);
        $guid -> setAttribute('isPermaLink', 'true');
        $item -> appendChild($guid);

        return $item;
    }

    /**
     * A post's RSS description is put through the same PostBody/HTMLCleaner
     * sanitizing pass as a page load - same reasoning as Post::toPayload():
     * the raw stored description isn't guaranteed whitelist-safe on its own.
     */
    public static function fromPost(Post $post): self
    {
        $author_username = $post -> author ?-> username ?? '';
        $link = URL::absolute('/users/' . $author_username . '/' . $post -> postId);

        $body = new PostBody();

        if ($post -> description !== null) {
            $body -> addContents($post -> description);
        }

        $description = HTMLObject::renderInner($body);
        $title = $post -> title ?? ($post -> description !== null ? $post -> shortDescription() : 'Untitled');

        return new self($title, $link, $description, (string) $post -> createdAt);
    }
}
