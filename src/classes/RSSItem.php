<?php

declare(strict_types=1);

class RSSItem
{
    /**
     * Set once by RSSFeed::toXML() before building any items - RSS's own
     * equivalent of HTMLObject::$document, so an item doesn't need the
     * document threaded through every call to build its element.
     */
    public static \DOMDocument $document;

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

    public function toElement(): \DOMElement
    {
        $document = self::$document;

        $item = $document -> createElement('item');
        $item -> appendChild(self::textElement('title', $this -> title));
        $item -> appendChild(self::textElement('link', $this -> link));

        $description = $document -> createElement('description');
        $description -> appendChild($document -> createCDATASection($this -> description));
        $item -> appendChild($description);

        $item -> appendChild(self::textElement('pubDate', $this -> pubDate));

        $guid = self::textElement('guid', $this -> link);
        $guid -> setAttribute('isPermaLink', 'true');
        $item -> appendChild($guid);

        return $item;
    }

    /**
     * Builds an element whose value is treated as literal text. DOMDocument's
     * createElement($name, $value) entity-PARSES the value instead, so a raw
     * '&' (e.g. a display name "AT&T" or a title "Tom & Jerry") emits a warning
     * and yields an empty element, corrupting the feed. A DOMText node is a
     * literal-text sink and escapes correctly.
     */
    public static function textElement(string $name, string $value): \DOMElement
    {
        $element = self::$document -> createElement($name);
        $element -> appendChild(self::$document -> createTextNode($value));

        return $element;
    }

    /**
     * A post's RSS description is its plaintext form (Posts.description, the
     * "document" the Delta migration made this column) - the summary a feed
     * reader wants, not rich markup. It rides in a CDATA section (see
     * toElement()), so its literal text is what's published.
     */
    public static function fromPost(Post $post): self
    {
        $author_username = $post -> author ?-> slug ?? '';
        $link = ServerURL::absolute('/users/' . $author_username . '/' . $post -> postId);

        $description = $post -> description ?? '';
        $title = $post -> title ?? ($post -> description !== null ? $post -> shortDescription() : 'Untitled');

        return new self($title, $link, $description, (string) $post -> createdAt);
    }
}
