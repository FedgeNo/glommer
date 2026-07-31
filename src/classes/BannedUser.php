<?php

declare(strict_types=1);

/**
 * One entry on the admin Banned Users page: the banned account's identity
 * plus an Unban button. Mirrored client-side in BannedUser.js for entries
 * loaded by infinite scroll and search.
 */
class BannedUser extends User
{
    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-user-id'] = (string) $this -> userId;

        $row = new Div();
        $row -> mixins = ['d-flex', 'align-items-center', 'gap-3'];

        $row -> addContent($this -> header());

        $unban = new UserUnbanButton((int) $this -> userId);
        $unban -> mixins[] = 'ms-auto';
        $row -> addContent($unban);

        $this -> contents[] = $row;

        return HTMLObject::toDOM();
    }

    /**
     * The JSON shape BannedUser.js builds an entry from.
     */
    public static function payloadFor(User $user): array
    {
        return [
            'userId' => (int) $user -> userId,
            'slug' => $user -> slug,
            'title' => $user -> title,
            'image' => $user -> avatarURL(),
        ];
    }
}
