<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Command\Helper\ConsoleHelper;
use Propel\Generator\Manager\InitManager\ConfigFileCreator;
use Propel\Generator\Manager\InitManager\ConnectionSetupDialog;
use Propel\Generator\Manager\InitManager\ProjectStructureSetupDialog;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_merge;
use function file_get_contents;
use function sprintf;
use function sys_get_temp_dir;
use function urlencode;

class InitCommand extends AbstractCommand
{
    /**
     * @param string|null $name
     */
    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('init')
            ->setDescription('Initializes a new project');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consoleHelper = new ConsoleHelper($input, $output);
        $this->getHelperSet()->set($consoleHelper);

        $consoleHelper->writeBlock('Perpl Initializer', 'bg=magenta;fg=white');
        $consoleHelper->writeSection("Configuration values (including database connection information) will be written to a configuration file. You will likely edit or add options to this file later.\n");

        $options = [];

        // setup configuration file
        $options['format'] = $this->inquireConfigFileExtension($consoleHelper);

        // setup and test database
        $connectionOptions = ConnectionSetupDialog::runSetup($consoleHelper);
        $options = array_merge($options, $connectionOptions);

        // setup dirs
        $projectPathVariables = ProjectStructureSetupDialog::runSetup($consoleHelper);
        $options = array_merge($options, $projectPathVariables);

        // reverse engineer schema

        $consoleHelper->writeSection('For an existing database, schema.xml can be created automatically with the <comment>database:reverse</comment> command.');
        $isReverseEngineerRequested = $consoleHelper->askConfirmation('Run database:reverse now to create schema.xml from exiting database?', false);
        if ($isReverseEngineerRequested) {
            $schema = $this->reverseEngineerSchema($consoleHelper, $options);
            if ($schema) {
                $options['schema'] = $schema;
            }
        }

        // confirm

        $consoleHelper->writeBlock('Summary');

        $consoleHelper->writeSummary([
            'Database management system' => $options['dbVendor'],
            'Charset' => $options['charset'],
            'Project root' => $projectPathVariables['projectRootPath'],
            'Perpl directory (__DIR__)' => $projectPathVariables['projectRootPath'] . '/' . $options['perplDir'],
            'Config file' => '__DIR__/perpl.' . $options['format'],
            'Schema file directory' => $options['schemaDir'],
            'Model file base directory' => $options['phpDir'],
            'PHP namespace of generated php models' => $options['namespace'],
        ]);

        $consoleHelper->writeln('');
        $doWrite = $consoleHelper->askConfirmation('Write data to config?', true);

        if (!$doWrite) {
            $consoleHelper->writeln('<error>Process aborted.</error>');

            return static::CODE_SUCCESS;
        }

        // finalize

        $consoleHelper->writeln('');

        ConfigFileCreator::create($output, $options);
        $this->buildSqlAndModelsAndConvertConfig($consoleHelper->getOutput(), $isReverseEngineerRequested);

        $consoleHelper->writeSection("Success!\n\nCall <info>require_once <path to ./generated-conf>/config.php></info> to register database connection and tables (typically where you load vendor/autoload.php) and you are ready to use Perpl.\n");

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Propel\Generator\Command\Helper\ConsoleHelper $consoleHelper
     *
     * @return string
     */
    protected function inquireConfigFileExtension(ConsoleHelper $consoleHelper): string
    {
        return $consoleHelper->select('Choose configuration file format:', ['yml', 'xml', 'json', 'ini', 'php'], 'json');
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     * @param bool $buildForSchema
     *
     * @return void
     */
    private function buildSqlAndModelsAndConvertConfig(?OutputInterface $output = null, bool $buildForSchema = true): void
    {
        $this->getApplication()->setAutoExit(false);

        $followupCommands = [
            'config:convert' => 'Creating PHP initialization script',
        ];

        if ($buildForSchema) {
            $followupCommands = array_merge($followupCommands, [
                'sql:build' => 'Creating SQL DDL script from schema.xml',
                'model:build' => 'Building model classes',
            ]);
        }

        foreach ($followupCommands as $command => $description) {
            $output->writeln("\n$description ($command)");
            $input = new ArrayInput([$command]);
            if ($this->getApplication()->run($input, $output) !== static::CODE_SUCCESS) {
                $output?->writeln("Failed to run `$command` command. Stopping automatic build. Please run command manually");
                exit(1);
            }
        }

        $this->getApplication()->setAutoExit(true);
    }

    /**
     * @param \Propel\Generator\Command\Helper\ConsoleHelper $consoleHelper
     * @param array $options
     *
     * @return string|null
     */
    private function reverseEngineerSchema(ConsoleHelper $consoleHelper, array $options): string|null
    {
        $outputDir = sys_get_temp_dir();
        $fullDsn = sprintf('%s;user=%s;password=%s', $options['dsn'], urlencode($options['user']), urlencode($options['password']));

        $input = [
            'connection' => $fullDsn,
            '--output-dir' => $outputDir,
        ];

        if (isset($options['namespace'])) {
            $input['--namespace'] = $options['namespace'];
        }

        $this->getApplication()->setAutoExit(false);
        $result = $this->runCommand('database:reverse', $input, $consoleHelper->getOutput());
        $this->getApplication()->setAutoExit(true);

        if ($result !== 0) {
            $consoleHelper->writeBlock('Failed to load database schema, using default - try again later with by calling database:reverse.', 'error');

            return null;
        }

        $schema = (string)file_get_contents("$outputDir/schema.xml");

        return $schema;
    }
}
