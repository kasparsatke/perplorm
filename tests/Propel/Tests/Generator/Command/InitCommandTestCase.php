<?php

declare(strict_types = 1);

namespace Propel\Tests\Generator\Command;

use Propel\Generator\Command\ConfigConvertCommand;
use Propel\Generator\Command\DatabaseReverseCommand;
use Propel\Generator\Command\InitCommand;
use Propel\Generator\Command\ModelBuildCommand;
use Propel\Generator\Command\SqlBuildCommand;
use Propel\Runtime\Perpl;
use Propel\Tests\TestCaseFixtures;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use function chdir;
use function getcwd;
use function sys_get_temp_dir;

abstract class InitCommandTestCase extends TestCaseFixtures
{
    /**
     * @var string|null
     */
    protected static $dir;

    protected static function getDir(): string
    {
        return sys_get_temp_dir() . '/test_perpl_init';
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        static::setupDirectory();

        chdir(static::$dir);
    }

    public static function setupDirectory()
    {
        if (static::$dir === null) {
            static::$dir = static::getDir();
        }

        $filesystem = new Filesystem();
        if ($filesystem->exists(static::$dir)) {
            $filesystem->remove(static::$dir);
        }
        $filesystem->mkdir(static::$dir);
    }

    protected static function runInitCommand(array $userInputs, bool $mockGeneratorStep = false): string
    {
        $app = new Application('Perpl', Perpl::VERSION);

        $subcommands = !$mockGeneratorStep
            ? [new ModelBuildCommand(), new SqlBuildCommand(), new ConfigConvertCommand(), new DatabaseReverseCommand()]
            : [(new class extends Command {
                public function execute(InputInterface $input, OutputInterface $output): int
                {
                    return 0;
                }
            })->setName('mock-command')->setAliases(['model:build', 'sql:build', 'config:convert'])];

        $app->addCommands([
            new InitCommand(),
            ...$subcommands,
        ]);

        $command = $app->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($userInputs);

        $home = getcwd();
        chdir($userInputs['projectRootPath']);
        $commandTester->execute(input: ['command' => $command->getName()]);

        chdir($home);

        return $commandTester->getDisplay();
    }

    /**
     * Gets the user input responses to the prompts during init command.
     *
     * @param string $lastAnswer
     *
     * @return array
     */
    protected static function getInputsArray(string $vendor): array
    {
        $keyedInput = [
            'fileFormat' => 'yml',

            'dbVendor' => $vendor,
            'host' => '',
            'port' => '',
            'dbname' => '',
            'user' => '',
            'password' => '',
            'charset' => '',
            'ignore' => 'ignore',

            'projectRootPath' => static::getDir(),
            'perplDir' => 'db',
            'phpDir' => 'src',
            'namespace' => 'Init\\Command\\Namespace',
            'doReverseEngineer' => 'no',

            'confirm' => 'yes',
        ];

        if ($vendor === 'sqlite') {
            unset($keyedInput['host']);
            unset($keyedInput['port']);
        }

        return $keyedInput;
    }
}
