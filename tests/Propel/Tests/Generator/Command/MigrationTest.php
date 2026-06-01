<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\MigrationCreateCommand;
use Propel\Generator\Command\MigrationDownCommand;
use Propel\Generator\Command\MigrationStatusCommand;
use Propel\Generator\Command\MigrationUpCommand;

/**
 * @group database
 */
class MigrationTest extends MigrationTestCase
{
    /**
     * @return void
     */
    public function testCreateCommandCreatesFiles(): void
    {
        $this->deleteMigrationFiles();
        $this->runCommandAndAssertSuccess('migration:create', new MigrationCreateCommand(), ['--schema-dir' => self::BASE_SCHEMA_DIR]);
        $this->assertGeneratedFileContainsCreateTableStatement(false, 'PropelMigration_*.php');
    }

    /**
     * @return void
     */
    public function testCreateCommandCreatesSuffixedFiles(): void
    {
        $this->deleteMigrationFiles();
        $suffix = 'an_explanatory_filename_suffix';
        $this->runCommandAndAssertSuccess('migration:create', new MigrationCreateCommand(), ['--schema-dir' => self::BASE_SCHEMA_DIR, '--suffix' => $suffix]);
        $this->assertGeneratedFileContainsCreateTableStatement(false, "PropelMigration_*_$suffix.php");
    }

    /**
     * @return void
     */
    public function testUpCommandPerformsUpMigration(): void
    {
        $outputString = $this->runCommandAndAssertSuccess('migration:up', new MigrationUpCommand(), [], self::MIGRATE_DOWN_AFTERWARDS);
        $this->assertStringContainsString('Migration complete.', $outputString);
    }

    /**
     * @return void
     */
    public function testDownCommandPerformsDownMigration(): void
    {
        $this->migrateUp();
        $outputString = $this->runCommandAndAssertSuccess('migration:down', new MigrationDownCommand());
        $this->assertStringContainsString('Reverse migration complete.', $outputString);
    }

    /**
     * @return void
     */
    public function testMigrationStatusCommandShouldReturnEmptyWhenOptionIsProvidedAndMigrationsWereNotExecuted(): void
    {
        $outputString = $this->runCommandAndAssertSuccess(
            'migration:status',
            new MigrationStatusCommand(),
            [self::COMMAND_OPTION_LAST_VERSION => true],
        );

        $this->assertEmpty(trim(str_replace('\n', '', $outputString)));
    }

    /**
     * @return void
     */
    public function testMigrationStatusCommandShouldReturnTheLastMigrationVersionWhenOptionIsProvided(): void
    {
        $this->setUpMigrateToVersion();

        $migrationVersions = $this->getMigrationVersions();

        $outputString = $this->runCommandAndAssertSuccess(
            'migration:status',
            new MigrationStatusCommand(),
            [self::COMMAND_OPTION_LAST_VERSION => true],
        );

        $this->tearDownMigrateToVersion($this->getMigrationVersions());

        $this->assertSame((string) array_pop($migrationVersions), trim(str_replace('\n', '', $outputString)));
    }

    /**
     * @return void
     */
    public function testMigrationStatusCommandShouldNotReturnTheLastMigrationVersionWhenOptionIsNotProvided(): void
    {
        $this->setUpMigrateToVersion();
        $this->migrateDown();

        $outputString = $this->runCommandAndAssertSuccess('migration:status', new MigrationStatusCommand());

        $this->tearDownMigrateToVersion($this->getMigrationVersions());

        $this->assertStringContainsString('Checking Database Versions', $outputString);
    }
}
