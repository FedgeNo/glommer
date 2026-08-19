<?php

declare(strict_types=1);

class SchemaInstallerTest extends TestCase
{
    public function testASemicolonInAColumnDefaultDoesNotSplitTheTable(): void
    {
        $schema = "CREATE TABLE `SemicolonDefaults` (\n"
            . "  `value` varchar(20) NOT NULL DEFAULT 'left;right',\n"
            . "  PRIMARY KEY (`value`)\n"
            . ") ENGINE=InnoDB;\n";

        $statements = SchemaInstaller::parseCreateTableStatements($schema);

        $this -> assertSame($schema, $statements['SemicolonDefaults'] . "\n");
    }
}
