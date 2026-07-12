<?php

declare(strict_types=1);

class Env
{
    private static bool $loaded = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $path = __DIR__ . '/../../.env';

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = self::stripQuotes(trim($value));

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }

    /**
     * A hand-edited .env is easy to write as KEY="value" or KEY='value' out
     * of habit from other tools - strip one matching pair of surrounding
     * quotes so the literal quote characters don't end up part of the value
     * itself (e.g. a wrapped WS_SECRET would otherwise never match what the
     * WebSocket daemon reads from the same file). Public so
     * Installer::envContents() can strip any pre-existing surrounding quotes
     * from a value before adding its own - same rule, both directions, so the
     * round trip is stable instead of guessing.
     */
    public static function stripQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
