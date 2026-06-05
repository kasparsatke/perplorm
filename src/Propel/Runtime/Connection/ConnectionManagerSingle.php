<?php

declare(strict_types = 1);

namespace Propel\Runtime\Connection;

use InvalidArgumentException;
use Propel\Runtime\Adapter\AdapterInterface;
use Propel\Runtime\Exception\RuntimeException;

/**
 * Manager for single connection to a datasource.
 */
class ConnectionManagerSingle implements ConnectionManagerInterface
{
    /**
     * @var string The datasource name associated to this connection
     */
    protected string $name;

    /**
     * @var array{dsn: string, user?: string|null, password: string|null, options?: mixed, settings?: mixed, classname?: class-string, attributes?: mixed}|null
     */
    protected array|null $configuration = null;

    protected ConnectionInterface|null $connection = null;

    /**
     * @param string $name The datasource name associated to this connection
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string $name The datasource name associated to this connection
     *
     * @return void
     */
    #[\Override]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string The datasource name associated to this connection
     */
    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws \Propel\Runtime\Exception\RuntimeException
     *
     * @return array{dsn: string, user?: string|null, password: string|null, options?: mixed, settings?: mixed, classname?: class-string, attributes?: mixed}
     */
    public function getConfiguration(): array
    {
        if (!$this->configuration) {
            throw new RuntimeException('Requested connection configuration before it was set.');
        }

        return $this->configuration;
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $connection
     *
     * @return void
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->setConfiguration(null);
        $this->connection = $connection;
    }

    /**
     * @param array{dsn: string, user?: string|null, password: string|null, options?: mixed, settings?: mixed, classname?: class-string, attributes?: mixed}|null $configuration
     *
     * @return void
     */
    public function setConfiguration(?array $configuration): void
    {
        $this->configuration = $configuration;
        $this->closeConnections();
    }

    /**
     * @param \Propel\Runtime\Adapter\AdapterInterface|null $adapter
     *
     * @throws \InvalidArgumentException
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    #[\Override]
    public function getWriteConnection(?AdapterInterface $adapter = null): ConnectionInterface
    {
        if ($this->connection === null) {
            if ($adapter === null) {
                throw new InvalidArgumentException('$adapter not given');
            }

            $this->connection = ConnectionFactory::create($this->getConfiguration(), $adapter);
            $this->connection->setName($this->getName());
        }

        return $this->connection;
    }

    /**
     * @param \Propel\Runtime\Adapter\AdapterInterface|null $adapter
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    #[\Override]
    public function getReadConnection(?AdapterInterface $adapter = null): ConnectionInterface
    {
        return $this->getWriteConnection($adapter);
    }

    /**
     * @return void
     */
    #[\Override]
    public function closeConnections(): void
    {
        $this->connection = null;
    }
}
