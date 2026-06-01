<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Loader;

use Propel\Common\Config\Exception\JsonParseException;
use function file_get_contents;
use function json_decode;
use function json_last_error;
use const JSON_ERROR_NONE;

/**
 * JsonFileLoader loads configuration parameters from json file.
 */
class JsonFileLoader extends FileLoader
{
    /**
     * Loads an Json file.
     *
     * @param string $path The resource
     *
     * @throws \Propel\Common\Config\Exception\JsonParseException if invalid json file
     *
     * @return array
     */
    #[\Override]
    protected function loadFileContent(string $path): array
    {
        $json = file_get_contents($path);
        if (!$json) {
            return [];
        }

        $content = json_decode($json, true);
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE) {
            throw new JsonParseException($error);
        }

        return $content;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed $resource A resource
     * @param string|null $type The resource type
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    #[\Override]
    public function supports($resource, $type = null): bool
    {
        return static::checkSupports('json', $resource);
    }
}
