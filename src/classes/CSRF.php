<?php

declare(strict_types=1);

class CSRF
{
    /** @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string} */
    public static function cookieOptions(): array
    {
        return [
            'expires' => 0,
            'path' => '/',
            'secure' => ServerURL::isHTTPS(),
            'httponly' => false,
            'samesite' => 'Strict',
        ];
    }

    public static function token(): string
    {
        if (!isset($_SESSION['CSRFToken'])) {
            $_SESSION['CSRFToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['CSRFToken'];
    }

    public static function verify(?string $token): bool
    {
        return $token !== null && isset($_SESSION['CSRFToken']) && hash_equals($_SESSION['CSRFToken'], $token);
    }
}
