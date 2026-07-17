<?php

declare(strict_types=1);

class HTMLDocument extends HTMLObject
{
    public string $tagName = 'html';
    public Head $head;
    public Body $body;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);
        $this -> head = new Head();
        $this -> body = new Body();
        parent::addContents([$this -> head, $this -> body]);
    }

    public function addHeadContent(HTMLObject|CData|string|\DOMNode $item): void
    {
        $this -> head -> addContent($item);
    }

    /**
     * Page content goes in the body - the only children <html> itself ever
     * has are <head> and <body>, so the inherited append-to-own-contents
     * behavior could only ever produce invalid markup here.
     */
    public function addContent(HTMLObject|CData|string|\DOMNode $item): void
    {
        $this -> body -> addContent($item);
    }
    
    public function addContents(Array $items): void
    {
        $this -> body -> addContents($items);
    }

    public function toDOM(): \DOMElement
    {
        $implementation = new \DOMImplementation();
        $doctype = $implementation -> createDocumentType('html');

        self::$document = $implementation -> createDocument(null, '', $doctype);
        self::$document -> encoding = 'UTF-8';
        self::$document -> formatOutput = true;

        if (Auth::check()) {
            $current_user = Auth::user();

            if ($current_user !== null && $current_user -> theme !== 'system') {
                $this -> attributes['data-theme'] = $current_user -> theme;
            }
        }

        return parent::toDOM();
    }

    public function __toString(): string
    {
        $html = $this -> toDOM();

        // Attach the root to the document so fillEmptyNonVoidTags's
        // document-wide XPath can reach it (the same order render() uses),
        // then serialize just that element.
        self::$document -> appendChild($html);
        self::fillEmptyNonVoidTags($html);

        return '<!DOCTYPE html>
'
            . self::stripSelfClosingSlash(self::$document -> saveXML($html));
    }

    public function send(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $this;
    }
}
