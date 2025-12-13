<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Loader;

use Propel\Common\Config\XmlToArrayConverter;

/**
 * XmlFileLoader loads configuration parameters from xml file.
 */
class XmlFileLoader extends FileLoader
{
    /**
     * Loads an Xml file.
     *
     * @param string $path
     *
     * @return array
     */
    #[\Override]
    protected function loadFileContent(string $path): array
    {
        return XmlToArrayConverter::convert($path);
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
        return static::checkSupports('xml', $resource);
    }
}
