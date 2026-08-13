<?php

declare(strict_types=1);

/**
 * Admin-editable, runtime site settings, stored in the database (not .env) so
 * they can be changed from the admin panel - .env is not reliably writable once
 * an install's file permissions are locked down. Values are strings; callers
 * interpret them.
 *
 * The whole table is read on the first ask and kept for the request. There are
 * a few dozen rows and a page reads five or six of them - the site's title, its
 * icon, whether a captcha is on - from wherever it happens to need them, which
 * as one query each was more round trips than most pages spent on their actual
 * content.
 */
class Settings
{
    /** @var array<string, ?string> */
    private static array $cache = [];

    private static bool $loaded = false;

    public static function get(string $name, ?string $default = null): ?string
    {
        try {
            self::load();
        } catch (\mysqli_sql_exception $exception) {
            // The Settings table may not exist yet - an existing install whose
            // code was updated before the schema migration ran. This is on the
            // login/signup render path, so degrade gracefully: treat every
            // setting as unset (Turnstile stays off) rather than failing the
            // whole page. The version gate, which asks through load() itself,
            // does want to hear about it.
        }

        return self::$cache[$name] ?? $default;
    }

    /**
     * Reads the table, once.
     *
     * Public because the version gate reads a setting before anything else on
     * the request does, and that read is the one that must not be swallowed -
     * a database that cannot answer is a server fault, not a site with its
     * settings unset. Everything after it is served from what this brought
     * back.
     *
     * @throws \mysqli_sql_exception
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Set before the read, not after: a failure below leaves an empty table
        // as the answer, and retrying it per setting is what this exists to
        // stop.
        self::$loaded = true;

        $result = mysqli_stmt_get_result(DB::run('
SELECT `name`, `value`
    FROM `Settings`
'));

        while ($result !== false && $row = mysqli_fetch_assoc($result)) {
            // Whatever a caller has already stored this request stands: it
            // knows something the table does not yet.
            self::$cache[$row['name']] ??= $row['value'];
        }
    }

    public static function set(string $name, string $value): void
    {
        DB::run('
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
', 'ss', $name, $value);

        self::$cache[$name] = $value;
    }
}
