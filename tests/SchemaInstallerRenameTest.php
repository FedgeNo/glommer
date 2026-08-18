<?php

declare(strict_types=1);

class SchemaInstallerRenameTest extends DatabaseTestCase
{
    public function testRenamesTrendingEntitiesWithoutLosingRows(): void
    {
        $connection = DB::connection();
        $slug = 'installer-rename-' . bin2hex(random_bytes(6));

        try {
            DB::run('
INSERT INTO `Entities` (`type`, `slug`, `title`, `score`, `postCount`, `userCount`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'sssdii', 'hashtag', $slug, 'Installer Rename', 1.0, 1, 1);

            mysqli_query($connection, 'RENAME TABLE `Entities` TO `TrendingEntities`');

            $pending = SchemaInstaller::pendingTableRenames($connection);

            $this -> assertSame(['TrendingEntities' => 'Entities'], $pending);

            SchemaInstaller::applyTableRenames($connection, $pending);

            $entity = DB::row('
SELECT *
    FROM `Entities`
    WHERE `slug` = ?
', Entity::class, 's', $slug);

            $this -> assertNotNull($entity);
            $this -> assertSame('Installer Rename', $entity -> title);
        } finally {
            $result = mysqli_query($connection, '
SELECT `TABLE_NAME`
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN (\'Entities\', \'TrendingEntities\')
');
            $tables = array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'TABLE_NAME');

            if (in_array('TrendingEntities', $tables, true) && !in_array('Entities', $tables, true)) {
                mysqli_query($connection, 'RENAME TABLE `TrendingEntities` TO `Entities`');
            }

            DB::run('DELETE FROM `Entities` WHERE `slug` = ?', 's', $slug);
        }
    }
}
