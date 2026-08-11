<?php

declare(strict_types=1);

/**
 * The one mark shown on a narrow screen, beside the hamburger.
 *
 * The menu is closed on mobile, so the dots on Notifications and Messages are
 * inside something nobody can see - this stands in for both of them and says
 * only "there is something in here". Opening the menu is what says which:
 * those dots are rendered too, and are what the reader sees once the list is
 * open.
 *
 * Hidden entirely on a wide screen, where the links it stands for are on the
 * page already and carry their own marks.
 */
class NavAlertDot extends Span
{
    public ?string $class = 'NavAlertDot';

    public function __construct(bool $anything_new)
    {
        parent::__construct();

        if ($anything_new) {
            $this -> class .= ' Active';
        }
    }

    public function toDOM(): \DOMElement
    {
        // See MessageDot: the dot needs words for anybody not looking at it,
        // and it says only that there is something, because that is all it
        // knows until the menu is opened. Read here, not in the constructor
        // above, for the same reason MessageDot's is.
        $this -> addContent(new HiddenLabel((string) (Strings::for(self::class)['label'] ?? '')));

        return parent::toDOM();
    }

    /** Whether either kind of thing is waiting. */
    public static function anythingNewFor(?User $user): bool
    {
        if ($user === null || $user -> userId === null) {
            return false;
        }

        return MessageDot::unreadFor($user)
            || Notification::newestIdFor((int) $user -> userId) > (int) $user -> lastNotificationId;
    }
}
