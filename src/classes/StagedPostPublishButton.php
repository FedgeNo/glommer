<?php

declare(strict_types=1);

/** Publishes a draft or scheduled post immediately, ahead of any clock. */
class StagedPostPublishButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
