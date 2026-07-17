<?php

declare(strict_types=1);

// Every app concept is an HTMLObject, and most are hydrated straight off a
// query (mysqli_fetch_object sets a property per column before the constructor
// runs). Allowing dynamic properties means a class needn't pre-declare every
// column any query might select just to avoid the 8.2 dynamic-property
// deprecation - it declares the properties it actually uses, and incidental
// columns ride along harmlessly.
#[\AllowDynamicProperties]
class HTMLObject
{
    protected static \DOMDocument $document;

    public static function currentDocument(): \DOMDocument
    {
        return self::$document;
    }

    private const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    public string $tagName = 'div';
    public ?string $id = null;
    public ?string $class = null;
    public array $contents = [];
    public array $attributes = [];

    // Set the first time this object renders; a second toDOM() throws. Rendering
    // is one-shot because most objects build their children into $this->contents
    // in toDOM(), so a second call would duplicate them - and plenty of toDOM()s
    // do non-idempotent work besides. Reuse means building a fresh instance.
    private bool $rendered = false;

    /**
     * $properties (optional) seeds the object from a plain array or another
     * object: every key that names a property this class actually declares is
     * copied onto it, e.g. new ExampleList(['limit' => 20]). Only declared
     * properties are ever assigned - a stray key is ignored rather than
     * creating a PHP 8.2+ dynamic property - so it's safe to hand it a wider
     * data carrier and let it pick out what it understands.
     */
    public function __construct(array|object|null $properties = null)
    {
        $this -> deriveClassName();

        if ($properties !== null) {
            foreach (is_array($properties) ? $properties : get_object_vars($properties) as $name => $value) {
                // Never copy in the element's own identity or presentation - its
                // tag name (a Page is <html>, a User is <div>; a seed's tag must
                // never clobber it), its CSS class (each object derives its own
                // from its type), its contents (each builds its own children), or
                // its attributes - so handing it a wider source, e.g. a whole
                // page, only ever transfers data properties, never changes what
                // the object is or how it renders.
                if (in_array($name, ['tagName', 'class', 'contents', 'attributes', 'rendered'], true)) {
                    continue;
                }

                if (property_exists($this, $name)) {
                    $this -> $name = $value;
                }
            }
        }
    }

    /**
     * Sets the element's CSS class from the class hierarchy: an app concept's
     * element carries its PHP class name(s) (e.g. ImageItem -> "FeedItem
     * ImageItem"), built from whichever ancestor first declared $class down to
     * static::class. A generic HTML-primitive wrapper (Div, Button, ...) that
     * never overrode $class keeps null - it isn't an app concept.
     */
    private function deriveClassName(): void
    {
        $declaring_class = (new \ReflectionProperty(static::class, 'class')) -> getDeclaringClass() -> getName();

        if ($declaring_class === self::class) {
            return; // generic HTML-primitive wrapper (Div, Button, Anchor, etc.) - no class name of its own
        }

        $names = [];
        $class = static::class;

        while ($class !== $declaring_class) {
            $names[] = $class;
            $class = get_parent_class($class);
        }

        if ($names === []) {
            return; // this class is the one that declared $class - nothing further to add
        }

        $base_value = (new \ReflectionClass($declaring_class)) -> getDefaultProperties()['class'];
        $this -> class = trim($base_value . ' ' . implode(' ', array_reverse($names)));
    }

    public function addContent(HTMLObject|CData|string|\DOMNode $item): void
    {
        $this -> contents[] = $item;
    }

    /**
     * @param array<HTMLObject|CData|string|\DOMNode> $items
     */
    public function addContents(array $items): void
    {
        foreach ($items as $item) {
            $this -> contents[] = $item;
        }
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> rendered) {
            throw new \LogicException(static::class . '::toDOM() called twice - build a fresh instance per render; a rendered HTMLObject is not reusable.');
        }

        $this -> rendered = true;

        $element = self::$document -> createElement($this -> tagName);

        if ($this -> id !== null) {
            $element -> setAttribute('id', $this -> id);
        }

        if ($this -> class !== null) {
            $element -> setAttribute('class', $this -> class);
        }

        foreach ($this -> attributes as $name => $value) {
            $element -> setAttribute($name, $value);
        }

        foreach ($this -> contents as $item) {
            $node = $this -> contentToNode($item);
            if ($node !== null) {
                $element -> appendChild($node);
            }
        }

        return $element;
    }

    protected function contentToNode($item): ?\DOMNode
    {
        if ($item instanceof HTMLObject) {
            return $item -> toDOM();
        } elseif ($item instanceof CData) {
            return $item -> toNode();
        } elseif (is_string($item)) {
            return self::$document -> createTextNode($item);
        } elseif ($item instanceof \DOMNode) {
            return self::$document -> importNode($item, true);
        }

        return null;
    }

    /**
     * Render a standalone object (not part of a full HTMLDocument) to an HTML string.
     * Used for AJAX responses that inject a fragment into an existing page.
     */
    public function render(): string
    {
        $implementation = new \DOMImplementation();
        self::$document = $implementation -> createDocument();
        self::$document -> formatOutput = true;

        $element = $this -> toDOM();
        self::$document -> appendChild($element);

        self::fillEmptyNonVoidTags($element);

        return self::stripSelfClosingSlash(self::$document -> saveXML($element));
    }

    protected static function fillEmptyNonVoidTags(\DOMElement $root): void
    {
        $xpath = new \DOMXPath(self::$document);

        foreach ($xpath -> query('//*[not(node())]', $root) as $element) {
            if (!in_array($element -> tagName, self::VOID_ELEMENTS, true)) {
                $element -> appendChild(self::$document -> createTextNode(''));
            }
        }
    }

    /**
     * saveXML() self-closes every empty element ("<tag/>"), which is correct
     * XML but not HTML5 - void elements (img, br, ...) should read "<tag>"
     * with no closing tag at all, and fillEmptyNonVoidTags() above already
     * guarantees no non-void element is ever empty enough to self-close. That
     * leaves "/>" only ever appearing at an actual void-element close, since
     * saveXML entity-escapes any bare ">" inside text or attribute values -
     * so this replace can't corrupt real content.
     */
    protected static function stripSelfClosingSlash(string $xml): string
    {
        return str_replace('/>', '>', $xml);
    }
}
