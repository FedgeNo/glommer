<?php

declare(strict_types=1);

class PostComposer extends Composer
{
    protected function legend(): string
    {
        return 'Create a post';
    }

    protected function editorPlaceholder(): string
    {
        return 'What\'s on your mind?';
    }

    protected function submitLabel(): string
    {
        return 'Post';
    }
}
