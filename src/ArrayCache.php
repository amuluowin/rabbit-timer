<?php

declare(strict_types=1);

namespace Rabbit\Cache;

use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\App;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Parser\ParserInterface;
use Throwable;

class ArrayCache extends AbstractCache implements CacheInterface
{
    private array $tableInstance = [];
    private int $maxLive = 3000000;
    private float $gcSleep = 0.01;
    private int $gcProbability = 100;
    public function __construct(private ?ParserInterface $serializer = null)
    {
        parent::__construct();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->buildKey($key);
        $value = $this->getValue($key);
        if ($value === null) {
            return $default;
        } elseif ($this->serializer === null) {
            return unserialize($value);
        } else {
            return $this->serializer->decode($value);
        }
    }

    private function getValue(string $key, int $nowtime = null): ?string
    {
        if (empty($key)) {
            return '';
        }
        if (empty($nowtime)) {
            $nowtime = time();
        }
        $column = ArrayHelper::getValue($this->tableInstance, $key);
        if ($column === null) {
            return null;
        }

        if ($column['expire'] > 0 && $column['expire'] < $nowtime) {
            unset($this->tableInstance[$key]);
            return null;
        }

        return $column['data'];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $key = $this->buildKey($key);
        if ($this->serializer === null) {
            $value = serialize($value);
        } else {
            $value = $this->serializer->encode($value);
        }

        return $this->setValue($key, $value, $ttl);
    }

    private function setValue(string $key, string $value, null|int|\DateInterval $duration): bool
    {
        $this->gc();
        $duration = !empty($duration) && $duration > $this->maxLive ? $this->maxLive : $duration;
        $expire = $duration ? $duration + time() : 0;
        $this->tableInstance[$key] = [
            'expire' => $expire,
            'data' => $value
        ];
        return true;
    }

    private function gc(bool $force = false): void
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            $i = 100000;
            $table = $this->tableInstance;
            foreach ($table as $key => $column) {
                if ($column['expire'] > 0 && $column['expire'] < time()) {
                    unset($this->tableInstance[$key]);
                }
                $i--;
                if ($i <= 0) {
                    usleep((int)($this->gcSleep * 1000 * 1000));
                    $i = 100000;
                }
            }
        }
    }

    public function delete(string $key): bool
    {
        $this->buildKey($key);
        unset($this->tableInstance[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->tableInstance = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            $results[$key] = $default;
            $value = $this->getValue($this->buildKey($key));
            if ($this->serializer === null) {
                $results[$key] = unserialize($value);
            } else {
                $results[$key] = $this->serializer->decode($value);
            }
        }

        return $results;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $ret = true;
        foreach ($values as $key => $value) {
            if ($this->serializer === null) {
                $value = serialize($value);
            } else {
                $value = $this->serializer->encode($value);
            }
            $ret &= $this->setValue($this->buildKey($key), $value, $ttl);
        }

        return (bool)$ret;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->tableInstance[$this->buildKey($key)]);
        }
        return true;
    }

    public function has(string $key): bool
    {
        $key = $this->buildKey($key);
        return isset($this->tableInstance[$key]);
    }
}
