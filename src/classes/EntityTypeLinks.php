<?php

declare(strict_types=1);

/**
 * A way into each kind of topic, under what is trending on /topics/.
 *
 * Trending is a top and a short one, so most of what this server knows about
 * is not on that page at any moment. These lead to the standing lists, where
 * a kind is shown in full rather than only while it is spiking.
 *
 * The label is the kind's own plural, from the locale - the same words the
 * heading of the page each one opens is written with.
 */
class EntityTypeLinks extends Div
{
    public ?string $class = 'EntityTypeLinks';
    public array $mixins = ['d-flex', 'flex-wrap', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        foreach (EntityType::all() as $type) {
            $link = new Anchor(
                ServerURL::absolute('/topics/' . rawurlencode(EntityType::slug($type)) . '/'),
                EntityType::plural($type)
            );
            $link -> class = 'Button';

            $this -> addContent($link);
        }

        return parent::toDOM();
    }
}
