<?php

declare(strict_types=1);

class CurrentUser extends User
{
    // Fetched as a plain User, not self - mysqli_fetch_object() would call
    // this very constructor (after hydrating properties) if fetched directly
    // as CurrentUser, recursing right back into this method.
    private static ?User $cachedUser = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        // Seeded from a row the caller already fetched (the profile page hands
        // over the user it loaded, so viewing your own profile is one Users
        // query, not two). With nothing passed, this IS the logged-in user,
        // loaded from the session id.
        if ($properties !== null || !Auth::check()) {
            return;
        }

        if (self::$cachedUser === null) {
            self::$cachedUser = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', Auth::id());
        }

        if (self::$cachedUser !== null) {
            foreach (self::$cachedUser as $key => $value) {
                $this -> $key = $value;
            }
        }
    }

    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();

        if (Auth::check() && Auth::id() === $this -> userId) {
            $actions = new Div();
            $actions -> class = 'd-flex flex-column align-items-end gap-2 ms-auto';

            $friends_link = new Anchor(ServerURL::absolute('/users/' . $this -> slug . '/friends'), 'Friends');
            $friends_link -> class = 'Btn';
            $actions -> addContent($friends_link);

            $actions -> addContent(new AvatarUploader());

            $element -> appendChild($actions -> toDOM());
        }

        return $element;
    }
}
