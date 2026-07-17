<?php

declare(strict_types=1);

/**
 * The columns ParentPostLink::fromParentId() reads off its Posts/Users join.
 */
class ParentPostLinkData
{
    public ?string $title = null;
    public ?string $description = null;
    public ?string $slug = null;
}
