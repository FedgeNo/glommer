<?php

declare(strict_types=1);

// The HTML layer over DOMObject: an app concept whose element carries a CSS
// class derived from its class hierarchy, guards against rendering twice, and
// accepts CData (and raw DOMNodes) as content on top of the strings and nested
// objects DOMObject already handles.
class HTMLObject extends DOMObject
{
    public string $tagName = 'div';
    public ?string $id = null;
    public ?string $class = null;

    // Set by the first output step - toDOM() or toJSON() - and a second one
    // throws. Output is one-shot because most objects build their children into
    // $this->contents in toDOM(), so a second call would duplicate them, and
    // plenty of output methods run a query or other non-idempotent work
    // besides. Reuse means building a fresh instance.
    private bool $rendered = false;

    public function __construct(array|object|null $properties = null)
    {
        // Derive the CSS class from the type first, then let DOMObject seed the
        // data properties - its skip-list already refuses to overwrite the class
        // just derived (or any other structural property).
        $this -> deriveClassName();

        parent::__construct($properties);
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

    public function toDOM(): \DOMElement
    {
        $this -> markRendered();

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

    /**
     * Claims this object's one output step. Any method that turns the object
     * into something for a client - markup, a payload - calls this first, so
     * whichever runs, it runs once.
     */
    protected function markRendered(): void
    {
        if ($this -> rendered) {
            throw new \LogicException(static::class . ' produced output twice - build a fresh instance per output step; a rendered HTMLObject is not reusable.');
        }

        $this -> rendered = true;
    }

    protected function contentToNode($item): ?\DOMNode
    {
        if ($item instanceof HTMLObject or $item instanceof CData) {
            return $item -> toDOM();
        } elseif (is_string($item)) {
            return self::$document -> createTextNode($item);
        } elseif ($item instanceof \DOMNode) {
            return self::$document -> importNode($item, true);
        }

        return null;
    }
}
