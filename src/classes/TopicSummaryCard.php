<?php

declare(strict_types=1);

/**
 * The paragraph atop a topic page saying what the topic is, labelled as
 * machine-written - a reader deciding how much weight to give it is owed the
 * fact that no person wrote it.
 *
 * Rendered empty when nothing has been written yet, carrying which topic it is
 * waiting on, so the client can ask for one and fill it in. Empty is the
 * ordinary state on a site with more topics than the timer has reached: the
 * card takes no space until there are words for it, and everything that would
 * be pushed down by them sits above it.
 */
class TopicSummaryCard extends Card
{
    public ?string $class = 'TopicSummaryCard';

    public function __construct(private ?string $summary, private string $type = '', private string $slug = '')
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> summary === null) {
            $this -> class .= ' Awaited';
            $this -> attributes['data-topic-type'] = $this -> type;
            $this -> attributes['data-topic-slug'] = $this -> slug;

            return parent::toDOM();
        }

        $this -> addContent(new Paragraph($this -> summary));

        $label = new Paragraph('AI-generated summary');
        $label -> class = 'TopicSummaryLabel';
        $label -> mixins = ['muted', 'text-sm'];
        $this -> addContent($label);

        return parent::toDOM();
    }

    /** The wording the client repeats when it fills one in - one place for it. */
    public const LABEL = 'AI-generated summary';
}
