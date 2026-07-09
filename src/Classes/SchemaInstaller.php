<?php

declare(strict_types=1);

class SchemaInstaller
{
    /**
     * @return array<string, string> table name => its CREATE TABLE statement, for every table
     *                                in schema.sql not yet present in $connection's current database
     */
    public static function missingTables(\mysqli $connection): array
    {
        $schema_path = __DIR__ . '/../../schema.sql';

        if (!is_file($schema_path)) {
            throw new \RuntimeException('schema.sql not found at ' . $schema_path . '.');
        }

        $schema_sql = (string) file_get_contents($schema_path);

        // One block per CREATE TABLE statement (schema.sql is DDL only, comments and
        // blank lines between statements), so each table can be checked and created
        // independently rather than treating "some tables exist" as all-or-nothing.
        preg_match_all('/CREATE TABLE `(\w+)` \([^;]+;/s', $schema_sql, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            throw new \RuntimeException('Could not parse any CREATE TABLE statements out of schema.sql.');
        }

        $existing_tables_result = mysqli_query($connection, '
SELECT `TABLE_NAME`
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
');
        $existing_tables = array_column(mysqli_fetch_all($existing_tables_result, MYSQLI_ASSOC), 'TABLE_NAME');

        $missing_statements = [];

        foreach ($matches as $match) {
            if (!in_array($match[1], $existing_tables, true)) {
                $missing_statements[$match[1]] = $match[0];
            }
        }

        return $missing_statements;
    }

    /**
     * @param array<string, string> $statements table name => its CREATE TABLE statement
     */
    public static function createTables(\mysqli $admin_connection, array $statements): void
    {
        foreach ($statements as $statement) {
            mysqli_query($admin_connection, $statement);
        }
    }
}
