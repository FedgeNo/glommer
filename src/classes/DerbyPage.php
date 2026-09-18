<?php

declare(strict_types=1);

/** Serve the source HTML intact, with a separate host adapter after its script. */
class DerbyPage
{
    public function render(): string
    {
        $source = file_get_contents(__DIR__ . '/../../games/bent-metal-derby/index.html');
        if ($source === false) throw new \RuntimeException('The Derby source checkout is missing.');
        $nonce = htmlspecialchars(SecurityHeaders::nonce(), ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars(CSRF::token(), ENT_QUOTES, 'UTF-8');
        $source = preg_replace('/<script\b/', '<script nonce="' . $nonce . '"', $source);
        $source = str_replace('</head>', '<meta name="glommer-csrf" content="' . $token . '"><link rel="stylesheet" href="/games/derby.css"></head>', $source);
        return str_replace('</body>', '<script nonce="' . $nonce . '" src="/games/derby.js"></script></body>', $source);
    }

    public function send(): void
    {
        SecurityHeaders::send(allows_game_ads: true);
        header('Cache-Control: no-store');
        echo $this -> render();
    }
}
