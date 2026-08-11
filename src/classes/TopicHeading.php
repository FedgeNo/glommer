<?php

declare(strict_types=1);

/**
 * What a topic is, above its posts: the name, and what kind of thing the
 * extractor took it for.
 *
 * The kind is worth saying out loud rather than leaving in the URL. "Amazon"
 * is a company or a river depending on which one this page is, and the two
 * are separate topics with separate pages - so the page has to say which it
 * is holding.
 */
class TopicHeading extends Div
{
    public ?string $class = 'TopicHeading';

    public function __construct(private TrendingEntityChip $entity)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $name = new Heading2();
        $name -> contents[] = (string) $this -> entity -> title;
        $this -> addContent($name);

        $kind = new Anchor(
            ServerURL::absolute('/topics/' . rawurlencode((string) $this -> entity -> type) . '/'),
            EntityType::label((string) $this -> entity -> type)
        );
        $kind -> class = 'TopicKind';

        $this -> addContent($kind);

        // Above the paragraph rather than under it: the paragraph can arrive
        // seconds after the page does, and anything below it moves when it
        // lands.
        $search = new Anchor(
            ServerURL::absolute('/search?q=' . urlencode((string) $this -> entity -> title)),
            (string) (Strings::for(self::class)['searchLink'] ?? '')
        );
        $search -> class = 'TopicSearchLink';

        $this -> addContent($search);

        return parent::toDOM();
    }
}
