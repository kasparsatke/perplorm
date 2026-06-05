<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\MigrationStatusCommand;

/**
 * @group database
 */
class MigrationStatusCommandTest extends MigrationTestCase
{
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

        $migrationVersions = $this->lookupExistingMigrationTimestamps();

        $outputString = $this->runCommandAndAssertSuccess(
            'migration:status',
            new MigrationStatusCommand(),
            [self::COMMAND_OPTION_LAST_VERSION => true],
        );

        $this->tearDownMigrateToVersion($this->lookupExistingMigrationTimestamps());

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

        $this->tearDownMigrateToVersion($this->lookupExistingMigrationTimestamps());

        $this->assertStringContainsString('Checking Database Versions', $outputString);
    }
}
