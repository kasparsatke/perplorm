<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\MigrationCreateCommand;

/**
 * @group database
 */
class MigrationCreateCommandTest extends MigrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->deleteMigrationFiles();
    }

    /**
     * @return void
     */
    public function testCreateCommandCreatesFiles(): void
    {
        $this->runCommandAndAssertSuccess('migration:create', new MigrationCreateCommand(), ['--schema-dir' => self::BASE_SCHEMA_DIR]);
        $this->assertGeneratedFileContainsCreateTableStatement(false, 'PropelMigration_*.php');
    }

    /**
     * @return void
     */
    public function testCreateCommandCreatesSuffixedFiles(): void
    {
        $suffix = 'an_explanatory_filename_suffix';
        $this->runCommandAndAssertSuccess('migration:create', new MigrationCreateCommand(), ['--schema-dir' => self::BASE_SCHEMA_DIR, '--suffix' => $suffix]);
        $this->assertGeneratedFileContainsCreateTableStatement(false, "PropelMigration_*_$suffix.php");
    }
}
