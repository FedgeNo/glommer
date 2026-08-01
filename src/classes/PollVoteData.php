<?php

declare(strict_types=1);

/**
 * The columns Poll's and PollOption's queries read off a PollVotes row. Some
 * fetch only a subset of these - the rest just stay null.
 */
class PollVoteData
{
    public ?int $pollId = null;
    public ?int $pollOptionId = null;
    public ?int $userId = null;
    public ?string $createdAt = null;
}
