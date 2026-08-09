<?php

declare(strict_types=1);

/** One ancestor row from ThreadContext's walk up out of a reply. */
class ThreadContextData
{
    public ?int $origin = null;
    public ?int $depth = null;
    public ?int $postId = null;
    public ?int $parentId = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $slug = null;
}
