<?php

declare(strict_types=1);

/**
 * A titled <section> wrapping an inner ItemList (a <ul>): the items as their
 * own list, headed by an optional <h2>. Subclasses (UserListSection,
 * HashtagGraph, TrendingHashtagList, TrendingSection) supply the heading, the
 * items, and the inner list's CSS class. An empty list shows nothing at all -
 * no heading, no notice -
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

    /**
     * Whether the inner <ul> carries this section's paging state and markers.
     * True where the client grows the list by appending to that <ul> and reads
     * how far it has got from the same element.
     */
    protected function pagesOnItems(): bool
    {
        return false;
    }

    /**
     * Attributes for the inner <ul>, for a section whose client-side identity
     * lives on the list rather than the wrapper.
     *
     * @return array<string, string>
     */
    protected function itemsAttributes(): array
    {
        return [];
    }

    /**
     * Whether the heading stands even with nothing under it. False keeps an
     * empty section out of the way entirely; true suits a list the client
     * populates later and needs a stable <h2> to retitle.
     */
    protected function headsEmptyList(): bool
    {
        return false;
    }

    public function toDOM(): \DOMElement
    {
        if (($this -> items !== [] || $this -> headsEmptyList()) && $this -> heading !== '') {
            $heading = new Heading2();
            $heading -> contents[] = $this -> heading;
            $this -> contents[] = $heading;
        }

        $list = new ItemList();

        if ($this -> itemsClass !== '') {
            $list -> class = $this -> itemsClass;
        }

        $list -> items = $this -> items;

        if ($this -> pagesOnItems()) {
            $list -> offset = $this -> offset;
            $list -> hasMore = $this -> hasMore;
            $list -> advertisesPaging = true;
        }

        foreach ($this -> itemsAttributes() as $name => $value) {
            $list -> attributes[$name] = $value;
        }

        $this -> contents[] = $list;

        return parent::toDOM();
    }
}
