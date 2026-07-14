<?php

declare(strict_types=1);

class ReplyComposer extends Composer
{
    public int $replyToPostId;

    public function __construct(int $reply_to_post_id)
    {
        parent::__construct();

        $this -> replyToPostId = $reply_to_post_id;
    }

    protected function addFields(): void
    {
        $parent_id_input = new HiddenInput();
        $parent_id_input -> name = 'parentId';
        $parent_id_input -> value = (string) $this -> replyToPostId;
        $this -> contents[] = $parent_id_input;
    }

    protected function legend(): string
    {
        return 'Write a reply';
    }

    protected function editorPlaceholder(): string
    {
        return 'Write a reply';
    }

    protected function submitLabel(): string
    {
        return 'Reply';
    }
}
