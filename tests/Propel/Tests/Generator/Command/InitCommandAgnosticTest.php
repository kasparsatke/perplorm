<?php

declare(strict_types=1);

namespace Propel\Tests\Generator\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Common\Config\ConfigurationManager;
use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;
use function file_get_contents;

class InitCommandAgnosticTest extends InitCommandTestCase
{
    /**
     * @return array<array>
     */
    #[ComparesGeneratedFile()]
    public static function InitDialogDataProvider(): array
    {
        static::setupDirectory();
        $input = static::getInputsArray('pgsql');
        $dialogOutput = static::runInitCommand($input, true);
        $dialogOutput = static::cleanupCommandOutput($dialogOutput);

        $data = [
            [$dialogOutput, __DIR__ . "/init_command_code/init_dialog.txt"],
        ];

        return $data;
    }

    private static function cleanupCommandOutput(string $dialogOutput): string
    {
        // remove terminal color code (not available on gibhub)
        $dialogOutput = preg_replace('/ > \w(\x1b\\[[0-9;]*[a-zA-Z]).*\n/m', " > ", $dialogOutput);

        // collapse lines filled with blanks, the number of blanks differs between Symfony min/max version up to but not including v8
        $dialogOutput = preg_replace('/^ +$/m', "", $dialogOutput);

        return $dialogOutput;
    }

    /**
     * @return void
     */
    #[DataProvider('InitDialogDataProvider')]
    public function testInitDialogText(string $content, string $filename)
    {
        $this->assertStringEqualsFile($filename, static::cleanupCommandOutput($content), CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    /**
     * @return array<array>
     */
    #[ComparesGeneratedFile()]
    public static function InitFileDataProvider(): array
    {
        static::setupDirectory();
        $data = [];
        $formats = ['ini', 'json', 'php', 'xml', 'yml'];
        $fileSpecs = ['', '.dist'];
        $input = static::getInputsArray('pgsql');
        $input['dbname'] = 'le db';
        $input['user'] = 'le user';
        $input['password'] = 'le password';

        foreach ($formats as $format) {
            $input['fileFormat'] = $format;
            static::runInitCommand($input, true);
            foreach ($fileSpecs as $spec) {
                $filename = static::getDir() . "/db/perpl{$spec}.{$format}";
                $content = file_get_contents($filename);
                $data[] = [$content, __DIR__ . "/init_command_code/$format{$spec}_conf.txt"];
            }
        }

        return $data;
    }

    /**
     * @return void
     */
    #[DataProvider('InitFileDataProvider')]
    public function testConfigFileCode(string $content, string $codeReferenceFileName)
    {
        $this->assertStringEqualsFile($codeReferenceFileName, $content, CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    public static function ConfigFileExtensionDataProvider(): array
    {
        copy(__DIR__ . '/util/default-config.yml', static::$dir . '/db/default-config.yml');
        $referenceConfig = (new ConfigurationManager(static::$dir . '/db/default-config.yml'))->getConfig();
        ;

        return [
            ['ini', $referenceConfig],
            ['json', $referenceConfig],
            ['php', $referenceConfig],
            ['xml', $referenceConfig],
            ['yml', $referenceConfig],
        ];
    }

    #[DataProvider('ConfigFileExtensionDataProvider')]
    public function testConfigFileConversion(string $format, array $expectedConfig): void
    {
        $input = static::getInputsArray('pgsql');
        $input['fileFormat'] = $format;
        $input['dbname'] = 'le db';
        $input['user'] = 'le user';
        $input['password'] = 'le password';

        static::runInitCommand($input, false);

        $this->assertFileExists(static::$dir . '/db/generated-conf/config.php', 'Configuration php file created.');
        $config = (new ConfigurationManager(static::$dir . '/db'))->getConfig();
        $this->assertEquals($expectedConfig, $config);
    }

    /**
     * @return void
     */
    public function testExecute()
    {
        $userInputs = $this->getInputsArray('sqlite');
        $userInputs['doReverseEngineer'] = 'yes';
        $userInputs['perplDir'] = 'config';
        $output = $this->runInitCommand($userInputs, false);

        $this->assertStringContainsString('
Write data to config? [yes]: 
 wrote config/perpl.yml
 wrote config/perpl.dist.yml
 wrote config/schema/schema.xml

Creating PHP initialization script (config:convert)
Successfully wrote PHP configuration in file /tmp/test_perpl_init/./config/generated-conf/config.php.

Creating SQL DDL script from schema.xml (sql:build)

Building model classes (model:build)', $output);

        $this->assertFileExists(static::$dir . '/config/schema/schema.xml', 'Example schema file created.');
        $emptySchema = '<?xml version="1.0" encoding="utf-8"?>
<database name="default" defaultIdMethod="native" namespace="\Init\Command\Namespace" defaultPhpNamingMethod="underscore"/>';
        $this->assertStringEqualsFile(static::$dir . '/config/schema/schema.xml', $emptySchema);

        $this->assertFileExists(static::$dir . '/config/perpl.yml', 'Should create configuration file.');
        $this->assertFileExists(static::$dir . '/config/perpl.dist.yml', 'Should create dist configuration file.');
        $this->assertFileExists(static::$dir . '/src', 'Model directory created.');
        $this->assertFileExists(static::$dir . '/config/generated-conf/config.php', 'Configuration php file created.');
        $this->assertFileExists(static::$dir . '/config/generated-sql/default.sql', 'Sql file from example schema created.');
    }

    /**
     * @return void
     */
    public function testExecuteAborted()
    {
        $userInputs = $this->getInputsArray('pgsql');
        $userInputs['confirm'] = 'no';

        $output = static::runInitCommand($userInputs);
        $this->assertStringContainsString('Process aborted', $output);
    }
}
