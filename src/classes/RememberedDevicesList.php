<?php

declare(strict_types=1);

/**
 * The Settings "Remembered devices" section: every persistent "Remember me"
 * login still active for this user, so they can spot one they don't
 * recognise and revoke it. A login where "Remember me" was NOT checked
 * leaves no persistent token and so isn't listed - it's a session that dies
 * with the browser, never a standing device. The current browser's own
 * remembered token (if any) is marked and left un-revokable here (see
 * RememberedDevice).
 */
class RememberedDevicesList extends ItemList
{
    public ?string $class = 'd-flex flex-column RememberedDevicesList';

    public int $userId;

    public function __construct(int $user_id)
    {
        parent::__construct();

        $this -> userId = $user_id;
    }

    public function toDOM(): \DOMElement
    {
        $devices = RememberToken::rowsForUser($this -> userId);

        if ($devices === []) {
            $this -> addContent(new Notice('No remembered devices. Devices where you check "Remember me" at login appear here.'));

            return parent::toDOM();
        }

        $this -> addContents($devices);

        return parent::toDOM();
    }
}
