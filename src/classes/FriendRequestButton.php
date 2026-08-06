<?php

declare(strict_types=1);

/**
 * Asks for a friendship, or takes the ask back while it is still pending.
 */
class FriendRequestButton extends ButtonButton
{
    public function __construct(int $user_id, bool $sent_by_viewer)
    {
        parent::__construct();

        $this -> type = 'button';

        if ($sent_by_viewer) {
            $this -> class .= ' Removing';
        }

        $this -> attributes['data-user-id'] = (string) $user_id;
        $this -> attributes['data-sent'] = $sent_by_viewer ? '1' : '0';
        $this -> contents[] = $sent_by_viewer ? 'Cancel Request' : 'Add Friend';
    }
}
