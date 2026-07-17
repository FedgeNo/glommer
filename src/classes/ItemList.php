<?php

declare(strict_types=1);

/**
 * A <ul> whose children are wrapped in <li> at render time. Subclasses (a feed,
 * a friends list, a notification list, ...) keep pushing plain item objects
 * (the cards) into $this->contents and reading them back out (hasItems,
 * newestId, ...) exactly as when they were a <div>; the wrapping happens only
 * once, in toDOM, so the markup is valid <ul><li> without the subclasses having
 * to build the <li>s themselves. A child that's already a ListItem (an empty-
 * state notice a subclass wrapped itself, say) is left alone.
 */
class ItemList extends UnorderedList
{
    public function toDOM(): \DOMElement
    {
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
