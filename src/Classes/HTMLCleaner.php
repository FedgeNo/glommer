<?php

declare(strict_types=1);

class HTMLCleaner extends HTMLLoader
{
    /**
     * Map of allowed tag name => list of allowed attribute names on that tag.
     * Any element whose tag isn't a key here is removed (its children are kept,
     * except for tags in DELETE_ENTIRELY). Any attribute not listed for its tag
     * is removed. URL-bearing attributes are additionally scheme-checked.
     */
    public array $whitelist = [];

    protected const DELETE_ENTIRELY = ['script', 'style'];

    protected const URL_ATTRIBUTES = ['href', 'src'];

    protected const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto'];

    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();

        while (($disallowed = $this -> findDisallowedElement($element)) !== null) {
            $parent = $disallowed -> parentNode;

            if (!in_array($disallowed -> tagName, self::DELETE_ENTIRELY, true)) {
                while ($disallowed -> firstChild !== null) {
                    $parent -> insertBefore($disallowed -> firstChild, $disallowed);
                }
            }

            $parent -> removeChild($disallowed);
        }

        foreach ($element -> getElementsByTagName('*') as $candidate) {
            $this -> cleanAttributes($candidate);
        }

        return $element;
    }

    protected function findDisallowedElement(\DOMElement $root): ?\DOMElement
    {
        foreach ($root -> getElementsByTagName('*') as $candidate) {
            if (!array_key_exists($candidate -> tagName, $this -> whitelist)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function cleanAttributes(\DOMElement $element): void
    {
        $allowed = $this -> whitelist[$element -> tagName] ?? [];

        $remove = [];

        foreach ($element -> attributes as $attribute) {
            $name = $attribute -> name;

            if (!in_array($name, $allowed, true)) {
                $remove[] = $name;
                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && !self::isSafeURL($attribute -> value)) {
                $remove[] = $name;
            }
        }

        foreach ($remove as $name) {
            $element -> removeAttribute($name);
        }
    }

    protected static function isSafeURL(string $url): bool
    {
        // Strip whitespace and control characters, which browsers ignore when
        // parsing a URL scheme (so "java\nscript:" would otherwise slip through).
        $url = preg_replace('/[\x00-\x20]+/', '', $url);

        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $matches)) {
            return true; // relative URL - no scheme to smuggle
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_URL_SCHEMES, true);
    }
}
