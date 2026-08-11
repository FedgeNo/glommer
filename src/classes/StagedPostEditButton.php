<?php

declare(strict_types=1);

/**
 * Opens a draft or scheduled post in the composer. A link dressed as a
 * button, because editing happens on a page of its own - the same composer
 * the post was written in, rather than a smaller form standing in for it.
 */
class StagedPostEditButton extends Anchor
{
    public ?string $class = 'StagedPostEditButton';
    public array $mixins = ['Button'];

    public function __construct(int $staged_post_id)
    {
        parent::__construct(
            ServerURL::absolute('/drafts/' . $staged_post_id),
            (string) (Strings::for(self::class)['name'] ?? '')
        );
    }
}
