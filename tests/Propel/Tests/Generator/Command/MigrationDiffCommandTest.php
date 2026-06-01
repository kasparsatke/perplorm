<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Command\MigrationDiffCommand;

/**
 * @group database
 */
class MigrationDiffCommandTest extends MigrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->deleteMigrationFiles();
    }

    /**
     * @return void
     */
    public function testCreatesFiles(): void
    {
        $this->runDiffAndAssertSuccess();
        $this->assertGeneratedFileContainsCreateTableStatement(true, 'PropelMigration_*.php');
    }

    /**
     * @return void
     */
    public function testCreatesSuffixedFiles(): void
    {
        $suffix = 'an_explanatory_filename_suffix';
        $this->runDiffAndAssertSuccess(['--suffix' => $suffix]);
        $this->assertGeneratedFileContainsCreateTableStatement(true, "PropelMigration_*_$suffix.php");
    }

    /**
     * @return void
     */
    public function testErrorOnOpenMigration(): void
    {
        $this->runDiffAndAssertSuccess();
        $errorMessgage = $this->runDiffAndAssertSuccess([], false, true);
        $this->assertStringContainsString('Pending migrations have been found - you should either execute or delet', $errorMessgage); // NOTE: Respect console line limit 
    }

    public function testPrintAttribute(): void
    {
        $output = $this->runDiffAndAssertSuccess(['--print' => true]);

        $this->assertStringContainsString('SQL to migrate DB to schema.xml:', $output);
        $this->assertEmpty($this->getMigrationVersions(), 'should not have created migration files');
    }

    public static function PrintOutputDataProvider(): array
    {
        return [
            [
                ['leFirstDb' => 'le first SQL;', 'leSecondDb' => 'le second SQL;'],
                " - Database leFirstDb:\nle first SQL;\n\n - Database leSecondDb:\nle second SQL;"
            ],
            [['leOnlyDb' => 'le only SQL;'], " - Database leOnlyDb:\nle only SQL;"]
        ];
    }

    #[DataProvider('PrintOutputDataProvider')]
    public function testPrintMultipleMigrationsOutput(array $migrations, string $expectedOutput): void
    {
        $command = new MigrationDiffCommand();
        $output = $this->callMethod($command, 'migrationsToString', [$migrations, true]);

        $this->assertSame($expectedOutput, $output);
    }

    /**
     * @return void
     */
    public function testPrintRunsOnPendingMigrations(): void
    {
        $this->runDiffAndAssertSuccess(); // creates pending migration
        $output = $this->runDiffAndAssertSuccess(['--print' => true]);
        $this->assertStringContainsString('SQL to migrate DB to schema.xml:', $output);
    }
}
