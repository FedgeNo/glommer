<?php

declare(strict_types=1);

/**
 * The identity block as one link to the profile. CurrentUser renders the
 * same identity on a plain block instead, since its own name edits in place
 * rather than linking out.
 */
class UserLink extends Anchor
{
    public ?string $class = 'UserLink';
}
