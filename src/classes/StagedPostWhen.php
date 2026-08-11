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
        $words = Strings::for(self::class);

        // {when} rather than concatenation, so a language that leads with the
        // time can - "Am 3. Mai wird das veröffentlicht" puts it first.
        parent::__construct($publish_at !== null
            ? str_replace('{when}', $publish_at, (string) ($words['scheduled'] ?? ''))
            : (string) ($words['draft'] ?? ''));
    }
}
