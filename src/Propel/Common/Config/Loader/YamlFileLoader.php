<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Loader;

use Propel\Common\Config\Exception\InputOutputException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use function file_get_contents;
use function is_array;
use function sprintf;

/**
 * YamlFileLoader loads configuration parameters from yaml file.
 */
class YamlFileLoader extends FileLoader
{
    /**
     * Loads a Yaml file.
     *
     * @param string $path
     *
     * @throws \Propel\Common\Config\Exception\InputOutputException if configuration file is not readable
     * @throws \Symfony\Component\Yaml\Exception\ParseException if something goes wrong in parsing file
     *
     * @return array
     */
    #[\Override]
    protected function loadFileContent(string $path): array
    {
        $data = file_get_contents($path);
        if (!$data) {
            throw new InputOutputException(sprintf('Unable to read configuration file `%s`.', $path));
        }

        $content = Yaml::parse($data) ?? [];

        if (!is_array($content)) {
            throw new ParseException('Unable to parse the configuration file: wrong yaml content.');
        }

        return $content;
    }

    /**
     * Returns true if this class supports the given resource.
     * Both 'yml' and 'yaml' extensions are accepted.
     *
     * @param mixed $resource A resource
     * @param string|null $type The resource type
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    #[\Override]
    public function supports($resource, $type = null): bool
    {
        return static::checkSupports(['yaml', 'yml'], $resource);
    }
}
