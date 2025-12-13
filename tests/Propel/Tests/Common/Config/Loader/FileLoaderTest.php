<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Common\Config\Loader;

use Propel\Common\Config\Loader\FileLoader as BaseFileLoader;
use Propel\Tests\TestCase;

class FileLoaderTest extends TestCase
{
    /** @var TestableFileLoader */
    private $loader;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->loader = new TestableFileLoader();
    }

    /**
     * @return void
     */
    public function testResourceNameIsNotStringReturnsFalse()
    {
        $this->assertFalse(TestableFileLoader::checkSupports('ini', null));
        $this->assertFalse(TestableFileLoader::checkSupports('yaml', false));
    }

    /**
     * @return void
     */
    public function testExtensionIsNotStringOrArrayReturnsFalse()
    {
        $this->assertFalse(TestableFileLoader::checkSupports('', '/tmp/propel.yaml'));
        $this->assertFalse(TestableFileLoader::checkSupports('12', '/tmp/propel.yaml'));
    }
}

class TestableFileLoader extends BaseFileLoader
{
    protected function loadFileContent(string $path): array
    {
        return [];
    }

    public function supports($resource, $type = null): bool
    {
        return false;
    }

    /**
     * @param string|string[] $ext
     * @param mixed $resource
     *
     * @return bool
     */
    public static function checkSupports($ext, $resource): bool
    {
        return parent::checkSupports($ext, $resource);
    }
}
