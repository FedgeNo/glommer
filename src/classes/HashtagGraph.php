<?php

declare(strict_types=1);

/**
 * The /tags/ "Popular" section: a titled list of the most-used HashtagNodes,
 * ordered by post count. Server-rendered as a plain <ul> of tag links - a
 * readable, crawlable list that works with no JS and stays a scrollable list on
 * narrow screens - which tag-graph.js upgrades in place to a 3D force-directed
 * graph above the layout breakpoint (tags that share more posts spring together,
 * drag to rotate). It stays a list below the breakpoint because the graph
 * captures touch and wheel to rotate/zoom, which would trap the page's scroll on
 * a phone.
 *
 * The co-occurrence edges ride on the section's data-edges attribute (JSON) so
 * they survive DOMDocument's escaping and the browser hands them back intact via
 * dataset; hashtagId is carried on each node only so graphData() can index the
 * edges against node order. Built from Hashtag::graphData().
 */
class HashtagGraph extends ListSection
{
    public ?string $class = 'HashtagGraph';

    protected string $heading = 'Popular';

    protected string $itemsClass = 'HashtagGraphField';

    /** @var array<int, array{a: int, b: int, weight: int}> */
    private array $edges = [];

    /**
     * @param array{nodes: HashtagNode[], edges: array<int, array{a: int, b: int, weight: int}>} $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        $this -> items = $data['nodes'];
        $this -> edges = $data['edges'];
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-edges'] = json_encode($this -> edges);

        return parent::toDOM();
    }
}
