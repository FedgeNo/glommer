<?php

declare(strict_types=1);

/**
 * The provisioning steps shared by the two install paths (the web setup
 * wizard in src/setup.php and the interactive CLI in bin/install.php):
 * creating the database, a least-privilege runtime account, the schema,
 * and the .env contents that record all of it.
 */
class Installer
{
    /**
     * Creates the database (if missing), a least-privilege runtime account
     * with a freshly generated random password, grants it SELECT/INSERT/
     * UPDATE/DELETE on that database only, and creates every table from
     * schema.sql that doesn't already exist.
     *
     * @param array<string, string> $initial_settings name => value rows to seed
     *                                                 into the Settings table
     * @return array{username: string, password: string} the runtime account credentials
     */
    public static function provisionDatabase(\mysqli $admin_connection, string $db_database, array $initial_settings = []): array
    {
        // CREATE DATABASE / CREATE USER / GRANT can't be prepared (MySQL's
        // prepared-statement protocol doesn't cover those statement types), so
        // the database name is interpolated below. Both callers already
        // validate it, but enforce it here too so the interpolation is safe no
        // matter who calls this.
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_database)) {
            throw new \RuntimeException('Invalid database name - only letters, numbers, and underscores are allowed.');
        }

        $runtime_username = $db_database;
        $runtime_password = bin2hex(random_bytes(24));

        mysqli_query($admin_connection, '
CREATE DATABASE IF NOT EXISTS `' . $db_database . '`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
');
        mysqli_select_db($admin_connection, $db_database);

        // CREATE USER/GRANT can't go through mysqli_prepare - account-management
        // statements aren't supported by the prepared-statement protocol on most
        // server versions - so the (self-generated, never user-supplied) password
        // is escaped and interpolated directly instead of bound as a placeholder.
        // The password stays raw in $runtime_password; it's escaped inline at each
        // query (the last possible step) rather than into a variable, so nobody
        // later reuses a pre-escaped value or feeds the raw one straight in.
        // Scoped to host '%' rather than the connect host - that's the address the
        // app connects to the server as, not necessarily what the server resolves
        // the connecting client back to for grant-matching (e.g. a loopback TCP
        // connection can resolve to 'localhost' regardless of the host string used
        // to reach it).
        mysqli_query($admin_connection, '
CREATE USER IF NOT EXISTS \'' . $runtime_username . '\'@\'%\'
    IDENTIFIED BY \'' . mysqli_real_escape_string($admin_connection, $runtime_password) . '\'
');

        // CREATE USER IF NOT EXISTS is a no-op when the account already exists
        // (a reinstall), so its password would silently stay the old one while
        // a fresh one gets written to .env - leaving the site unable to connect.
        // ALTER USER forces the password to match what we're about to store.
        mysqli_query($admin_connection, '
ALTER USER \'' . $runtime_username . '\'@\'%\'
    IDENTIFIED BY \'' . mysqli_real_escape_string($admin_connection, $runtime_password) . '\'
');
        mysqli_query($admin_connection, '
GRANT SELECT, INSERT, UPDATE, DELETE
    ON `' . $db_database . '`.*
    TO \'' . $runtime_username . '\'@\'%\'
');

        SchemaInstaller::createTables($admin_connection, SchemaInstaller::missingTables($admin_connection));
        SchemaInstaller::runMaintenance($admin_connection);

        // Record the code version this database now matches - init.php refuses
        // to serve a mismatched pair, so a fresh install must start in agreement.
        $initial_settings['appVersion'] = self::codeVersion();

        // Seed any initial settings (e.g. Turnstile keys entered in setup) now
        // that the Settings table exists. The admin connection runs this - the
        // runtime account and the Database::connection() singleton aren't set up
        // yet at install time.
        foreach ($initial_settings as $setting_name => $setting_value) {
            $stmt = mysqli_prepare($admin_connection, '
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
');
            mysqli_stmt_bind_param($stmt, 'ss', $setting_name, $setting_value);
            mysqli_stmt_execute($stmt);
        }

        return ['username' => $runtime_username, 'password' => $runtime_password];
    }

    /**
     * The codebase's version, hard-coded in src/init.php (the single source of
     * truth). Web requests have init.php loaded so the constant exists; the CLI
     * installer doesn't load init.php (it can't - init.php starts a session and
     * sends headers), so fall back to reading the constant out of the source.
     */
    public static function codeVersion(): string
    {
        if (defined('GLOMMER_VERSION')) {
            return GLOMMER_VERSION;
        }

        $init_source = (string) file_get_contents(__DIR__ . '/../init.php');

        if (!preg_match('/const GLOMMER_VERSION = \'([^\']+)\';/', $init_source, $match)) {
            throw new \RuntimeException('Could not find GLOMMER_VERSION in src/init.php.');
        }

        return $match[1];
    }

    /**
     * @param array<string, string> $env ENV key => value, in the order they
     *                                    should appear in the file
     */
    public static function envContents(array $env): string
    {
        $lines = [];

        foreach ($env as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines) . "\n";
    }
}
