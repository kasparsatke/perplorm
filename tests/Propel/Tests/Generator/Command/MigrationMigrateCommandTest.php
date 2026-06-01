<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\MigrationDownCommand;
use Propel\Generator\Command\MigrationMigrateCommand;
use Propel\Generator\Command\MigrationUpCommand;

/**
 * @group database
 */
class MigrationMigrateCommandTest extends MigrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $runner = new MigrationMigrateCommandTest('');
        $runner->deleteMigrationFiles();
    }

    /**
     * @return void
     */
    public function testPerformsUpMigration(): void
    {
        $this->runDiffAndAssertSuccess();
        $outputString = $this->runMigrateAndAssertSuccess([], self::MIGRATE_DOWN_AFTERWARDS);
        $this->assertStringContainsString('Migration complete.', $outputString);
    }

    /**
     * @return void
     */
    public function testMigrateToTheLastVersionIfTheGivenVersionIsNotExists(): void
    {
        $outputString = $this->runMigrateAndAssertSuccess(
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

        $migrationVersions = $this->lookupExistingMigrationTimestamps();
        $expectedVersion = $migrationVersions[array_key_last($migrationVersions)];

        $outputString = $this->runMigrateAndAssertSuccess(
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

        $migrationVersions = $this->lookupExistingMigrationTimestamps();
        $expectedVersion = $migrationVersions[array_key_first($migrationVersions)];

        $outputString = $this->runMigrateAndAssertSuccess(
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

        $migrationVersions = $this->lookupExistingMigrationTimestamps();
        $this->migrateDown();

        $expectedVersion = $migrationVersions[array_key_last($migrationVersions)];

        $outputString = $this->runMigrateAndAssertSuccess(
            [self::COMMAND_OPTION_MIGRATE_TO_VERSION => $expectedVersion],
        );

        $this->assertIsCurrentVersion($expectedVersion);
        $this->assertStringContainsString('Migration complete. No further migration to execute.', $outputString);

        $this->tearDownMigrateToVersion($migrationVersions);
    }

    /**
     * @return void
     */
    public function testManualUpAndDown(): void
    {
        $this->runDiffAndAssertSuccess();
        $outputString = $this->runCommandAndAssertSuccess('migration:up', new MigrationUpCommand());
        $this->assertStringContainsString('Migration complete.', $outputString);
    
        $outputString = $this->runCommandAndAssertSuccess('migration:down', new MigrationDownCommand());
        $this->assertStringContainsString('Reverse migration complete.', $outputString);
    }

    protected function runMigrateAndAssertSuccess(
        array $additionalArguments = [],
        bool $migrateDownAfterwards = false,
        bool $expectNonZeroExitCode = false
    ) {
        return $this->runCommandAndAssertSuccess(
            'migration:migrate', 
            new MigrationMigrateCommand(), 
            $additionalArguments, 
            $migrateDownAfterwards,
            $expectNonZeroExitCode
        );
    }
}
