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
class RememberedDevicesList extends Div
{
    public ?string $class = 'd-flex flex-column gap-2 RememberedDevicesList';

    public int $userId;

    public function __construct(int $user_id)
    {
        parent::__construct();

        $this -> userId = $user_id;
    }

    public function toDOM(): \DOMElement
    {
        $rows = RememberToken::rowsForUser($this -> userId);
        $current_selector = RememberToken::currentSelector();

        if ($rows === []) {
            $this -> addContent(new Notice('No remembered devices. Devices where you check "Remember me" at login appear here.'));

            return parent::toDOM();
        }

        foreach ($rows as $row) {
            $this -> addContent(new RememberedDevice($row, $row -> selector === $current_selector));
        }

        return parent::toDOM();
    }
}
