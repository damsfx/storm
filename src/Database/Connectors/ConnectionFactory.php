<?php namespace Winter\Storm\Database\Connectors;

use Illuminate\Support\Arr;
use Illuminate\Database\Connectors\ConnectionFactory as ConnectionFactoryBase;
use Winter\Storm\Database\Connections\Connection;
use Winter\Storm\Database\Connections\MySqlConnection;
use Winter\Storm\Database\Connections\SQLiteConnection;
use Winter\Storm\Database\Connections\PostgresConnection;
use Winter\Storm\Database\Connections\SqlServerConnection;
use PDOException;
use InvalidArgumentException;

class ConnectionFactory extends ConnectionFactoryBase
{
    /**
     * Carbon copy of parent. Except Laravel creates an "uncatchable" exception,
     * this is resolved as part of the override below.
     *
     * @param  array  $config
     * @return \Closure
     */
    protected function createPdoResolverWithHosts(array $config)
    {
        return function () use ($config) {
            foreach (Arr::shuffle($hosts = $this->parseHosts($config)) as $key => $host) {
                $config['host'] = $host;

                try {
                    return $this->createConnector($config)->connect($config);
                }
                catch (PDOException $e) {
                }
            }

            if (isset($e)) {
                throw $e;
            }
        };
    }

    /**
     * Create a connector instance based on the configuration.
     *
     * @param  array  $config
     * @return \Illuminate\Database\Connectors\ConnectorInterface
     *
     * @throws \InvalidArgumentException
     */
    public function createConnector(array $config)
    {
        if (array_get($config, 'driver') === 'sqlite') {
            return new SQLiteConnector;
        } else {
            return parent::createConnector($config);
        }
    }

    /**
     * Create a new connection instance.
     *
     * @param  string   $driver
     * @param  \PDO     $connection
     * @param  string   $database
     * @param  string   $prefix
     * @param  array    $config
     * @return \Illuminate\Database\Connection
     *
     * @throws \InvalidArgumentException
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        if ($resolver = Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        switch ($driver) {
            case 'mysql':
                return new MySqlConnection($connection, $database, $prefix, $config);
            case 'pgsql':
                return new PostgresConnection($connection, $database, $prefix, $config);
            case 'sqlite':
                return new SQLiteConnection($connection, $database, $prefix, $config);
            case 'sqlsrv':
                return new SqlServerConnection($connection, $database, $prefix, $config);
        }

        throw new InvalidArgumentException("Unsupported driver [$driver]");
    }
}
