<?php

declare(strict_types=1);

/**
 * The mark that says a message is waiting - beside the Messages link, and on
 * the site's own name, which is where someone's eye goes first and which is
 * visible from every page whether or not the menu is open.
 *
 * Rendered on every page and simply hidden when there is nothing to say, the
 * same as NotificationDot: a dot that only exists in the markup sometimes
 * cannot be switched on by a message arriving live.
 */
class MessageDot extends Span
{
    public ?string $class = 'MessageDot';

    public function __construct(bool $unread)
    {
        parent::__construct();

        if ($unread) {
            $this -> class .= ' Active';
        }
    }

    /** Whether this member has been sent anything since they last looked. */
    public static function unreadFor(?User $user): bool
    {
        if ($user === null || $user -> userId === null) {
            return false;
        }

        return Message::newestReceivedId((int) $user -> userId) > (int) $user -> lastMessageId;
    }
}
