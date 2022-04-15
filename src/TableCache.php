<?php

declare(strict_types=1);

namespace Rabbit\Cache;

use Psr\SimpleCache\CacheInterface;
use Rabbit\Parser\ParserInterface;
use Swoole\Table;

class TableCache extends AbstractCache implements CacheInterface
{
    private Table $tableInstance;
    private int $maxLive = 3000000;
    private int $gcSleep = 100;
    private int $gcProbability = 100;

    public function __construct(int $size = 1024, private int $dataLength = 8192, private ?ParserInterface $serializer = null)
    {
        parent::__construct();
        $table = new Table($size);
        $table->column('expire', Table::TYPE_STRING, 11);
        $table->column('nextId', Table::TYPE_STRING, 35);
        $table->column('data', Table::TYPE_STRING, $dataLength);
        $table->create();
        $this->tableInstance = $table;
    }

    public function get($key, mixed $default = null): mixed
    {
        $key = $this->buildKey($key);
        $value = $this->getValue($key);
        if ($value === false) {
            return $default;
        } elseif ($this->serializer === null) {
            return unserialize($value);
        } else {
            return $this->serializer->decode($value);
        }
    }

    private function getValue(string $key, int $nowtime = null): bool|string
    {
        if (empty($key)) {
            return '';
        }
        if (empty($nowtime)) {
            $nowtime = time();
        }
        $column = $this->tableInstance->get($key);
        if ($column === false) {
            return false;
        }

        if ($column['expire'] > 0 && $column['expire'] < $nowtime) {
            $this->deleteValue($key);
            return false;
        }
        $nextValue = $this->getValue($column['nextId'], $nowtime);
        if ($nextValue === false) {
            $this->tableInstance->del($key);
            return false;
        }

        return $column['data'] . $nextValue;
    }

    private function deleteValue(string $key): bool
    {
        $column = $column = $this->tableInstance->get($key);
        if ($column) {
            $nextId = $column['nextId'];
            unset($column);
            $nextId && $this->deleteValue($nextId);
        }
        return $this->tableInstance->del($key);
    }

    public function set($key, mixed $value, $ttl = null): bool
    {
        $key = $this->buildKey($key);
        if ($this->serializer === null) {
            $value = serialize($value);
        } else {
            $value = $this->serializer->encode($value);
        }

        return $this->setValue($key, $value, $ttl);
    }

    private function setValue(string $key, string $value, null|int|float $duration): bool
    {
        $this->gc();
        $duration = !empty($duration) && $duration > $this->maxLive ? $this->maxLive : $duration;
        $expire = $duration ? $duration + time() : 0;
        $valueLength = strlen($value);
        return $this->setValueRec($key, $value, $expire, $valueLength) !== null;
    }

    private function gc(bool $force = false): void
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            $i = 100000;
            $dels = [];
            foreach ($this->tableInstance as $key => $column) {
                if ($column['expire'] > 0 && $column['expire'] < time()) {
                    $dels[] = $key;
                }
                $i--;
                if ($i <= 0) {
                    usleep($this->gcSleep);
                    $i = 100000;
                }
            }
            foreach ($dels as $key) {
                $this->deleteValue($key);
            }
        }
    }

    private function setValueRec(string $key, string &$value, ?float $expire, int $valueLength, int $num = 0): ?string
    {
        $start = $num * $this->dataLength;
        if ($start > $valueLength) {
            return '';
        }
        $nextNum = $num + 1;
        $nextId = $this->setValueRec($key, $value, $expire, $valueLength, $nextNum);
        if ($nextId === null) {
            return null;
        }
        if ($num) {
            $setKey = $key . $num;
        } else {
            $setKey = $key;
        }
        $result = $this->tableInstance->set($setKey, [
            'expire' => $expire,
            'nextId' => $nextId,
            'data' => substr($value, $start, $this->dataLength)
        ]);

        if ($result === false) {
            if ($nextId) {
                $this->deleteValue($nextId);
            }
            return null;
        }
        return $setKey;
    }

    public function delete($key): bool
    {
        $this->buildKey($key);
        return $this->deleteValue($key);
    }

    public function clear(): bool
    {
        $table = [];
        foreach ($this->tableInstance as $key => $column) {
            $table[] = $key;
        }
        $ret = true;
        foreach ($table as $key) {
            $ret &= $this->tableInstance->del($key);
        }
        return (bool)$ret;
    }

    public function getMultiple($keys, mixed $default = null): iterable
    {
        $newKeys = [];
        foreach ($keys as $key) {
            $newKeys[$key] = $this->buildKey($key);
        }
        $values = $this->getValues(array_values($newKeys));
        $results = [];
        foreach ($newKeys as $key => $newKey) {
            $results[$key] = false;
            if (isset($values[$newKey])) {
                if ($this->serializer === null) {
                    $results[$key] = unserialize($values[$newKey]);
                } else {
                    $results[$key] = $this->serializer->decode($values[$newKey]);
                }
            }
        }

        return $results;
    }

    protected function getValues(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->getValue($key);
        }

        return $results;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $data = [];
        foreach ($values as $key => $value) {
            if ($this->serializer === null) {
                $value = serialize($value);
            } else {
                $value = $this->serializer->encode($value);
            }
            $data[$this->buildKey($key)] = $value;
        }

        return $this->setValues($data, $ttl);
    }

    private function setValues(array $data, null|int|float $duration): bool
    {
        $ret = true;
        foreach ($data as $key => $value) {
            $ret &= $this->setValue($key, $value, $duration);
        }

        return (bool)$ret;
    }

    public function deleteMultiple($keys): bool
    {
        $ret = true;
        foreach ($keys as $key) {
            $ret &= $this->deleteValue($this->buildKey($key));
        }

        return (bool)$ret;
    }

    public function has($key): bool
    {
        $key = $this->buildKey($key);
        $value = $this->getValue($key);

        return $value !== false;
    }
}
