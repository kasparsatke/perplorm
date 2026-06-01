<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\MigrationDiffCommand;
use Propel\Generator\Command\MigrationMigrateCommand;

/**
 * @group database
 */
class MigrationMigrateCommandTest extends MigrationTestCase
{
    /**
     * @return void
     */
    public function testPerformsUpMigration(): void
    {
        $this->runCommandAndAssertSuccess('migration:diff', new MigrationDiffCommand(), ['--schema-dir' => self::BASE_SCHEMA_DIR]);

        $outputString = $this->runCommandAndAssertSuccess(
            'migration:migrate', 
            new MigrationMigrateCommand(), 
            [], 
            self::MIGRATE_DOWN_AFTERWARDS
        );
        $this->assertStringContainsString('Migration complete.', $outputString);
    }

    /**
     * @return void
     */
    public function testMigrateToTheLastVersionIfTheGivenVersionIsNotExists(): void
    {
        $outputString = $this->runCommandAndAssertSuccess(
            'migration:migrate',
            new MigrationMigrateCommand(),
            [self::COMMAND_OPTION_MIGRATE_TO_VERSION => 0],
            self::MIGRATE_DOWN_AFTERWARDS,
        );

        $this->assertStringContainsString('Migration complete.', $outputString);
    }

    /**
     * @return void
     */
    public function testDoNothingIfGivenVersionIsTheLastAppliedVersion(): void
    {
        $this->setUpMigrateToVersion();

        $migrationVersions = $this->getMigrationVersions();
        $expectedVersion = $migrationVersions[array_key_last($migrationVersions)];

        $outputString = $this->runCommandAndAssertSuccess(
            'migration:migrate',
            new MigrationMigrateCommand(),
            [self::COMMAND_OPTION_MIGRATE_TO_VERSION => $expectedVersion],
        );

        $this->assertIsCurrentVersion($expectedVersion);
        $this->assertStringContainsString(
            sprintf('Already at version %s.', $expectedVersion),
            $outputString,
        );

        $this->tearDownMigrateToVersion($migrationVersions);
    }

    /**
     * @return void
     */
    public function testRollbackToTheGivenVersionIfItIsLowerThanTheCurrentVersion(): void
    {
        $this->setUpMigrateToVersion();

        $migrationVersions = $this->getMigrationVersions();
        $expectedVersion = $migrationVersions[array_key_first($migrationVersions)];

        $outputString = $this->runCommandAndAssertSuccess(
            'migration:migrate',
            new MigrationMigrateCommand(),
            [self::COMMAND_OPTION_MIGRATE_TO_VERSION => $expectedVersion],
        );

        $this->assertIsCurrentVersion($expectedVersion);
        $this->assertStringContainsString(
            sprintf('Successfully rollback to migration version %s.', $expectedVersion),
            $outputString,
        );

        $this->tearDownMigrateToVersion($migrationVersions);
    }

    /**
     * @return void
     */
    public function testMigrateToTheGivenVersionIfItIsHigherThanTheCurrentVersion(): void
    {
        $this->setUpMigrateToVersion();

        $migrationVersions = $this->getMigrationVersions();
        $this->migrateDown();

        $expectedVersion = $migrationVersions[array_key_last($migrationVersions)];

        $outputString = $this->runCommandAndAssertSuccess(
            'migration:migrate',
            new MigrationMigrateCommand(),
            [self::COMMAND_OPTION_MIGRATE_TO_VERSION => $expectedVersion],
        );

        $this->assertIsCurrentVersion($expectedVersion);
        $this->assertStringContainsString('Migration complete. No further migration to execute.', $outputString);

        $this->tearDownMigrateToVersion($migrationVersions);
    }
}
