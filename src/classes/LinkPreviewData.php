<?php

declare(strict_types=1);

/**
 * The columns LinkPreviewFetcher::cachedMetadata() reads off a LinkPreviews row.
 */
class LinkPreviewData
{
    public ?string $title = null;
    public ?string $description = null;
    public ?string $imageURL = null;
    public int $succeeded = 0;
}
