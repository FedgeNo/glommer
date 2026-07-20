<?php

declare(strict_types=1);

/**
 * A <ul> whose children are wrapped in <li> at render time, so the markup is
 * valid <ul><li> without a subclass having to build the <li>s itself. A child
 * that's already a ListItem (an empty-state notice a subclass wrapped) is left
 * alone.
 *
 * Whatever a subclass loaded is poured in here, so a list of its own is a
 * rows() and nothing else. A plain one is the inner <ul> a ListSection builds
 * around its items.
 */
class ItemList extends UnorderedList
{
    /** Stands in for the items when a list comes back empty; blank shows none. */
    protected string $emptyNotice = '';

    public function toDOM(): \DOMElement
    {
        $this -> addContents($this -> items);

        if ($this -> contents === [] && $this -> emptyNotice !== '') {
            $this -> addContent(new Notice($this -> emptyNotice));
        }

        $this -> contents = array_map(static function ($item): ListItem {
            if ($item instanceof ListItem) {
                return $item;
            }

            $list_item = new ListItem();
            $list_item -> addContent($item);

            return $list_item;
        }, $this -> contents);

        return parent::toDOM();
    }
}
