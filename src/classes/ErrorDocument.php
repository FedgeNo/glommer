<?php

declare(strict_types=1);

class ErrorDocument
{
    public static function send(int $status_code, string $title, string $message): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status_code);

        $page = new Page(['title' => $title]);

        $page -> addContent(new Paragraph($message));

        $page -> addContent(new Anchor(ServerURL::absolute('/'), 'Back to Home'));

        $page -> send();
    }
}
