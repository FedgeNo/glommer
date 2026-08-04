<?php

declare(strict_types=1);

/**
 * Follows a Fediverse account, or withdraws the follow. One button in two
 * states rather than two buttons, since it is one decision.
 */
class UserFollowButton extends ButtonButton
{
    public function __construct(int $user_id, bool $following)
    {
        parent::__construct();

        $this -> type = 'button';

        if ($following) {
            $this -> class .= ' Removing';
        }

        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> attributes['data-following'] = $following ? '1' : '0';
        $this -> contents[] = $following ? 'Unfollow' : 'Follow';
    }
}
