<?php

declare(strict_types=1);

/**
 * A titled <section> wrapping an inner ItemList (a <ul>): the items as their
 * own list, headed by an optional <h2>. Subclasses (UserListSection,
 * TagListSection, TrendingSection) supply the heading, the items, and the inner
 * list's CSS class. An empty list shows nothing at all - no heading, no notice -
 * just the bare <section> and its empty <ul>, so it takes no visual space while
 * still standing as a stable mount point the client can populate later.
 */
abstract class ListSection extends Section
{
    /** The <h2> text; blank (or an empty list) renders no heading. */
    protected string $heading = '';

    /** CSS class for the inner <ul>. */
    protected string $itemsClass = '';

    /** @var HTMLObject[]|string[] */
    public array $items = [];

    /**
     * Whether the list came back with anything - lets a page hide a whole
     * section rather than show its heading over nothing.
     */
    public function hasItems(): bool
    {
        return $this -> items !== [];
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> items !== [] && $this -> heading !== '') {
            $heading = new Heading2();
            $heading -> contents[] = $this -> heading;
            $this -> contents[] = $heading;
        }

        $list = new ItemList();

        if ($this -> itemsClass !== '') {
            $list -> class = $this -> itemsClass;
        }

        $list -> addContents($this -> items);

        $this -> contents[] = $list;

        return parent::toDOM();
    }
}
