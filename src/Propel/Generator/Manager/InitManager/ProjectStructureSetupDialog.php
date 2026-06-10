<?php

declare(strict_types = 1);

namespace Propel\Generator\Manager\InitManager;

use Propel\Generator\Command\Helper\ConsoleHelper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use function getcwd;
use function is_dir;
use function realpath;
use function rtrim;
use function str_ends_with;
use function substr;

class ProjectStructureSetupDialog extends ConsoleHelper
{
    /**
     * @param \Propel\Generator\Command\Helper\ConsoleHelper $parentHelper
     *
     * @return array{projectRootPath: string, perplDir: string, projectDir: string, phpDir: string, namespace: string}|null
     */
    public static function runSetup(ConsoleHelper $parentHelper): array|null
    {
        return (new self($parentHelper))->setUpPathVariables();
    }

    /**
     * @param \Propel\Generator\Command\Helper\ConsoleHelper $parentHelper
     */
    public function __construct(ConsoleHelper $parentHelper)
    {
        parent::__construct($parentHelper->getInput(), $parentHelper->getOutput());
        $this->setHelperSet($parentHelper->getHelperSet());
    }

    /**
     * @return array{projectRootPath: string, perplDir: string, projectDir: string, phpDir: string, namespace: string}
     */
    protected function setUpPathVariables(): array
    {
        $this->writeBlock('Set up project structure.');

        $this->printDirectoryStructureExample();

        $projectRootPath = $this->inquireProjectRootPath();
        $perplDir = $this->inquirePerplDir();
        $projectDir = !$perplDir ? '__DIR__' : '__DIR__/' . Path::makeRelative($projectRootPath, "$projectRootPath/$perplDir");
        $phpDir = (string)$this->askQuestion('Model files base directory (root of PHP namespace):', 'src');

        $guessedNamespace = self::guessModelNamespace("$projectRootPath/$phpDir");
        $namespace = $this->askQuestion('Namespace for PHP model files:', $guessedNamespace);

        return [
            'projectRootPath' => $projectRootPath,
            'perplDir' => $perplDir,
            'projectDir' => $projectDir,
            'phpDir' => "$projectDir/$phpDir",
            'namespace' => $namespace,
            'schemaDir' => '__DIR__/schema',
            'phpConfDir' => '__DIR__/generated-conf',
            'sqlDir' => '__DIR__/generated-sql',
            'migrationDir' => '__DIR__/generated-migrations',
        ];
    }

    /**
     * @return void
     */
    protected function printDirectoryStructureExample(): void
    {
        $this->writeSection('A typical perpl project looks like this:

%projectDir%/                              <comment>project root directory</comment>
    │
    ├─╼ %perplDir%/                          <comment>perpl config dir (should be \'config\', \'conf\', \'perpl\', or \'db\' if to be resolved automatically by perpl commands)</comment>
    │      │
    │      ├─╼ %schemaDir%/                    <comment>Schema XML files (input for migration:diff, database:reverse, etc)</comment>
    │      │      └─╼ schema.xml                 <comment>Main schema file</comment>
    │      │
    │      ├─╼ generated-conf/                 <comment>PHP scripts to import database configuration (from config:convert and model:build)</comment>
    │      ├─╼ generated-migrations/           <comment>Migration files (target for migration:create, migration:migrate, etc)</comment>
    │      ├─╼ generated-sql/                  <comment>SQL database initialization files for sql:insert (user-generated and generated from schema.xml by sql:build)</comment>
    │      ├─╼ perpl.json                      <comment>Instance-specific configuration values (i.e. database connection)</comment>
    │      └─╼ perpl.dist.json                 <comment>Shared configuration values (i.e. main directory structure)</comment>
    │
    └─╼ %phpDir%/                            <comment>Base target directory for model:build, root of PHP namespace, typically \'/src/\'</comment>
 
You can adjust this structure later by editing the config file.
');
    }

    /**
     * @return string
     */
    protected function inquireProjectRootPath(): string
    {
        $default = getcwd() ?: '.';
        $projectPath = (string)$this->askQuestion('Project root directory:', $default) ?: $default;

        return realpath($projectPath) ?: $projectPath;
    }

    /**
     * @return string
     */
    protected function inquirePerplDir(): string
    {
        $perplDir = $this->select('perpl config dir (use project root directory, \'config\', \'conf\', \'perpl\', or \'db\' to be resolved automatically by perpl commands)', [
            'db',
            'conf',
            'config',
            'perpl',
            'set custom',
            'use project root',
        ], 'db');

        if ($perplDir === 'set custom') {
            $this->writeSection('Remember, you\'ll have to use the --config-dir parameter even when calling perpl commands from root directory.');
            $perplDir = (string)$this->askQuestion('perpl config dir') ?: 'use project root';
        }

        return $perplDir === 'use project root' ? '' : rtrim($perplDir, '/\\');
    }

    /**
     * @param string $srcDirPath
     *
     * @return string
     */
    private static function guessModelNamespace(string $srcDirPath): string
    {
        if (!is_dir($srcDirPath)) {
            return '\\Model';
        }
        // incrementally go down single directories in src
        $namespace = '\\';
        $path = $srcDirPath;
        $maxDepth = 5;
        while (true) {
            $finder = Finder::create()->directories()->in($path)->depth(0);
            $maxDepth--;
            if ($finder->count() !== 1 || $maxDepth < 0) {
                break;
            }
            $iterator = $finder->getIterator();
            $iterator->rewind();
            $dirname = $iterator->current()->getFilename();
            $path .= "/$dirname";
            $namespace .= "$dirname\\";
        }

        return str_ends_with($namespace, '\\Model\\')
            ? substr($namespace, 0, -1)
            : "{$namespace}Model";
    }
}
