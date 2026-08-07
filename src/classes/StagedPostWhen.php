<?php

declare(strict_types=1);

/**
 * The line that says what a staged post is waiting for: a clock, or its
 * author's say-so.
 */
class StagedPostWhen extends Paragraph
{
    public ?string $class = 'StagedPostWhen';
    public array $mixins = ['muted', 'text-sm'];

    public function __construct(?string $publish_at)
    {
        parent::__construct($publish_at !== null
            ? 'Scheduled for ' . $publish_at
            : 'Draft - publishes only when you say so');
    }
}
