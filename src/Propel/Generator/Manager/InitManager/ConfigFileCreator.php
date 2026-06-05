<?php

declare(strict_types = 1);

namespace Propel\Generator\Manager\InitManager;

use InvalidArgumentException;
use Propel\Common\Config\XmlToArrayConverter;
use Propel\Generator\Builder\Util\PropelTemplate;
use Propel\Runtime\Parser\YamlParser;
use Symfony\Component\Console\Output\OutputInterface;
use function addcslashes;
use function array_push;
use function dirname;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_array;
use function is_string;
use function json_encode;
use function mkdir;
use function print_r;
use function var_export;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class ConfigFileCreator
{
    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function __construct(protected OutputInterface $output)
    {
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array $options
     *
     * @return void
     */
    public static function create(OutputInterface $output, array $options): void
    {
        (new self($output))->writeFiles($options);
    }

    /**
     * @param array $options
     *
     * @return void
     */
    public function writeFiles(array $options): void
    {
        $fileExt = $options['format'];
        $configDirPath = $options['perplDir'];

        $configs = [
            '' => [$this, 'buildConfigLocalArray'],
            '.dist' => [$this, 'buildConfigDistArray'],
        ];
        foreach ($configs as $fileSpec => $builder) {
            $distArray = $builder($options);
            $content = $this->toFormat($fileExt, $distArray);
            $this->writeFile("$configDirPath/perpl{$fileSpec}.{$fileExt}", $content);
        }

        if (!isset($options['schema'])) {
            $options['schema'] = PropelTemplate::renderFile(__DIR__ . '/templates/bookstore.schema.xml.php', $options);
        }
        $schemaFileName = "$configDirPath/schema/schema.xml";
        $this->writeFile($schemaFileName, $options['schema']);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function buildConfigDistArray(array $options): array
    {
        return [
            'propel' => [
                'database' => [
                    'connections' => [
                        'default' => [
                            'adapter' => $options['dbVendor'],
                            'settings' => [
                                'charset' => $options['charset'],
                            ],
                        ],
                    ],
                ],
                'paths' => [
                    'projectDir' => $options['projectDir'],
                    'phpDir' => $options['phpDir'],
                    'schemaDir' => $options['schemaDir'],
                    'phpConfDir' => $options['phpConfDir'],
                    'sqlDir' => $options['sqlDir'],
                    'migrationDir' => $options['migrationDir'],
                ],
                'generator' => [
                    'declareStrictTypesInBuilders' => true,
                    'dateTime' => [
                        'dateTimeClass' => '\DateTimeImmutable',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function buildConfigLocalArray(array $options): array
    {
        return [
            'propel' => [
                'database' => [
                    'connections' => [
                        'default' => [
                            'dsn' => $options['dsn'],
                            'user' => $options['user'],
                            'password' => $options['password'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $format
     * @param array $config
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function toFormat(string $format, array $config): string
    {
        $formatted = match ($format) {
            'ini' => implode("\n", $this->toIniFormat($config)) . "\n",
            'php' => "<?php\nreturn " . $this->outputPhpArray($config) . ";\n",
            'json' => json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'xml' => XmlToArrayConverter::fromArray(['config' => $config]),
            'yml' => (new YamlParser())->fromArray($config, null, 10),
            default => throw new InvalidArgumentException("Format not supported: `$format`")
        };

        if (!is_string($formatted)) {
            throw new InvalidArgumentException("Failed to format config as $format" . print_r($config, true));
        }

        return $formatted;
    }

    /**
     * @param string $filename
     * @param string $content
     *
     * @return void
     */
    private function writeFile(string $filename, string $content): void
    {
        $directory = dirname($filename);
        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($filename, $content);
        $this->output->writeln("<info> wrote $filename</info>");
    }

    /**
     * @param array $array
     * @param string $iniKey
     *
     * @return array<string>
     */
    private function toIniFormat(array $array, string $iniKey = '')
    {
        $ini = [];
        foreach ($array as $itemKey => $item) {
            $key = $iniKey ? "$iniKey.$itemKey" : $itemKey;
            if (is_array($item)) {
                array_push($ini, ...$this->toIniFormat($item, $key));
            } else {
                $escapedItem = is_string($item)
                    ? '"' . addcslashes($item, '"') . '"'
                    : var_export($item, true);
                $ini[] = "$key = $escapedItem";
            }
        }

        return $ini;
    }

    /**
     * @param array $array
     * @param string $outerIndent
     *
     * @return string
     */
    private function outputPhpArray(array $array, string $outerIndent = ''): string
    {
        $output = '';
        foreach ($array as $itemKey => $item) {
            $innerIndent = "$outerIndent    ";
            $value = match (true) {
                is_array($item) => $this->outputPhpArray($item, $innerIndent),
                default => var_export($item, true)
            };
            $output .= "\n{$innerIndent}'{$itemKey}' => $value,";
        }

        return "[{$output}\n{$outerIndent}]";
    }
}
