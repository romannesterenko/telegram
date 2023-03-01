<?php

namespace danog\MadelineProto\Db;

use Amp\Iterator;
use Amp\Producer;
use Amp\Promise;
use Amp\Sql\CommandResult;
use Amp\Sql\Pool;
use Amp\Sql\ResultSet;
use Amp\Success;
use Throwable;
use Webmozart\Assert\Assert;
use function Amp\call;
/**
 * Generic SQL database backend.
 */
abstract class SqlArray extends DriverArray
{
    /**
     *
     */
    protected Pool $db;
    //Pdo driver used for value quoting, to prevent sql injections.
    /**
     *
     */
    protected \PDO $pdo;
    protected const SQL_GET = 0;
    protected const SQL_SET = 1;
    protected const SQL_UNSET = 2;
    protected const SQL_COUNT = 3;
    protected const SQL_ITERATE = 4;
    protected const SQL_CLEAR = 5;
    /**
     * @var array<self::SQL_*, string>
     */
    private array $queries = [];
    /**
     * Prepare statements.
     *
     * @param SqlArray::SQL_* $type
     *
     * @return string
     */
    protected abstract function getSqlQuery(int $type) : string;
    /**
     *
     */
    public function setTable(string $table) : DriverArray
    {
        $this->table = $table;
        $this->queries = [self::SQL_GET => $this->getSqlQuery(self::SQL_GET), self::SQL_SET => $this->getSqlQuery(self::SQL_SET), self::SQL_UNSET => $this->getSqlQuery(self::SQL_UNSET), self::SQL_COUNT => $this->getSqlQuery(self::SQL_COUNT), self::SQL_ITERATE => $this->getSqlQuery(self::SQL_ITERATE), self::SQL_CLEAR => $this->getSqlQuery(self::SQL_CLEAR)];
        return $this;
    }
    /**
     * Deserialize retrieved value.
     * @return mixed
     */
    protected function getValue(string $value)
    {
        $phabelReturn = \unserialize($value);
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Return value must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($phabelReturn) . ' returned in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        return $phabelReturn;
    }
    /**
     * Serialize retrieved value.
     * @param mixed $value
     */
    protected function setValue($value) : string
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($value) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($value) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        return \serialize($value);
    }
    /**
     * @return Iterator<array{0: string, 1: mixed}>
     */
    public function getIterator() : Iterator
    {
        return new Producer(function (callable $emit) {
            $request = (yield $this->execute($this->queries[self::SQL_ITERATE]));
            while ((yield $request->advance())) {
                $row = $request->getCurrent();
                (yield $emit([$row['key'], $this->getValue($row['value'])]));
            }
        });
    }
    /**
     *
     */
    #[\ReturnTypeWillChange]
    public function getArrayCopy() : Promise
    {
        return call(function () {
            $iterator = $this->getIterator();
            $result = [];
            while ((yield $iterator->advance())) {
                [$key, $value] = $iterator->getCurrent();
                $result[$key] = $value;
            }
            return $result;
        });
    }
    /**
     * @param mixed $key
     */
    public function offsetGet($key) : Promise
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($key) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($key) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        $key = (string) $key;
        if ($this->hasCache($key)) {
            return new Success($this->getCache($key));
        }
        return call(function () use($key) {
            $row = (yield $this->execute($this->queries[self::SQL_GET], ['index' => $key]));
            if (!(yield $row->advance())) {
                return null;
            }
            $row = $row->getCurrent();
            if ($value = $this->getValue($row['value'])) {
                $this->setCache($key, $value);
            }
            return $value;
        });
    }
    /**
     * @param (int | string) $key
     * @param mixed $value
     */
    public function set($key, $value) : Promise
    {
        if (!(\is_int($key) || \is_string($key))) {
            if (!(\is_string($key) || \is_object($key) && \method_exists($key, '__toString') || (\is_bool($key) || \is_numeric($key)))) {
                if (!(\is_bool($key) || \is_numeric($key))) {
                    throw new \TypeError(__METHOD__ . '(): Argument #1 ($key) must be of type int|string, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($key) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
                } else {
                    $key = (int) $key;
                }
            } else {
                $key = (string) $key;
            }
        }
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #2 ($value) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($value) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        $key = (string) $key;
        if ($this->hasCache($key) && $this->getCache($key) === $value) {
            return new Success();
        }
        $this->setCache($key, $value);
        $request = $this->execute($this->queries[self::SQL_SET], ['index' => $key, 'value' => $this->setValue($value)]);
        //Ensure that cache is synced with latest insert in case of concurrent requests.
        $request->onResolve(function (?Throwable $err, ?CommandResult $result) use($key, $value) {
            if ($err) {
                throw $err;
            }
            Assert::greaterThanEq($result->getAffectedRowCount(), 1);
            $this->setCache($key, $value);
        });
        return $request;
    }
    /**
    * Unset value for an offset.
    *
    * @link https://php.net/manual/en/arrayiterator.offsetunset.php
    *
    * @param (string | int) $index <p>
    The offset to unset.
    </p>
    *
    * @return Promise<array>
    * @throws \Throwable
    * @param (int | string) $key
    */
    public function unset($key) : Promise
    {
        if (!(\is_int($key) || \is_string($key))) {
            if (!(\is_string($key) || \is_object($key) && \method_exists($key, '__toString') || (\is_bool($key) || \is_numeric($key)))) {
                if (!(\is_bool($key) || \is_numeric($key))) {
                    throw new \TypeError(__METHOD__ . '(): Argument #1 ($key) must be of type int|string, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($key) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
                } else {
                    $key = (int) $key;
                }
            } else {
                $key = (string) $key;
            }
        }
        $key = (string) $key;
        $this->unsetCache($key);
        return $this->execute($this->queries[self::SQL_UNSET], ['index' => $key]);
    }
    /**
    * Count elements.
    *
    * @link https://php.net/manual/en/arrayiterator.count.php
    * @return Promise<int> The number of elements or public properties in the associated
    array or object, respectively.
    * @throws \Throwable
    */
    public function count() : Promise
    {
        return call(function () {
            /** @var ResultSet */
            $row = (yield $this->execute($this->queries[self::SQL_COUNT]));
            Assert::true((yield $row->advance()));
            return $row->getCurrent()['count'];
        });
    }
    /**
     * Clear all elements.
     *
     * @return Promise<CommandResult>
     */
    public function clear() : Promise
    {
        $this->clearCache();
        return $this->execute($this->queries[self::SQL_CLEAR]);
    }
    /**
     * Perform async request to db.
     *
     * @param string $sql
     * @param array $params
     *
     * @psalm-param self::STATEMENT_* $stmt
     *
     * @return Promise<(CommandResult | ResultSet)>
     * @throws \Throwable
     */
    protected function execute(string $sql, array $params = []) : Promise
    {
        if (isset($params['index']) && !\mb_check_encoding($params['index'], 'UTF-8')) {
            $params['index'] = \mb_convert_encoding($params['index'], 'UTF-8');
        }
        foreach ($params as $key => $value) {
            $value = $this->pdo->quote($value);
            $sql = \str_replace(":{$key}", $value, $sql);
        }
        return $this->db->query($sql);
    }
}