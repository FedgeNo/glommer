<?php

declare(strict_types=1);

class CSRF
{
    public static function token(): string
    {
        if (!isset($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    public static function verify(?string $token): bool
    {
        return $token !== null && isset($_SESSION['csrfToken']) && hash_equals($_SESSION['csrfToken'], $token);
    }
}
