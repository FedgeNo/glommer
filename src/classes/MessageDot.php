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

    public function toDOM(): \DOMElement
    {
        // A coloured circle says nothing to anybody not looking at it. The
        // words ride inside it out of sight, and are only in the accessibility
        // tree at all while the dot is shown - an inactive one is display:none.
        // Read here rather than in the constructor above: an object built
        // early and rendered late would otherwise be fixed in whatever
        // language was current when somebody happened to construct it.
        $this -> addContent(new HiddenLabel((string) (Strings::for(self::class)['label'] ?? '')));

        return parent::toDOM();
    }

    /** One answer per loaded User - see unreadFor(). */
    private static ?\WeakMap $unreadByUser = null;

    /**
     * Whether this member has been sent anything since they last looked.
     *
     * Answered once per User instance: the navigation asks three times per
     * render (the mobile alert dot, the dot on the site title, the one beside
     * the Messages link), each time for the same cached Auth::user(), and the
     * answer cannot change between them. Keyed on the instance rather than the
     * id so a freshly loaded User is always answered fresh.
     */
    public static function unreadFor(?User $user): bool
    {
        if ($user === null || $user -> userId === null) {
            return false;
        }

        self::$unreadByUser ??= new \WeakMap();

        if (!isset(self::$unreadByUser[$user])) {
            self::$unreadByUser[$user] = Message::newestReceivedId((int) $user -> userId) > (int) $user -> lastMessageId;
        }

        return self::$unreadByUser[$user];
    }
}
