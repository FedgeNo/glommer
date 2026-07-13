<?php

declare(strict_types=1);

/**
 * One entry on the admin Banned Users page: the banned account's identity
 * plus an Unban button. Mirrored client-side in banned-user.js for entries
 * loaded by infinite scroll and search.
 */
class BannedUser extends User
{
    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-user-id'] = (string) $this -> userId;

        $row = new Div();
        $row -> class = 'd-flex align-items-center gap-3';

        $row -> addContent($this -> header());

        $unban = new UnbanButton((int) $this -> userId);
        $unban -> class = 'ms-auto ' . $unban -> class;
        $row -> addContent($unban);

        $this -> contents[] = $row;

        return HTMLObject::toDOM();
    }

    /**
     * The JSON shape banned-user.js builds an entry from.
     */
    public static function payloadFor(User $user): array
    {
        return [
            'userId' => (int) $user -> userId,
            'username' => $user -> username,
            'displayName' => $user -> displayName,
            'image' => $user -> avatarURL(),
        ];
    }
}
