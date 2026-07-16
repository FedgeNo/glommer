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

            $stmt = mysqli_prepare(self::$connection, '
SET `time_zone` = ?
');
            mysqli_stmt_bind_param($stmt, 's', $time_zone);
            mysqli_stmt_execute($stmt);
        }

        return self::$connection;
    }
}
