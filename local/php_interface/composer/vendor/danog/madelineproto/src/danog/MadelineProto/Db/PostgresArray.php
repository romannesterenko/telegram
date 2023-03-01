<?php

namespace danog\MadelineProto\Db;

use Amp\Postgres\ConnectionConfig;
use Amp\Promise;
use danog\MadelineProto\Db\Driver\Postgres;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Database\Postgres as DatabasePostgres;
/**
 * Postgres database backend.
 */
class PostgresArray extends SqlArray
{
    /**
     *
     */
    public DatabasePostgres $dbSettings;
    // Legacy
    /**
     *
     */
    protected array $settings;
    /**
     * Prepare statements.
     *
     * @param SqlArray::STATEMENT_* $type
     *
     * @return string
     */
    protected function getSqlQuery(int $type) : string
    {
        switch ($type) {
            case SqlArray::SQL_GET:
                return "SELECT value FROM \"{$this->table}\" WHERE key = :index";
            case SqlArray::SQL_SET:
                return "\n                INSERT INTO \"{$this->table}\"\n                (key,value)\n                VALUES (:index, :value)\n                ON CONFLICT (key) DO UPDATE SET value = :value\n            ";
            case SqlArray::SQL_UNSET:
                return "\n                DELETE FROM \"{$this->table}\"\n                WHERE key = :index\n            ";
            case SqlArray::SQL_COUNT:
                return "\n                SELECT count(key) as count FROM \"{$this->table}\"\n            ";
            case SqlArray::SQL_ITERATE:
                return "\n                SELECT key, value FROM \"{$this->table}\"\n            ";
            case SqlArray::SQL_CLEAR:
                return "\n                DELETE FROM \"{$this->table}\"\n            ";
        }
        throw new Exception("An invalid statement type {$type} was provided!");
    }
    /**
     * Initialize on startup.
     *
     * @return \Generator
     */
    public function initStartup() : \Generator
    {
        $this->setTable($this->table);
        yield from $this->initConnection($this->dbSettings);
    }
    /**
     * Initialize connection.
     *
     * @param DatabasePostgres $settings
     * @return \Generator
     */
    public function initConnection($settings) : \Generator
    {
        $config = ConnectionConfig::fromString("host=" . \str_replace("tcp://", "", $settings->getUri()));
        $host = $config->getHost();
        $port = $config->getPort();
        $this->pdo = new \PDO("pgsql:host={$host};port={$port}", $settings->getUsername(), $settings->getPassword());
        if (!isset($this->db)) {
            $this->db = (yield from Postgres::getConnection($settings));
        }
    }
    /**
     * @return mixed
     */
    protected function getValue(string $value)
    {
        $phabelReturn = \unserialize(\hex2bin($value));
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Return value must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($phabelReturn) . ' returned in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        return $phabelReturn;
    }
    /**
     * @param mixed $value
     */
    protected function setValue($value) : string
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($value) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($value) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        return \bin2hex(\serialize($value));
    }
    /**
     * Create table for property.
     *
     * @return \Generator
     *
     * @throws \Throwable
     *
     * @psalm-return \Generator<int, Promise, mixed, void>
     */
    protected function prepareTable() : \Generator
    {
        Logger::log("Creating/checking table {$this->table}", Logger::WARNING);
        (yield $this->db->query("\n            CREATE TABLE IF NOT EXISTS \"{$this->table}\"\n            (\n                \"key\" VARCHAR(255) PRIMARY KEY NOT NULL,\n                \"value\" TEXT NOT NULL\n            );            \n        "));
    }
    /**
     *
     */
    protected function renameTable(string $from, string $to) : \Generator
    {
        Logger::log("Moving data from {$from} to {$to}", Logger::WARNING);
        (yield $this->db->query(
            /** @lang PostgreSQL */
            "\n            ALTER TABLE \"{$from}\" RENAME TO \"{$to}\";\n        "
        ));
    }
}