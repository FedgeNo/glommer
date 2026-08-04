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

    public function __construct(string $slug, ?string $title)
    {
        parent::__construct();

        $this -> addContent(new Anchor(ServerURL::absolute('/users/' . $slug . '/'), $title !== null && $title !== '' ? $title : $slug));
        $this -> addContent(' reposted');
    }
}
