<?php

namespace danog\MadelineProto\Db;

use Amp\Mysql\ConnectionConfig;
use Amp\Promise;
use danog\MadelineProto\Db\Driver\Mysql;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Database\Mysql as DatabaseMysql;
/**
 * MySQL database backend.
 */
class MysqlArray extends SqlArray
{
    /**
     *
     */
    protected DatabaseMysql $dbSettings;
    // Legacy
    /**
     *
     */
    protected array $settings;
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
                return "SELECT `value` FROM `{$this->table}` WHERE `key` = :index LIMIT 1";
            case SqlArray::SQL_SET:
                return "\n                    REPLACE INTO `{$this->table}` \n                    SET `key` = :index, `value` = :value \n                ";
            case SqlArray::SQL_UNSET:
                return "\n                    DELETE FROM `{$this->table}`\n                    WHERE `key` = :index\n                ";
            case SqlArray::SQL_COUNT:
                return "\n                    SELECT count(`key`) as `count` FROM `{$this->table}`\n                ";
            case SqlArray::SQL_ITERATE:
                return "\n                    SELECT `key`, `value` FROM `{$this->table}`\n                ";
            case SqlArray::SQL_CLEAR:
                return "\n                    DELETE FROM `{$this->table}`\n                ";
        }
        throw new Exception("An invalid statement type {$type} was provided!");
    }
    /**
     * Initialize connection.
     *
     * @param DatabaseMysql $settings
     * @return \Generator
     */
    public function initConnection($settings) : \Generator
    {
        $config = ConnectionConfig::fromString("host=" . \str_replace("tcp://", "", $settings->getUri()));
        $host = $config->getHost();
        $port = $config->getPort();
        $this->pdo = new \PDO("mysql:host={$host};port={$port};charset=UTF8", $settings->getUsername(), $settings->getPassword());
        if (!isset($this->db)) {
            $this->db = (yield from Mysql::getConnection($settings));
        }
    }
    /**
     * Create table for property.
     *
     * @return \Generator
     *
     * @throws \Throwable
     *
     * @psalm-return \Generator<int, Promise, mixed, mixed>
     */
    protected function prepareTable() : \Generator
    {
        Logger::log("Creating/checking table {$this->table}", Logger::WARNING);
        return (yield $this->db->query("\n            CREATE TABLE IF NOT EXISTS `{$this->table}`\n            (\n                `key` VARCHAR(255) NOT NULL,\n                `value` MEDIUMBLOB NULL,\n                `ts` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (`key`)\n            )\n            ENGINE = InnoDB\n            CHARACTER SET 'utf8mb4' \n            COLLATE 'utf8mb4_general_ci'\n        "));
    }
    /**
     *
     */
    protected function renameTable(string $from, string $to) : \Generator
    {
        Logger::log("Moving data from {$from} to {$to}", Logger::WARNING);
        (yield $this->db->query("\n            REPLACE INTO `{$to}`\n            SELECT * FROM `{$from}`;\n        "));
        (yield $this->db->query("\n            DROP TABLE `{$from}`;\n        "));
    }
}