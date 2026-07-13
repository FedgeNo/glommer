<?php

declare(strict_types=1);

/**
 * The /tags/ 3D force-directed hashtag graph: HashtagNode links for the most-used
 * tags (sized client-side by post count) laid out by co-occurrence - tags that
 * share more posts spring together more tightly - and rotatable in any direction
 * by dragging. Built from Hashtag::graphData().
 *
 * The nodes are real, server-rendered links (so it degrades to a plain tag list
 * with no JS); tag-graph.js reads them plus the co-occurrence edges carried on
 * data-edges and takes over the layout and rotation. Edges ride on the attribute
 * rather than a <script> block so the JSON survives DOMDocument's escaping and
 * the browser hands it back intact via dataset.
 */
class HashtagGraph extends Div
{
    public ?string $class = 'HashtagGraph';

    /**
     * @param array{nodes: array<int, array{tag: string, postCount: int}>, edges: array<int, array{a: int, b: int, weight: int}>} $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        foreach ($data['nodes'] as $node) {
            $this -> addContent(new HashtagNode($node['tag'], $node['postCount']));
        }

        // Index pairs + co-occurrence weight, referencing the node order above.
        $this -> attributes['data-edges'] = json_encode($data['edges']);
    }
}
