<?php

declare(strict_types=1);

class DB
{
    private static ?\mysqli $connection = null;

    public static function connection(): \mysqli
    {
        if (self::$connection === null) {
            self::$connection = mysqli_connect(
                Config::get('host'),
                Config::get('username'),
                Config::get('password'),
                Config::get('database'),
                Config::get('port')
            );

            mysqli_set_charset(self::$connection, 'utf8mb4');

            // PHP's own clock (date.timezone) is UTC - without this, MySQL's
            // NOW()/CURRENT_TIMESTAMP() run on the server's SYSTEM timezone
            // instead, which silently disagrees with PHP by however many
            // hours the server happens to be offset from UTC. Pinning the
            // session here makes MySQL's clock the same clock PHP uses
            // everywhere else, rather than needing every table/query to
            // account for a possible mismatch.
            $time_zone = '+00:00';

            self::run('
SET `time_zone` = ?
', 's', $time_zone);
        }

        return self::$connection;
    }

    public static function prepare(string $sql): \mysqli_stmt
    {
        return mysqli_prepare(self::connection(), $sql);
    }

    public static function bind(\mysqli_stmt $stmt, string $types, mixed ...$params): void
    {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    public static function execute(\mysqli_stmt $stmt): void
    {
        mysqli_stmt_execute($stmt);
    }

    public static function run(string $sql, ?string $types = null, mixed ...$params): \mysqli_stmt
    {
        $stmt = self::prepare($sql);

        if ($types !== null) {
            self::bind($stmt, $types, ...$params);
        }

        self::execute($stmt);

        return $stmt;
    }

    public static function row(string $sql, string $class, ?string $types = null, mixed ...$params): ?object
    {
        $result = mysqli_stmt_get_result(self::run($sql, $types, ...$params));
        $row = mysqli_fetch_object($result, $class);

        return $row === false ? null : $row;
    }

    public static function rows(string $sql, string $class, ?string $types = null, mixed ...$params): array
    {
        $result = mysqli_stmt_get_result(self::run($sql, $types, ...$params));
        $rows = [];

        while (($row = mysqli_fetch_object($result, $class)) !== null && $row !== false) {
            $rows[] = $row;
        }

        return $rows;
    }
}
