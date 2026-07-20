<?php

declare(strict_types=1);

/**
 * A titled section over a list of users.
 */
abstract class UserSection extends ListSection
{
    public ?string $class = 'UserSection';

    public ?User $user = null;
    public int $offset = 0;
}
