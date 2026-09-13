<?php

declare(strict_types=1);

class SchemaMigrationDetectionTest extends DatabaseTestCase
{
    public function testTheCurrentFreshSchemaHasNoPendingIndexOrColumnMigrations(): void
    {
        $this -> assertSame([], SchemaInstaller::neededIndexMigrations());
    }
}
