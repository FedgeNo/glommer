<?php

declare(strict_types=1);

/** Shared URL rules for an attachment and the elements displaying its media. */
trait FeedMedia
{
    public function srcURL(): string
    {
        // Only the stored item ID goes to the proxy, never an arbitrary URL.
        if ($this -> remoteURL !== null) {
            return RemoteMedia::proxyURL((int) $this -> itemId);
        }

        return ServerURL::absolute(UploadProcessor::srcPath((int) $this -> itemId, (string) $this -> type));
    }

    public function imageURL(): ?string
    {
        // Remote files are not copied or transcoded here and have no thumbnail.
        if ($this -> remoteURL !== null) {
            return null;
        }

        $path = UploadProcessor::thumbnailPath((int) $this -> itemId, (string) $this -> type);

        return $path !== null ? ServerURL::absolute($path) : null;
    }
}
