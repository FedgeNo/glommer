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
        // The raw values the in-place editor prefills its input/textarea from -
        // the linkified bio and (possibly slug-fallback) name can't be read back
        // out of the rendered DOM.
        $this -> attributes['data-title'] = (string) ($this -> title ?? '');
        $this -> attributes['data-description'] = (string) ($this -> description ?? '');

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

    /**
     * Your own card: the name is editable in place, so the identity can't be one
     * big profile link. Only the avatar links out; the name/username/joined
     * column carries the edit pencil.
     */
    protected function identityElement(): HTMLObject
    {
        $block = new Div();
        $block -> class = 'UserLink';

        $avatar_link = new Anchor(ServerURL::absolute('/users/' . $this -> slug . '/'));
        $avatar_link -> addContent(Avatar::forUser($this));
        $block -> addContent($avatar_link);

        $block -> addContent($this -> identityInfo());

        return $block;
    }

    /** The display name paired with the edit pencil. */
    protected function nameElement(): HTMLObject
    {
        $row = new Div();
        $row -> class = 'DisplayNameRow d-flex align-items-center gap-2';

        $heading = new Heading2();
        $heading -> class = 'DisplayName';
        $heading -> contents[] = $this -> title ?: $this -> slug;
        $row -> addContent($heading);

        $row -> addContent(new EditProfileButton());

        return $row;
    }

    /**
     * Always present on your own card, even when empty, so there's a bio to
     * click into and the editor has a target (an empty one shows a prompt - see
     * the .CurrentUser .UserBio:empty rule).
     */
    protected function bioElement(): ?HTMLObject
    {
        return new UserBio($this);
    }
}
