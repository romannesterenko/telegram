<?php

namespace danog\MadelineProto\Db;

use Amp\Promise;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Database\DatabaseAbstract;
use danog\MadelineProto\Settings\Database\Memory;
use danog\MadelineProto\SettingsAbstract;
use ReflectionClass;
use function Amp\call;
/**
 * Array caching trait.
 */
abstract class DriverArray implements DbArray
{
    /**
     *
     */
    protected string $table;
    use ArrayCacheTrait;
    /**
     * Initialize connection.
     */
    public abstract function initConnection(DatabaseAbstract $settings) : \Generator;
    /**
     * Initialize on startup.
     *
     * @return \Generator
     */
    public abstract function initStartup() : \Generator;
    /**
     * Create table for property.
     *
     * @return \Generator
     *
     * @throws \Throwable
     */
    protected abstract function prepareTable() : \Generator;
    /**
     * Rename table.
     *
     * @param string $from
     * @param string $to
     * @return \Generator
     */
    protected abstract function renameTable(string $from, string $to) : \Generator;
    /**
     * Get the value of table.
     *
     * @return string
     */
    public function getTable() : string
    {
        return $this->table;
    }
    /**
     * Set the value of table.
     *
     * @param string $table
     *
     * @return self
     */
    public function setTable(string $table) : self
    {
        $this->table = $table;
        return $this;
    }
    /**
     * Check if key isset.
     *
     * @param mixed $key
     *
     * @return Promise<bool> true if the offset exists, otherwise false
     */
    public function isset($key) : Promise
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
        return call(function () use($key) {
            return null !== (yield $this->offsetGet($key));
        });
    }
    /**
     * @param string $table
     * @param (DbArray | array | null) $previous
     * @param DatabaseAbstract $settings
     *
     * @return Promise
     *
     * @psalm-return Promise<static>
     */
    public static function getInstance(string $table, $previous, $settings) : Promise
    {
        $instance = new static();
        $instance->setTable($table);
        /** @psalm-suppress UndefinedPropertyAssignment */
        $instance->dbSettings = $settings;
        $instance->setCacheTtl($settings->getCacheTtl());
        $instance->startCacheCleanupLoop();
        return call(static function () use($instance, $previous, $settings) {
            yield from $instance->initConnection($settings);
            yield from $instance->prepareTable();
            if (static::getClassName($previous) !== static::getClassName($instance)) {
                if ($previous instanceof DriverArray) {
                    yield from $previous->initStartup();
                }
                yield from static::renameTmpTable($instance, $previous);
                yield from static::migrateDataToDb($instance, $previous);
            }
            return $instance;
        });
    }
    /**
     * Rename table of old database, if the new one is not a temporary table name.
     *
     * Otherwise, simply change name of table in new database to match old table name.
     *
     * @param self $new New db
     * @param (DbArray | array | null) $old Old db
     *
     * @return \Generator
     */
    protected static function renameTmpTable(self $new, $old) : \Generator
    {
        if ($old instanceof SqlArray && $old->getTable()) {
            if ($old->getTable() !== $new->getTable() && !\str_starts_with($new->getTable(), 'tmp')) {
                yield from $new->renameTable($old->getTable(), $new->getTable());
            } else {
                $new->setTable($old->getTable());
            }
        }
    }
    /**
     * @param self $new
     * @param (DbArray | array | null) $old
     *
     * @return \Generator
     * @throws \Throwable
     */
    protected static function migrateDataToDb(self $new, $old) : \Generator
    {
        if (!empty($old) && static::getClassName($old) !== static::getClassName($new)) {
            if (!$old instanceof DbArray) {
                $old = (yield MemoryArray::getInstance('', $old, new Memory()));
            }
            Logger::log('Converting ' . \get_class($old) . ' to ' . \get_class($new), Logger::ERROR);
            $counter = 0;
            $total = (yield $old->count());
            $iterator = $old->getIterator();
            while ((yield $iterator->advance())) {
                $counter++;
                if ($counter % 500 === 0 || $counter === $total) {
                    (yield $new->set(...$iterator->getCurrent()));
                    Logger::log("Loading data to table {$new}: {$counter}/{$total}", Logger::WARNING);
                } else {
                    $new->set(...$iterator->getCurrent());
                }
                $new->clearCache();
            }
            (yield $old->clear());
            Logger::log('Converting database done.', Logger::ERROR);
        }
    }
    /**
     *
     */
    public function __destruct()
    {
        $this->stopCacheCleanupLoop();
    }
    /**
     * Get the value of table.
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->table;
    }
    /**
     * Sleep function.
     *
     * @return array
     */
    public function __sleep() : array
    {
        return ['table', 'dbSettings'];
    }
    /**
     *
     */
    public function __wakeup()
    {
        if (isset($this->settings) && \is_array($this->settings)) {
            $clazz = (new ReflectionClass($this))->getProperty('dbSettings')->getType()->getName();
            /**
             * @var SettingsAbstract
             * @psalm-suppress UndefinedThisPropertyAssignment
             */
            $this->dbSettings = new $clazz();
            $this->dbSettings->mergeArray($this->settings);
            unset($this->settings);
        }
    }
    /**
     *
     */
    public final function offsetExists($index) : bool
    {
        throw new \RuntimeException('Native isset not support promises. Use isset method');
    }
    /**
     * @param mixed $index
     * @param mixed $value
     */
    public final function offsetSet($index, $value) : void
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($index) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($index) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #2 ($value) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($value) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        $this->set($index, $value);
    }
    /**
     * @param mixed $index
     */
    public final function offsetUnset($index) : void
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($index) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($index) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        $this->unset($index);
    }
    /**
     *
     */
    protected static function getClassName($instance) : ?string
    {
        if ($instance === null) {
            return null;
        } elseif (\is_array($instance)) {
            return 'Array';
        }
        return \str_replace('NullCache\\', '', \get_class($instance));
    }
}