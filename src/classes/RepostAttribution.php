<?php

declare(strict_types=1);

/**
 * Who put this post in front of you: the line over a card that is here because
 * somebody reposted it rather than because its author is followed. Without it
 * a stranger's post simply appears, unexplained.
 */
class RepostAttribution extends Div
{
    public ?string $class = 'RepostAttribution';

    public function __construct(private readonly string $slug, private readonly ?string $title)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $sentence = Strings::for(self::class)['attribution'] ?? [];

        $this -> addContent((string) ($sentence['before'] ?? ''));
        $this -> addContent(new Anchor(
            ServerURL::absolute('/users/' . $this -> slug . '/'),
            $this -> title !== null && $this -> title !== '' ? $this -> title : $this -> slug
        ));
        $this -> addContent((string) ($sentence['after'] ?? ''));

        return parent::toDOM();
    }
}
