<?php

declare(strict_types = 1);

namespace Propel\Generator\Manager\InitManager;

use Propel\Generator\Command\Helper\ConsoleHelper;
use Propel\Runtime\Adapter\AdapterFactory;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\Exception\ConnectionException;
use Symfony\Component\Console\Output\OutputInterface;
use function array_slice;
use function getcwd;
use function preg_match;
use function print_r;

class ConnectionSetupDialog extends ConsoleHelper
{
    /**
     * @param \Propel\Generator\Command\Helper\ConsoleHelper $parentHelper
     *
     * @return array{dbVendor: string, dsn: string, user: string, password: string, charset: string}
     */
    public static function runSetup(ConsoleHelper $parentHelper): array
    {
        return (new self($parentHelper))->setUpDatabaseConnection();
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
     * @return array{dbVendor: string, dsn: string, user: string, password: string, charset: string}
     */
    protected function setUpDatabaseConnection(): array
    {
        $this->writeBlock('Register database connection');
        $this->writeln('');

        $dbVendor = $this->select('Select database vendor', [
            'mysql' => 'MySQL',
            'sqlite' => 'SQLite',
            'pgsql' => 'PostgreSQL',
            'oracle' => 'Oracle',
            'sqlsrv' => 'MSSQL (via pdo-sqlsrv)',
            'mssql' => 'MSSQL (via pdo-mssql)',
        ]);

        $connectionSuccessful = false;
        $dsn = null;
        $user = null;

        do {
            $dsn = match ($dbVendor) {
                'mysql' => $this->requestMysqlDsn($dsn),
                'pgsql' => $this->requestPgsqlDsn($dsn),
                'sqlite' => $this->requestSqliteDsn(),
                default => $this->requestDsn($dbVendor),
            };

            $user = (string)$this->askQuestion('DB user', $user ?? 'root');
            $password = (string)$this->askHiddenResponse('DB password');

            $defaultCharset = $dbVendor === 'mysql' ? 'utf8mb4' : 'utf8';
            $charset = $this->askQuestion('charset', $defaultCharset);

            $connectionSuccessful = $this->testConnection($dbVendor, $dsn, $user, $password);
            if (!$connectionSuccessful) {
                $options = [
                    'retry' => 're-enter credentials',
                    'ignore' => 'keep as is - cannot import existing database schema now, you\'ll have to run <info>database:reverse</info> later)',
                ];
                $nextAction = $this->select('What now?', $options, 'retry');
                if ($nextAction === 'ignore') {
                    break;
                }
            }
        } while (!$connectionSuccessful);

        return [
            'dbVendor' => $dbVendor,
            'dsn' => $dsn,
            'user' => $user,
            'password' => $password,
            'charset' => $charset,
        ];
    }

    /**
     * @param string|null $dsn
     *
     * @return string
     */
    private function requestMysqlDsn(string|null $dsn): string
    {
        return 'mysql' . $this->requestDefaultDsn('3306', $dsn);
    }

    /**
     * @param string|null $dsn
     *
     * @return string
     */
    private function requestPgsqlDsn(string|null $dsn): string
    {
        return 'pgsql' . $this->requestDefaultDsn('5432', $dsn);
    }

    /**
     * @param string $defaultPort
     * @param string $dsn
     *
     * @return string
     */
    private function requestDefaultDsn(string $defaultPort, string|null $dsn): string
    {
        [$defaultHost, $defaultPort, $defaultDatabase] = $this->splitDsn($dsn) ?? ['localhost', $defaultPort, null];

        $host = $this->askQuestion('host', $defaultHost);
        $port = $this->askQuestion('port', $defaultPort);
        $database = $this->askQuestion('database name', $defaultDatabase);

        return ":host=$host;port=$port;dbname=$database";
    }

    /**
     * @param string $dsn
     *
     * @return array{string, string, string}|null
     */
    protected function splitDsn(string|null $dsn): array|null
    {
        if (!$dsn) {
            return null;
        }
        preg_match('/:host=(.+?);port=(.+?);dbname=(.+)/', $dsn, $matches);

        return $matches ? array_slice($matches, 1) : null;
    }

    /**
     * @return string
     */
    private function requestSqliteDsn(): string
    {
        $path = $this->askQuestion('sqlite database file location', getcwd() . '/my.app.sq3');

        return "sqlite:$path";
    }

    /**
     * @param string $rdbms
     *
     * @return string
     */
    private function requestDsn(string $rdbms): string
    {
        $help = match ($rdbms) {
            'oracle' => 'https://php.net/manual/en/ref.pdo-oci.connection.php#refsect1-ref.pdo-oci.connection-description',
            'sqlsrv' => 'https://php.net/manual/en/ref.pdo-sqlsrv.connection.php#refsect1-ref.pdo-sqlsrv.connection-description',
            'mssql' => 'https://php.net/manual/en/ref.pdo-dblib.connection.php#refsect1-ref.pdo-dblib.connection-description',
            default => 'https://php.net/manual/en/pdo.drivers.php',
        };

        return $this->askQuestion("Please enter dsn string (see <comment>$help</comment>) for your database connection");
    }

    /**
     * @param string $dbVendor
     * @param string $dsn
     * @param string|null $user
     * @param string|null $password
     *
     * @return bool
     */
    private function testConnection(string $dbVendor, string $dsn, string|null $user, string|null $password): bool
    {
        $adapter = AdapterFactory::create($dbVendor);

        try {
            ConnectionFactory::create(['dsn' => $dsn, 'user' => $user, 'password' => $password], $adapter);

            $this->writeSection('<info>Successfully connected to sql server!</info>');

            return true;
        } catch (ConnectionException $e) {
            // get the "real" wrapped exception message
            do {
                $e = $e->getPrevious() ?? $e;
                $message = $e->getMessage();
            } while ($e->getPrevious() !== null);

            $this->writeBlock('Failed to connect with server: ' . $message, 'error');
            $this->writeln('');

            if ($this->getOutput()->getVerbosity() === OutputInterface::VERBOSITY_DEBUG) {
                $this->writeln('Exception: ' . print_r($e, true));
            }

            return false;
        }
    }
}
