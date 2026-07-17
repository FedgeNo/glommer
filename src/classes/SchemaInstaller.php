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
     * Whether the database has NONE of the app's tables yet - a genuinely fresh
     * install, which should get the current schema created directly rather than
     * run through the incremental upgrade steps (drift/type migrations/backfills).
     * An empty-but-installed database (its tables exist, just no rows) is NOT
     * fresh and takes the normal upgrade path - the code updates daily, so an
     * install created yesterday can need upgrading today even with no data.
     */
    public static function isFreshInstall(\mysqli $connection): bool
    {
        $existing_result = mysqli_query($connection, '
SELECT `TABLE_NAME`
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
');
        $existing = array_column(mysqli_fetch_all($existing_result, MYSQLI_ASSOC), 'TABLE_NAME');

        foreach (array_keys(self::schemaTableBodies()) as $table) {
            if (in_array($table, $existing, true)) {
                return false;
            }
        }

        return true;
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
     * The non-CREATE-TABLE, non-ALTER-TABLE statements in schema.sql -
     * idempotent DML maintenance the installer runs after tables are ensured,
     * on every install and upgrade (currently just the Users.friendCount
     * recompute). Kept in schema.sql so the whole schema, data-maintenance
     * included, has one source of truth. Same one-statement-per-`;`
     * assumption as the CREATE TABLE parsing (no semicolons inside a
     * statement). ALTER TABLE statements are excluded here - they're DDL
     * (see indexMigrationStatements()) and need admin privileges the runtime
     * account this runs on deliberately doesn't have.
     *
     * @return string[]
     */
    public static function maintenanceStatements(): array
    {
        return array_values(array_filter(
            self::nonTableStatements(),
            static fn (string $statement): bool => !str_starts_with($statement, 'ALTER TABLE')
        ));
    }

    public static function runMaintenance(\mysqli $connection): void
    {
        foreach (self::maintenanceStatements() as $statement) {
            mysqli_query($connection, $statement);
        }
    }

    /**
     * The one-time column renames that brought in the shared-vocabulary schema
     * (username -> slug, displayName -> title plus a new description bio; the
     * hashtag tag -> slug + new title; the entity/type columns). These can't go
     * through the drift/index-migration machinery - that only ever ADDs or
     * MODIFYs, never CHANGEs a column's name, so it would see the new names as
     * missing columns and add them empty alongside the untouched old ones.
     *
     * Every statement is individually guarded (IF EXISTS / IF NOT EXISTS), so
     * it's safe against a very old database missing some of these tables/columns
     * entirely (createTables will have just created any missing table at the
     * NEW schema, whose old column names then simply aren't there to rename) and
     * safe to re-run after a partial failure. Ordered so each new column is
     * backfilled before a unique key is put on it (an empty slug across every
     * row would collide). Run gated on the app version, after createTables and
     * before drift detection.
     *
     * @return string[]
     */
    public static function renameMigrationStatements(): array
    {
        return [
            'ALTER TABLE `Users` CHANGE COLUMN IF EXISTS `username` `slug` varchar(50) NOT NULL',
            'ALTER TABLE `Users` RENAME INDEX IF EXISTS `username` TO `slug`',
            'ALTER TABLE `Users` CHANGE COLUMN IF EXISTS `displayName` `title` varchar(100) DEFAULT NULL',
            'ALTER TABLE `Users` ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `title`',
            'ALTER TABLE `Hashtags` CHANGE COLUMN IF EXISTS `tag` `slug` varchar(64) NOT NULL',
            'ALTER TABLE `Hashtags` RENAME INDEX IF EXISTS `tag` TO `slug`',
            'ALTER TABLE `Hashtags` ADD COLUMN IF NOT EXISTS `title` varchar(64) NOT NULL DEFAULT \'\' AFTER `slug`',
            'UPDATE `Hashtags` SET `title` = `slug` WHERE `title` = \'\'',
            'ALTER TABLE `FeedItems` CHANGE COLUMN IF EXISTS `itemType` `type` varchar(50) NOT NULL',
            'ALTER TABLE `Reports` CHANGE COLUMN IF EXISTS `targetType` `type` varchar(16) NOT NULL',
            'ALTER TABLE `TrendingEntities` CHANGE COLUMN IF EXISTS `entityType` `type` varchar(16) NOT NULL',
            'ALTER TABLE `TrendingEntities` CHANGE COLUMN IF EXISTS `entityValue` `title` varchar(255) NOT NULL',
            'ALTER TABLE `TrendingEntities` ADD COLUMN IF NOT EXISTS `slug` varchar(255) NOT NULL DEFAULT \'\' AFTER `type`',
            'UPDATE `TrendingEntities` SET `slug` = LOWER(`title`) WHERE `slug` = \'\'',
            'ALTER TABLE `TrendingEntities` DROP INDEX IF EXISTS `entityType_entityValue`',
            'ALTER TABLE `TrendingEntities` ADD UNIQUE INDEX IF NOT EXISTS `type_slug` (`type`, `slug`)',
            'ALTER TABLE `BannedTrendingEntities` CHANGE COLUMN IF EXISTS `entityType` `type` varchar(16) NOT NULL',
            'ALTER TABLE `BannedTrendingEntities` CHANGE COLUMN IF EXISTS `entityValue` `title` varchar(255) NOT NULL',
            'ALTER TABLE `BannedTrendingEntities` ADD COLUMN IF NOT EXISTS `slug` varchar(255) NOT NULL DEFAULT \'\' AFTER `type`',
            'UPDATE `BannedTrendingEntities` SET `slug` = LOWER(`title`) WHERE `slug` = \'\'',
            'ALTER TABLE `BannedTrendingEntities` DROP PRIMARY KEY, ADD PRIMARY KEY (`type`, `slug`)',
            'ALTER TABLE `ModerationActions` CHANGE COLUMN IF EXISTS `targetType` `type` varchar(16) DEFAULT NULL',
            // The three columns added above needed a DEFAULT '' so existing rows
            // had a value before being backfilled; drop it now they're filled, so
            // an upgraded database matches a fresh one (whose schema.sql CREATE
            // declares these plainly NOT NULL). Drift detection compares column
            // existence, not defaults, so it wouldn't reconcile this otherwise.
            'ALTER TABLE `Hashtags` MODIFY COLUMN IF EXISTS `title` varchar(64) NOT NULL',
            'ALTER TABLE `TrendingEntities` MODIFY COLUMN IF EXISTS `slug` varchar(255) NOT NULL',
            'ALTER TABLE `BannedTrendingEntities` MODIFY COLUMN IF EXISTS `slug` varchar(255) NOT NULL',
        ];
    }

    /**
     * Runs renameMigrationStatements(). The caller gates this on the app
     * version (so it only runs while upgrading through the rename), but every
     * statement is also individually idempotent, so a re-run after a partial
     * failure is harmless. Must run BEFORE any drift check (missingDefinitions),
     * or the new names look like missing columns.
     */
    public static function applyRenameMigrations(\mysqli $admin_connection): void
    {
        foreach (self::renameMigrationStatements() as $statement) {
            mysqli_query($admin_connection, $statement);
        }
    }

    /**
     * The idempotent index migrations in schema.sql (ALTER TABLE ... ADD/DROP
     * INDEX IF NOT EXISTS/IF EXISTS, and MODIFY COLUMN column-type fixes) -
     * DDL, so unlike maintenanceStatements() these need admin privileges.
     * Returns only the ones actually still needed against $connection (an ADD
     * whose index already exists, a DROP whose index is already gone, or a
     * MODIFY whose column already has the target type, is left out), so
     * callers only have to reach for admin credentials when there's genuinely
     * DDL work pending - the same "admin creds only when there's real work"
     * principle missingDefinitions() already follows for column/index/FK
     * drift.
     *
     * @return string[]
     */
    public static function neededIndexMigrations(\mysqli $connection): array
    {
        $needed = [];
        $existing_by_table = [];
        $statements = self::indexMigrationStatements();
        $count = count($statements);

        for ($i = 0; $i < $count; $i++) {
            $statement = $statements[$i];

            if (preg_match('/^ALTER TABLE `(\w+)` MODIFY COLUMN `(\w+)` (\S+(?: unsigned)?)(?: NOT NULL)?(?: DEFAULT (\S+))?/', $statement, $column_match)) {
                [, $table, $column, $target_type] = $column_match;
                // Only present for a statement that names one (e.g. a bare
                // type change like the int(11)->unsigned migrations above
                // doesn't) - some column changes are a default-value fix
                // with the type unchanged (e.g. Users.verified), which the
                // type-only comparison alone would never flag as needed.
                $target_default = $column_match[4] ?? null;
                $current_type = self::columnType($connection, $table, $column);

                if ($current_type === null) {
                    continue;
                }

                $needs_migration = strcasecmp($current_type, $target_type) !== 0;

                if (!$needs_migration && $target_default !== null) {
                    $current_default = self::columnDefault($connection, $table, $column);
                    $needs_migration = $current_default === null || strcasecmp(trim($current_default, '\''), trim($target_default, '\'')) !== 0;
                }

                if ($needs_migration) {
                    $needed[] = $statement;
                }

                continue;
            }

            // An FK rule change: a DROP FOREIGN KEY immediately followed by
            // an ADD CONSTRAINT of the same name - two separate statements,
            // not one combined DROP+ADD (MariaDB errors re-adding the same
            // constraint name within the ALTER TABLE it was just dropped
            // in). Detected and applied as a pair.
            if (
                preg_match('/^ALTER TABLE `(\w+)` DROP FOREIGN KEY `(\w+)`$/', $statement, $drop_match)
                && isset($statements[$i + 1])
                && preg_match(
                    '/^ALTER TABLE `' . preg_quote($drop_match[1], '/') . '` ADD CONSTRAINT `' . preg_quote($drop_match[2], '/') . '` FOREIGN KEY .+ ON DELETE (\w+)$/',
                    $statements[$i + 1],
                    $add_match
                )
            ) {
                [, $table, $constraint] = $drop_match;
                $target_rule = $add_match[1];
                $current_rule = self::foreignKeyDeleteRule($connection, $table, $constraint);

                if ($current_rule !== null && strcasecmp($current_rule, $target_rule) !== 0) {
                    $needed[] = $statement;
                    $needed[] = $statements[$i + 1];
                }

                $i++; // the paired ADD statement is already accounted for

                continue;
            }

            if (!preg_match('/^ALTER TABLE `(\w+)` (ADD|DROP) INDEX IF (?:NOT )?EXISTS `(\w+)`/', $statement, $match)) {
                continue;
            }

            [, $table, $action, $index] = $match;

            if (!isset($existing_by_table[$table])) {
                $existing_by_table[$table] = self::existingIndexes($connection, $table);
            }

            $index_exists = in_array($index, $existing_by_table[$table], true);

            if (($action === 'ADD' && !$index_exists) || ($action === 'DROP' && $index_exists)) {
                $needed[] = $statement;
            }
        }

        return $needed;
    }

    private static function columnType(\mysqli $connection, string $table, string $column): ?string
    {
        $stmt = mysqli_prepare($connection, '
SELECT `COLUMN_TYPE`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null ? (string) $row['COLUMN_TYPE'] : null;
    }

    private static function columnDefault(\mysqli $connection, string $table, string $column): ?string
    {
        $stmt = mysqli_prepare($connection, '
SELECT `COLUMN_DEFAULT`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null && $row['COLUMN_DEFAULT'] !== null ? (string) $row['COLUMN_DEFAULT'] : null;
    }

    private static function foreignKeyDeleteRule(\mysqli $connection, string $table, string $constraint): ?string
    {
        $stmt = mysqli_prepare($connection, '
SELECT `DELETE_RULE`
    FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
    WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `CONSTRAINT_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $constraint);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null ? (string) $row['DELETE_RULE'] : null;
    }

    /**
     * @return string[]
     */
    private static function indexMigrationStatements(): array
    {
        return array_values(array_filter(
            self::nonTableStatements(),
            static fn (string $statement): bool => str_starts_with($statement, 'ALTER TABLE')
        ));
    }

    /**
     * Every statement in schema.sql outside the CREATE TABLE blocks - the
     * shared parse behind maintenanceStatements() (DML) and
     * indexMigrationStatements() (DDL), split apart by their differing
     * privilege requirements.
     *
     * @return string[]
     */
    private static function nonTableStatements(): array
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
                } elseif (preg_match('/^(UNIQUE KEY|FULLTEXT KEY|KEY) `(\w+)`/', $line, $index_match)) {
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
