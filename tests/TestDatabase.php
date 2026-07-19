<?php

declare(strict_types=1);

/**
 * Builds and tears down the throwaway database DatabaseTestCase-derived
 * tests run against. Needs the DB server's root account - only reachable via
 * MariaDB/MySQL's unix_socket auth plugin, which authenticates the OS root
 * user with no password over the local socket (mysqli_connect('localhost',
 * 'root', '') - the same trick bin/install.php's admin_connection() uses) -
 * so this only works when bin/run-tests.php itself runs as root (`sudo php
 * bin/run-tests.php`).
 *
 * Rather than replaying schema.sql, every table's structure is copied
 * straight off the real database with CREATE TABLE ... LIKE, so a test run
 * exercises whatever the live schema actually is, drift and all, not just
 * what's checked into git. CREATE TABLE ... LIKE drops foreign key
 * constraints (confirmed empirically against this schema), which
 * conveniently sidesteps the cross-database FK-reference problem a straight
 * copy would otherwise hit - the app's own code is still what enforces
 * referential integrity in these tests, same as for every other write in
 * this codebase.
 */
final class TestDatabase
{
    private static ?string $name = null;

    public static function setUp(): bool
    {
        $source = (string) Config::get('database');
        $test_db = $source . '_test';

        if (!self::isSafeIdentifier($source) || !self::isSafeIdentifier($test_db) || $test_db === $source) {
            fwrite(STDERR, "Refusing to set up a test database - unsafe or ambiguous database name ({$source}).\n");

            return false;
        }

        try {
            $root = mysqli_connect('localhost', 'root', '');
        } catch (\mysqli_sql_exception $exception) {
            fwrite(STDERR, "Could not connect to the database as root over the local socket - is this running as root, and does the DB server have unix_socket auth configured for root? ({$exception -> getMessage()})\n");

            return false;
        }

        try {
            mysqli_query($root, 'DROP DATABASE IF EXISTS `' . $test_db . '`');
            mysqli_query($root, 'CREATE DATABASE `' . $test_db . '`');

            $tables_result = mysqli_query($root, 'SHOW TABLES FROM `' . $source . '`');

            while ($row = mysqli_fetch_row($tables_result)) {
                $table = $row[0];

                if (!self::isSafeIdentifier($table)) {
                    throw new \RuntimeException('Unsafe table name encountered: ' . $table);
                }

                mysqli_query($root, 'CREATE TABLE `' . $test_db . '`.`' . $table . '` LIKE `' . $source . '`.`' . $table . '`');
            }
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Failed to build the test database: ' . $exception -> getMessage() . "\n");
            mysqli_query($root, 'DROP DATABASE IF EXISTS `' . $test_db . '`');
            mysqli_close($root);

            return false;
        }

        mysqli_close($root);

        self::$name = $test_db;

        // Points DB::connection() - the app's own singleton, used by every
        // model class exactly as in production - at the throwaway database
        // via the same root/socket path, rather than teaching it a second
        // connection mode just for tests.
        putenv('DB_HOST=localhost');
        putenv('DB_USERNAME=root');
        putenv('DB_PASSWORD=');
        putenv('DB_DATABASE=' . $test_db);
        Config::reload();

        return true;
    }

    public static function tearDown(): void
    {
        if (self::$name === null) {
            return;
        }

        $name = self::$name;
        self::$name = null;

        try {
            $root = mysqli_connect('localhost', 'root', '');
            mysqli_query($root, 'DROP DATABASE IF EXISTS `' . $name . '`');
            mysqli_close($root);
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Could not drop the test database `' . $name . '`: ' . $exception -> getMessage() . "\n");
        }
    }

    private static function isSafeIdentifier(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    }
}
