<?php

namespace danog\MadelineProto\Db\Driver;

use Amp\Postgres\ConnectionConfig;
use Amp\Postgres\Pool;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Database\Postgres as DatabasePostgres;
use function Amp\Postgres\Pool;
/**
 * Postgres driver wrapper.
 *
 * @internal
 */
class Postgres
{
    /**
     * @var Pool[]
     */
    private static array $connections = [];
    /**
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $db
     *
     * @param int $maxConnections
     * @param int $idleTimeout
     *
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws \Throwable
     *
     * @return \Generator<Pool>
     */
    public static function getConnection(DatabasePostgres $settings) : \Generator
    {
        $dbKey = $settings->getKey();
        if (empty(static::$connections[$dbKey])) {
            $config = ConnectionConfig::fromString("host=" . \str_replace("tcp://", "", $settings->getUri()))->withUser($settings->getUsername())->withPassword($settings->getPassword())->withDatabase($settings->getDatabase());
            yield from static::createDb($config);
            static::$connections[$dbKey] = new Pool($config, $settings->getMaxConnections(), $settings->getIdleTimeout());
        }
        return static::$connections[$dbKey];
    }
    /**
     * @param ConnectionConfig $config
     *
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws \Throwable
     */
    private static function createDb(ConnectionConfig $config) : \Generator
    {
        try {
            $db = $config->getDatabase();
            $user = $config->getUser();
            $connection = pool($config->withDatabase(null));
            $result = (yield $connection->query("SELECT * FROM pg_database WHERE datname = '{$db}'"));
            while ((yield $result->advance())) {
                $row = $result->getCurrent();
                if ($row === false) {
                    (yield $connection->query("\n                            CREATE DATABASE {$db}\n                            OWNER {$user}\n                            ENCODING utf8\n                        "));
                }
            }
            (yield $connection->query("\n                    CREATE OR REPLACE FUNCTION update_ts()\n                    RETURNS TRIGGER AS \$\$\n                    BEGIN\n                       IF row(NEW.*) IS DISTINCT FROM row(OLD.*) THEN\n                          NEW.ts = now(); \n                          RETURN NEW;\n                       ELSE\n                          RETURN OLD;\n                       END IF;\n                    END;\n                    \$\$ language 'plpgsql'\n                "));
            $connection->close();
        } catch (\Throwable $e) {
            Logger::log($e->getMessage(), Logger::ERROR);
        }
    }
}