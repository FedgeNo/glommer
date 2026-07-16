<?php

declare(strict_types=1);

class Conversation extends Anchor
{
    public ?string $class = 'Card d-flex align-items-center gap-3 Conversation';

    public ?int $userId = null;
    public ?string $username = null;
    public ?string $displayName = null;
    public int $hasAvatar = 0;
    public ?string $lastMessageAt = null;

    public function toDOM(): \DOMElement
    {
        $this -> href = ServerURL::absolute('/messages/' . $this -> username);

        $name = $this -> displayName ?? $this -> username;

        $this -> contents[] = Avatar::create(
            (bool) $this -> hasAvatar,
            $this -> hasAvatar ? ServerURL::absolute(User::avatarPath((int) $this -> userId)) : null,
            $name,
            (int) $this -> userId
        );

        $info = new Div();

        $name_heading = new Heading2();
        $name_heading -> contents[] = $name;
        $info -> addContent($name_heading);

        $username_line = new Div();
        $username_line -> class = 'Muted text-sm';
        $username_line -> contents[] = '@' . $this -> username;
        $info -> addContent($username_line);

        if ($this -> lastMessageAt !== null) {
            $meta = new Div();
            $meta -> class = 'Muted text-sm';
            $meta -> contents[] = 'Last message ';

            $meta -> addContent(new RelativeTime($this -> lastMessageAt));

            $info -> addContent($meta);
        }

        $this -> contents[] = $info;

        return parent::toDOM();
    }
}
