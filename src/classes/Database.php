<?php

declare(strict_types=1);

class Database
{
    private static ?\mysqli $connection = null;

    public static function connection(): \mysqli
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../config.php';

            self::$connection = mysqli_connect(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port']
            );

            mysqli_set_charset(self::$connection, 'utf8mb4');

            // PHP's own clock (date.timezone) is UTC - without this, MySQL's
            // NOW()/CURRENT_TIMESTAMP() run on the server's SYSTEM timezone
            // instead, which silently disagrees with PHP by however many
            // hours the server happens to be offset from UTC. Pinning the
            // session here makes MySQL's clock the same clock PHP uses
            // everywhere else, rather than needing every table/query to
            // account for a possible mismatch.
            mysqli_query(self::$connection, "SET time_zone = '+00:00'");
        }

        return self::$connection;
    }
}
