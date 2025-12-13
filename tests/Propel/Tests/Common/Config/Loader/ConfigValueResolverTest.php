<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Common\Config\Loader;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Common\Config\Exception\InvalidArgumentException;
use Propel\Common\Config\Exception\RuntimeException;
use Propel\Common\Config\Loader\ConfigValueResolver;
use Propel\Tests\TestCase;

class ConfigValueResolverTest extends TestCase
{
    /**
     * @return array<array{array, array, string}>
     */
    public static function resolveParamsProvider()
    {
        return [
            [
                ['foo'],
                ['foo'],
                '->resolve() returns its argument unmodified if no placeholders are found',
            ],
            [
                ['foo' => 'bar', 'I\'m a %foo%'],
                ['foo' => 'bar', 'I\'m a bar'],
                '->resolve() replaces placeholders by their values',
            ],
            [
                ['foo' => 'bar', '%foo%' => '%foo%'],
                ['foo' => 'bar', 'bar' => 'bar'],
                '->resolve() replaces placeholders in keys and values of arrays',
            ],
            [
                ['foo' => 'bar', '%foo%' => ['%foo%' => ['%foo%' => '%foo%']]],
                ['foo' => 'bar', 'bar' => ['bar' => ['bar' => 'bar']]],
                '->resolve() replaces placeholders in nested arrays',
            ],
            [
                ['foo' => 'bar', 'I\'m a %%foo%%'],
                ['foo' => 'bar', 'I\'m a %foo%'],
                '->resolve() supports % escaping by doubling it',
            ],
            [
                ['foo' => 'bar', 'I\'m a %foo% %%foo %foo%'],
                ['foo' => 'bar', 'I\'m a bar %foo bar'],
                '->resolve() supports % escaping by doubling it',
            ],
            [
                ['foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']]],
                ['foo' => ['bar' => ['ding' => 'I\'m a bar %foo %bar']]],
                '->resolve() supports % escaping by doubling it',
            ],
            [
                ['foo' => 'bar', 'baz' => '%%%foo% %foo%%% %%foo%% %%%foo%%%'],
                ['foo' => 'bar', 'baz' => '%bar bar% %foo% %bar%'],
                '->resolve() replaces params placed besides escaped %',
            ],
            [
                ['baz' => '%%s?%%s', '%baz%'],
                ['baz' => '%s?%s', '%s?%s'],
                '->resolve() is not replacing greedily',
            ],
            [
                ['host' => 'foo.bar', 'port' => 1337, '%host%:%port%'],
                ['host' => 'foo.bar', 'port' => 1337, 'foo.bar:1337'],
                '',
            ],
            [
                ['foo' => 'bar', '%foo%'],
                ['foo' => 'bar', 'bar'],
                'Parameters must be wrapped by %.',
            ],
            [
                ['foo' => 'bar', '% foo %'],
                ['foo' => 'bar', '% foo %'],
                'Parameters should not have spaces.',
            ],
            [
                ['foo' => 'bar', '{% set my_template = "foo" %}'],
                ['foo' => 'bar', '{% set my_template = "foo" %}'],
                'Twig-like strings are not parameters.',
            ],
            [
                ['foo' => 'bar', '50% is less than 100%'],
                ['foo' => 'bar', '50% is less than 100%'],
                'Text between % signs is allowed, if there are spaces.',
            ],
            [
                ['foo' => ['bar' => 'baz', '%bar%' => 'babar'], 'babaz' => '%foo%'],
                ['foo' => ['bar' => 'baz', 'baz' => 'babar'], 'babaz' => ['bar' => 'baz', 'baz' => 'babar']],
                '',
            ],
            [
                ['foo' => ['bar' => 'baz'], 'babaz' => '%foo%'],
                ['foo' => ['bar' => 'baz'], 'babaz' => ['bar' => 'baz']],
                'Should replace with array',
            ],
            [
                ['__DIR__'],
                [__DIR__],
                'Replace __DIR__ as only param'
            ],
            [
                ['foo/__DIR__/bar'],
                ['foo/' . __DIR__ . '/bar'],
                'Replace __DIR__ in string'
            ],
            [
                ['%protocol%:__DIR__/%folder%/foo', 'folder' => 'bar', 'protocol' => 'file'],
                ['file:'.__DIR__ . '/bar/foo', 'folder' => 'bar', 'protocol' => 'file'],
                'Replace __DIR__ and param'
            ],
            [
                ['__DIR__:__DIR__'],
                [__DIR__ . ':' . __DIR__,],
                'Replace all __DIR__'
            ],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('resolveParamsProvider')]
    public function testResolveValues($conf, $expected, $message)
    {
        $this->assertParamsResolveTo($expected, $conf, null, $message);
    }

    /**
     * @return void
     */
    public function testResolveFullConfigParams()
    {
        putenv('host=127.0.0.1');
        putenv('user=root');

        $config = [
            'HoMe' => 'myHome',
            'project' => 'myProject',
            'subhome' => '__DIR__/%HoMe%/subhome',
            'property1' => 1,
            'property2' => false,
            'direcories' => [
                'project' => '%HoMe%/projects/%project%',
                'conf' => '%project%',
                'schema' => '%project%/schema',
                'template' => '%HoMe%/templates',
                'output%project%' => '/build',
            ],
            '%HoMe%' => 4,
            'host' => '%env.host%',
            'user' => '%env.user%',
        ];

        $expected = [
            'HoMe' => 'myHome',
            'project' => 'myProject',
            'subhome' => dirname(__FILE__) . '/myHome/subhome',
            'property1' => 1,
            'property2' => false,
            'direcories' => [
                'project' => 'myHome/projects/myProject',
                'conf' => 'myProject',
                'schema' => 'myProject/schema',
                'template' => 'myHome/templates',
                'outputmyProject' => '/build',
            ],
            'myHome' => 4,
            'host' => '127.0.0.1',
            'user' => 'root',
        ];

        $this->assertParamsResolveTo($expected, $config);

        //cleanup environment
        putenv('host');
        putenv('user');
    }

    /**
     * @return void
     */
    public function testResolveReplaceWithoutCasting()
    {
        $config = ['foo' => true, 'expfoo' => '%foo%', 'bar' => null, 'expbar' => '%bar%'];
        $resolvedConfig = ConfigValueResolver::resolve($config, __FILE__);

        $this->assertTrue($resolvedConfig['expfoo'], '->resolve() replaces arguments that are just a placeholder by their value without casting them to strings');
        $this->assertNull($resolvedConfig['expbar'], '->resolve() replaces arguments that are just a placeholder by their value without casting them to strings');
    }

    /**
     * @return array<array{string, class-string<\Throwable>, string}>
     */
    public static function InvalidConfigurationsDataProvider(): array
    {
        return [
            [['foo' => 'bar', '%baz%'], InvalidArgumentException::class, "Parameter 'baz' not found in configuration file."],
            [['foo %foobar% bar'], InvalidArgumentException::class, "Parameter 'foobar' not found in configuration file."],
            [['foo' => '%bar%', 'bar' => '%foobar%', 'foobar' => '%foo%'], RuntimeException::class, "Circular reference detected for parameter 'bar'."],
            [['foo' => 'a %bar%', 'bar' => 'a %foobar%', 'foobar' => 'a %foo%'], RuntimeException::class, "Circular reference detected for parameter 'bar'."],
        ];
    }

    /**
     * @dataProvider InvalidConfigurationsDataProvider
     *
     * @param array $config
     * @param class-string<\Throwable> $exceptionClass
     * @param string $exceptionMessage
     *
     * @return void
     */
    #[DataProvider('InvalidConfigurationsDataProvider')]
    public function testInvalidConfigurationThrowsException(array $config, string $exceptionClass, string $exceptionMessage): void
    {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);
        ConfigValueResolver::resolve($config, __FILE__);
    }

    /**
     * @return void
     */
    public function testResolveEnvironmentVariable()
    {
        putenv('home=myHome');
        putenv('schema=mySchema');
        putenv('isBoolean=true');
        putenv('integer=1');

        $config = [
            'home' => '%env.home%',
            'property1' => '%env.integer%',
            'property2' => '%env.isBoolean%',
            'direcories' => [
                'projects' => '%home%/projects',
                'schema' => '%env.schema%',
                'template' => '%home%/templates',
                'output%env.home%' => '/build',
            ],
        ];

        $expected = [
            'home' => 'myHome',
            'property1' => '1',
            'property2' => 'true',
            'direcories' => [
                'projects' => 'myHome/projects',
                'schema' => 'mySchema',
                'template' => 'myHome/templates',
                'outputmyHome' => '/build',
            ],
        ];

        $this->assertParamsResolveTo($expected, $config);

        //cleanup environment
        putenv('home');
        putenv('schema');
        putenv('isBoolean');
        putenv('integer');
    }

    /**
     * @return void
     */
    public function testResolveEmptyEnvironmentVariable()
    {
        putenv('home=');

        $config = [
            'home' => '%env.home%',
        ];

        $expected = [
            'home' => '',
        ];

        $this->assertParamsResolveTo($expected, $config);

        //cleanup environment
        putenv('home');
    }

    /**
     * @return void
     */
    public function testNonExistentEnvironmentVariableThrowsException()
    {
        putenv('home=myHome');

        $config = [
            'home' => '%env.home%',
            'property1' => '%env.foo%',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Environment variable 'foo' is not defined.");
        ConfigValueResolver::resolve($config, __FILE__);
    }

    /**
     * @return void
     */
    public function testParameterIsNotStringOrNumber()
    {
        $config = [
            'foo' => 'a %bar%',
            'bar' => [],
            'baz' => '%foo%',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A string value must be composed of strings and/or numbers,');
        ConfigValueResolver::resolve($config, __FILE__);
    }

    /**
     * @return void
     */
    public function testDirResolvesDirectoryInput()
    {
        $this->assertParamsResolveTo([__DIR__], ['__DIR__'], __DIR__);
    }

    /**
     * @return void
     */
    public function testDirResolvesFileInput()
    {
        $this->assertParamsResolveTo([__DIR__], ['__DIR__'], __FILE__);
    }

    /**
     * @param array $expected
     * @param array $config
     * @param string|null $dirValue
     * @param string $message
     *
     * @return void
     */
    protected function assertParamsResolveTo(array $expected, array $config, string|null $dirValue = null, string $message = ''): void
    {
        $actual = ConfigValueResolver::resolve($config, $dirValue ?? __FILE__);
        $this->assertEquals($expected, $actual, $message);
    }
}
