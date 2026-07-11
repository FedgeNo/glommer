<?php

declare(strict_types=1);

class PostPage extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'PostPage Card';

    public ?Post $post = null;

    public function toDOM(): \DOMElement
    {
        $action_bar = new PostActionBar();
        $action_bar -> postId = (int) $this -> post -> postId;
        $action_bar -> postUserId = (int) $this -> post -> userId;
        $action_bar -> postUsername = $this -> post -> author ?-> username;
        $action_bar -> standalone = true;

        // The permalink page shows the focused post in full - no "See More"
        // truncation of its description the way the feed does.
        $this -> post -> truncateDescription = false;

        $this -> contents[] = $this -> post;
        $this -> contents[] = $action_bar;

        return parent::toDOM();
    }

    public static function fromPost(Post $post): self
    {
        $page = new self();
        $page -> post = $post;

        return $page;
    }
}
