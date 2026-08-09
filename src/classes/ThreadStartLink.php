<?php

declare(strict_types=1);

/**
 * The way out of the middle of a thread: a link to the post it began with,
 * sitting at the end of the line that says what this reply answers.
 */
class ThreadStartLink extends Anchor
{
    public ?string $class = 'ThreadStartLink';
}
