<?php

declare(strict_types=1);

class HTMLDocument extends HTMLObject
{
    public string $tagName = 'html';
    public Head $head;
    public Body $body;

    public function __construct()
    {
        parent::__construct();
        $this -> head = new Head();
        $this -> body = new Body();
    }

    /**
     * Tracks how far into $head -> contents the metadata block (charset,
     * viewport, title, description/OG/twitter meta tags, JSON-LD) reaches -
     * set by Page::create() once it's built that block, so addMetaContent()
     * knows where to insert.
     */
    public int $metaContentEndIndex = 0;

    public function addHeadContent(HTMLObject|string|\DOMNode $item): void
    {
        $this -> head -> addContents($item);
    }

    /**
     * Inserts into the head immediately after the metadata block and before
     * any stylesheet/script - for content that's metadata in spirit (e.g. an
     * RSS <link rel="alternate">) but added by a page script after
     * Page::create() has already built the rest of the head.
     */
    public function addMetaContent(HTMLObject|string|\DOMNode $item): void
    {
        array_splice($this -> head -> contents, $this -> metaContentEndIndex, 0, [$item]);
        $this -> metaContentEndIndex++;
    }

    /**
     * Page content goes in the body - the only children <html> itself ever
     * has are <head> and <body>, so the inherited append-to-own-contents
     * behavior could only ever produce invalid markup here.
     */
    public function addContents(HTMLObject|string|\DOMNode $item): void
    {
        $this -> body -> addContents($item);
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

        $html = parent::toDOM();
        $html -> appendChild($this -> head -> toDOM());
        $html -> appendChild($this -> body -> toDOM());

        self::$document -> appendChild($html);

        return $html;
    }

    public function __toString(): string
    {
        $html = $this -> toDOM();

        self::fillEmptyNonVoidTags($html);

        return '<!DOCTYPE html>
'
            . self::stripSelfClosingSlash(self::$document -> saveXML(self::$document -> documentElement));
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
