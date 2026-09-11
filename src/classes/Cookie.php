<?php

declare(strict_types=1);

class Cookie
{
    public static function name(string $name): string
    {
        // Plain HTTP is only available to the initial setup wizard, before
        // TLS is configured. Installed sites require HTTPS in init.php.
        return ServerURL::isHTTPS() ? '__Host-' . $name : $name;
    }

    public static function get(string $name): ?string
    {
        $value = $_COOKIE[self::name($name)] ?? null;

        return is_string($value) ? $value : null;
    }

    public static function clearLegacy(): void
    {
        if (!ServerURL::isHTTPS()) {
            return;
        }

        // Retire the old names without accepting them as a fallback: a
        // sibling subdomain could have supplied those unprefixed cookies.
        foreach (['PHPSESSID', 'rememberToken', 'CSRF-TOKEN', 'APP-CONFIG'] as $name) {
            if (!isset($_COOKIE[$name])) {
                continue;
            }

            setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            unset($_COOKIE[$name]);
        }
    }
}
