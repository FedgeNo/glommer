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

    /**
     * The non-CREATE-TABLE statements in schema.sql - idempotent maintenance
     * the installer runs after tables are ensured, on every install and
     * upgrade (currently just the Users.friendCount recompute). Kept in
     * schema.sql so the whole schema, data-maintenance included, has one
     * source of truth. Same one-statement-per-`;` assumption as the CREATE
     * TABLE parsing (no semicolons inside a statement).
     *
     * @return string[]
     */
    public static function maintenanceStatements(): array
    {
        $schema_path = __DIR__ . '/../../schema.sql';

        if (!is_file($schema_path)) {
            throw new \RuntimeException('schema.sql not found at ' . $schema_path . '.');
        }

        $schema_sql = (string) file_get_contents($schema_path);

        // Remove the CREATE TABLE blocks (created separately).
        $without_tables = (string) preg_replace('/CREATE TABLE `\w+` \([^;]+;/s', '', $schema_sql);

        // Strip full-line `--` comments BEFORE splitting on `;` - a comment can
        // contain a semicolon (the header does), which would otherwise split a
        // comment into a bogus statement.
        $code_lines = [];

        foreach (explode("\n", $without_tables) as $line) {
            if (!str_starts_with(trim($line), '--')) {
                $code_lines[] = $line;
            }
        }

        $statements = [];

        foreach (explode(';', implode("\n", $code_lines)) as $chunk) {
            $statement = trim($chunk);

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    public static function runMaintenance(\mysqli $connection): void
    {
        foreach (self::maintenanceStatements() as $statement) {
            mysqli_query($connection, $statement);
        }
    }

    /**
     * Schema drift on tables that already exist: columns, indexes, and
     * foreign keys that schema.sql defines but the live table lacks - the
     * situation an existing install lands in after upgrading to a version
     * whose schema.sql gained something. Compares by name only (a changed
     * definition under a kept name is out of scope) and returns ready-to-run
     * ALTER statements keyed by a human-readable label.
     *
     * @return array<string, array<string, string>> table name => [label => ALTER statement]
     */
    public static function missingDefinitions(\mysqli $connection): array
    {
        $missing = [];

        foreach (self::schemaTableBodies() as $table => $body) {
            if (!self::tableExists($connection, $table)) {
                continue;
            }

            $existing_columns = self::existingColumns($connection, $table);
            $existing_indexes = self::existingIndexes($connection, $table);
            $existing_constraints = self::existingConstraints($connection, $table);
            $previous_column = null;

            foreach (self::parseBodyLines($body) as $line) {
                if (preg_match('/^`(\w+)`/', $line, $column_match)) {
                    if (!in_array($column_match[1], $existing_columns, true)) {
                        $position = $previous_column === null ? 'FIRST' : 'AFTER `' . $previous_column . '`';
                        $missing[$table]['column ' . $column_match[1]] = 'ALTER TABLE `' . $table . '` ADD COLUMN ' . $line . ' ' . $position;
                    }

                    $previous_column = $column_match[1];
                } elseif (preg_match('/^(UNIQUE KEY|KEY) `(\w+)`/', $line, $index_match)) {
                    if (!in_array($index_match[2], $existing_indexes, true)) {
                        $missing[$table]['index ' . $index_match[2]] = 'ALTER TABLE `' . $table . '` ADD ' . $line;
                    }
                } elseif (preg_match('/^CONSTRAINT `(\w+)`/', $line, $constraint_match)) {
                    if (!in_array($constraint_match[1], $existing_constraints, true)) {
                        $missing[$table]['foreign key ' . $constraint_match[1]] = 'ALTER TABLE `' . $table . '` ADD ' . $line;
                    }
                }
            }
        }

        return $missing;
    }

    /**
     * @return array<string, string> table name => the body between CREATE TABLE's parentheses
     */
    private static function schemaTableBodies(): array
    {
        $schema_path = __DIR__ . '/../../schema.sql';

        if (!is_file($schema_path)) {
            throw new \RuntimeException('schema.sql not found at ' . $schema_path . '.');
        }

        $schema_sql = (string) file_get_contents($schema_path);

        preg_match_all('/CREATE TABLE `(\w+)` \((.+?)\) ENGINE[^;]*;/s', $schema_sql, $matches, PREG_SET_ORDER);

        $bodies = [];

        foreach ($matches as $match) {
            $bodies[$match[1]] = $match[2];
        }

        return $bodies;
    }

    /**
     * @return string[] the body's definition lines, trimmed, without trailing commas
     */
    private static function parseBodyLines(string $body): array
    {
        $lines = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim(rtrim(trim($line), ','));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function tableExists(\mysqli $connection, string $table): bool
    {
        $stmt = mysqli_prepare($connection, '
SELECT 1
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * @return string[]
     */
    private static function existingColumns(\mysqli $connection, string $table): array
    {
        $stmt = mysqli_prepare($connection, '
SELECT `COLUMN_NAME`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'COLUMN_NAME');
    }

    /**
     * @return string[]
     */
    private static function existingIndexes(\mysqli $connection, string $table): array
    {
        $stmt = mysqli_prepare($connection, '
SELECT DISTINCT `INDEX_NAME`
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'INDEX_NAME');
    }

    /**
     * @return string[]
     */
    private static function existingConstraints(\mysqli $connection, string $table): array
    {
        $foreign_key_type = 'FOREIGN KEY';

        $stmt = mysqli_prepare($connection, '
SELECT `CONSTRAINT_NAME`
    FROM `information_schema`.`TABLE_CONSTRAINTS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `CONSTRAINT_TYPE` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $foreign_key_type);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'CONSTRAINT_NAME');
    }
}
