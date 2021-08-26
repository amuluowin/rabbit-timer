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
    private ?ParserInterface $serializer;
    private array $tableInstance = [];
    private int $maxLive = 3000000;
    private float $gcSleep = 0.01;
    private int $gcProbability = 100;
    public function __construct(ParserInterface $serializer = null)
    {
        parent::__construct();
        $this->serializer = $serializer;
    }

    /**
     * @param string $key
     * @param null $default
     * @return bool|mixed|null|string
     */
    public function get($key, $default = null)
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

    /**
     * @param string $key
     * @param int $nowtime
     * @return bool|string
     */
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
            $this->tableInstance = array_slice($this->tableInstance, 0, null, true);
            return null;
        }

        return $column['data'];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $ttl
     * @return bool
     * @throws Throwable
     */
    public function set($key, $value, $ttl = null)
    {
        $key = $this->buildKey($key);
        if ($this->serializer === null) {
            $value = serialize($value);
        } else {
            $value = $this->serializer->encode($value);
        }

        return $this->setValue($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @param string $value
     * @param float|null $duration
     * @return bool
     * @throws Throwable
     */
    private function setValue(string $key, string $value, ?float $duration): bool
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

    /**
     * @param bool $force
     * @throws Throwable
     */
    private function gc(bool $force = false)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            App::debug("ArrayCache GC begin");
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
            $this->tableInstance = array_slice($this->tableInstance, 0, null, true);
            App::debug("ArrayCache GC end.");
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        $this->buildKey($key);
        unset($this->tableInstance[$key]);
        $this->tableInstance = array_slice($this->tableInstance, 0, null, true);
        return true;
    }

    /**
     * @return bool|void
     */
    public function clear()
    {
        $this->tableInstance = [];
    }

    /**
     * @param iterable $keys
     * @param null $default
     * @return array|iterable
     */
    public function getMultiple($keys, $default = null)
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

    /**
     * @param iterable $values
     * @param null $ttl
     * @return array|bool
     * @throws Throwable
     */
    public function setMultiple($values, $ttl = null)
    {
        $failedKeys = [];
        foreach ($values as $key => $value) {
            if ($this->serializer === null) {
                $value = serialize($value);
            } else {
                $value = $this->serializer->encode($value);
            }
            $this->setValue($this->buildKey($key), $value, $ttl);
            $failedKeys[] = $key;
        }

        return $failedKeys;
    }

    /**
     * @param iterable $keys
     * @return array|bool
     */
    public function deleteMultiple($keys)
    {
        $failedKeys = [];
        foreach ($keys as $key) {
            unset($this->tableInstance[$this->buildKey($key)]);
            $failedKeys[] = $key;
        }
        $this->tableInstance = array_slice($this->tableInstance, 0, null, true);
        return $failedKeys;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        $key = $this->buildKey($key);
        return isset($this->tableInstance[$key]);
    }
}
