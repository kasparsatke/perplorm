<?php

namespace Propel\Tests\Generator\Manager\InitManager;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Manager\InitManager\ProjectStructureSetupDialog;
use Propel\Tests\TestCase;
use Symfony\Component\Filesystem\Filesystem;


class ProjectStructureSetupDialogTest extends TestCase
{
    /**
     * @return array<array>
     */
    public static function GuessNamespaceDataProvider(): array
    {
        return [
            ['\\Model', []],
            ['\\Foo\\Model', ['Foo/']],
            ['\\Foo\\Bar\\Baz\\Model', ['Foo/Bar/Baz/']],
            ['\\Foo\\Model', ['Foo/Bar/', 'Foo/Baz/']],
            ['\\Foo\\Bar\\Model', ['Foo/Bar/Baz/', 'Foo/Bar/Model/']],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('GuessNamespaceDataProvider')]
    public function testGuessNamespace(string $expectedNamespace, array $srcDirPaths = [])
    {
        $tmpDir = $this->createDirectoryStructure($srcDirPaths);

        $guessedNamespace = $this->callMethod(ProjectStructureSetupDialog::class, 'guessModelNamespace', [$tmpDir]);
        $this->assertSame($expectedNamespace, $guessedNamespace);
    }

    protected function createDirectoryStructure(array $srcDirPaths): string
    {
        $tmpDir = sys_get_temp_dir() . '/perpl-tests/project-structure-dialog/';

        $filesystem = new Filesystem();
        if ($filesystem->exists($tmpDir)) {
            $filesystem->remove($tmpDir);
        }

        foreach ($srcDirPaths as $path) {
            $filesystem->mkdir("$tmpDir/$path");
        }

        return $tmpDir;
    }

}
