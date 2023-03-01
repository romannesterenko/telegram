<?php

namespace danog\MadelineProto\Db;

use Amp\Iterator;
use Amp\Producer;
use Amp\Promise;
use Amp\Success;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Database\Memory;
use function Amp\call;
/**
 * Memory database backend.
 */
class MemoryArray extends \ArrayIterator implements DbArray
{
    /**
     *
     */
    public function __construct($array = [], $flags = 0)
    {
        parent::__construct((array) $array, $flags | self::STD_PROP_LIST);
    }
    /**
     * Get instance.
     *
     * @param string $table
     * @param mixed $previous
     * @param Memory $settings
     * @return Promise<self>
     */
    public static function getInstance(string $table, $previous, $settings) : Promise
    {
        return call(static function () use($previous) {
            if ($previous instanceof MemoryArray) {
                return $previous;
            }
            if ($previous instanceof DbArray) {
                Logger::log("Loading database to memory. Please wait.", Logger::WARNING);
                if ($previous instanceof DriverArray) {
                    yield from $previous->initStartup();
                }
                $temp = (yield $previous->getArrayCopy());
                (yield $previous->clear());
                $previous = $temp;
            }
            return new static($previous);
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
        parent::offsetSet($key, $value);
        return new Success();
    }
    /**
     * @param (int | string) $key
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
        return new Success(parent::offsetExists($key));
    }
    /**
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
        parent::offsetUnset($key);
        return new Success();
    }
    /**
     * @param mixed $offset
     */
    public function offsetExists($offset) : bool
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($offset) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($offset) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        throw new \RuntimeException('Native isset not support promises. Use isset method');
    }
    /**
     * @param mixed $offset
     */
    public function offsetGet($offset) : Promise
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($offset) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($offset) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        return new Success(parent::offsetExists($offset) ? parent::offsetGet($offset) : null);
    }
    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset) : void
    {
        if (!true) {
            throw new \TypeError(__METHOD__ . '(): Argument #1 ($offset) must be of type mixed, ' . \Phabel\Plugin\TypeHintReplacer::getDebugType($offset) . ' given, called in ' . \Phabel\Plugin\TypeHintReplacer::trace());
        }
        parent::offsetUnset($offset);
    }
    /**
     *
     */
    #[\ReturnTypeWillChange]
    public function count() : Promise
    {
        return new Success(parent::count());
    }
    /**
     *
     */
    #[\ReturnTypeWillChange]
    public function getArrayCopy() : Promise
    {
        return new Success(parent::getArrayCopy());
    }
    /**
     *
     */
    public function clear() : Promise
    {
        parent::__construct([], parent::getFlags());
        return new Success();
    }
    /**
     *
     */
    public function getIterator() : Iterator
    {
        return new Producer(function (callable $emit) {
            foreach ($this as $key => $value) {
                (yield $emit([$key, $value]));
            }
        });
    }
}