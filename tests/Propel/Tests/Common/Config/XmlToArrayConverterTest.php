<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Common\Config;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Common\Config\Exception\InvalidArgumentException;
use Propel\Common\Config\Exception\XmlParseException;
use Propel\Common\Config\XmlToArrayConverter;
use Propel\Tests\TestCase;
use Propel\Generator\Util\VfsTrait;

class XmlToArrayConverterTest extends TestCase
{
    use VfsTrait;
    use DataProviderTrait;

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForXmlToArrayConverter')]
    public function testConvertFromString(string $xml, $expected)
    {
        $actual = XmlToArrayConverter::convert($xml);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForXmlToArrayConverter')]
    public function testConvertFromFile($xml, $expected)
    {
        $file = $this->newFile('testconvert.xml', $xml);
        $actual = XmlToArrayConverter::convert($file->url());

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForXmlToArrayConverterXmlInclusions')]
    public function testConvertFromFileWithXmlInclusion($xmlLoad, $xmlInclude, $expected)
    {
        $this->newFile('testconvert.xml', $xmlLoad);
        $this->newFile('testconvert_include.xml', $xmlInclude);
        $actual = XmlToArrayConverter::convert(vfsStream::url('root/testconvert.xml'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testInexistentFileThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid xml content');

        XmlToArrayConverter::convert('nonexistent.xml');
    }

    /**
     * @return void
     */
    public function testInvalidXmlThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid xml content');

        $invalidXml = <<< INVALID_XML
No xml
only plain text
---------
INVALID_XML;
        XmlToArrayConverter::convert($invalidXml);
    }

    /**
     * @return void
     */
    public function testErrorInXmlThrowsException()
    {
        $this->expectException(XmlParseException::class);
        $this->expectExceptionMessage('An error occurred while parsing XML configuration file:');

        $xmlWithError = <<< XML
<?xml version='1.0' standalone='yes'?>
<movies>
 <movie>
  <titles>Star Wars</title>
 </movie>
 <movie>
  <title>The Lord Of The Rings</title>
 </movie>
</movies>
XML;
        XmlToArrayConverter::convert($xmlWithError);
    }

    /**
     * @return void
     */
    public function testMultipleErrorsInXmlThrowsException()
    {
        $this->expectException(XmlParseException::class);
        $this->expectExceptionMessage('Some errors occurred while parsing XML configuration file:');

        $xmlWithErrors = <<< XML
<?xml version='1.0' standalone='yes'?>
<movies>
 <movie>
  <titles>Star Wars</title>
 </movie>
 <movie>
  <title>The Lord Of The Rings</title>
 </movie>
</moviess>
XML;
        XmlToArrayConverter::convert($xmlWithErrors);
    }

    /**
     * @return void
     */
    public function testEmptyFileReturnsEmptyArray()
    {
        $file = $this->newFile('empty.xml', '');
        $actual = XmlToArrayConverter::convert($file->url());

        $this->assertEquals([], $actual);
    }

    public static function ArrayToXmlDataProvider(): array
    {
        return [
            [
                ['rootName' => 'text content'],
                '<rootName>text content</rootName>'
            ], [
                ['root' => ['node1' => 1, 'node2' => ['nested1' => 12.3, 'nested2' => 'value']]],
                '<root>
  <node1>1</node1>
  <node2>
    <nested1>12.3</nested1>
    <nested2>value</nested2>
  </node2>
</root>'
            ]
        ];
    }

    #[DataProvider('ArrayToXmlDataProvider')]
    public function testArrayToXml(array $array, string $expected): void
    {
        $actual = XmlToArrayConverter::fromArray($array);
        $this->assertSame("<?xml version=\"1.0\"?>\n$expected\n", $actual);
    }

    public static function ArrayToXmlInvalidArrayDataProvider(): array
    {
        return [
            [[]], 
            [['node1' => 1, 'node2' => 2]]
        ];
    }

    #[DataProvider('ArrayToXmlInvalidArrayDataProvider')]
    public function testArrayToXmlInvalidInputException(array $array): void
    {
        $this->expectException(InvalidArgumentException::class);
        XmlToArrayConverter::fromArray($array);
    }
}
