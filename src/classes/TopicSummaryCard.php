<?php

declare(strict_types=1);

/**
 * The paragraph atop a tag page saying what people are posting about there,
 * labelled as machine-written - a reader deciding how much weight to give a
 * summary is owed the fact that no person wrote it.
 */
class TopicSummaryCard extends Card
{
    public ?string $class = 'TopicSummaryCard';

    public function __construct(string $summary)
    {
        parent::__construct();

        $this -> addContent(new Paragraph($summary));

        $label = new Paragraph('AI-generated summary of recent posts');
        $label -> class = 'TopicSummaryLabel';
        $label -> mixins = ['muted', 'text-sm'];
        $this -> addContent($label);
    }
}
