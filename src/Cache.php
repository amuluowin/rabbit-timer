<?php

declare(strict_types=1);

namespace Rabbit\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Rabbit\Parser\ParserInterface;

/**
 * Class Cache
 * @package rabbit\cache
 */
class Cache implements CacheInterface
{
    private string $driver = 'memory';

    /**
     * Cache constructor.
     * @param array $drivers
     * @param ParserInterface|null $serializer
     */
    public function __construct(private array $drivers, private ?ParserInterface $serializer = null)
    {
    }

    /**
     * @param string|null $driver
     * @return CacheInterface
     */
    public function getDriver(string $driver = null): CacheInterface
    {
        $currentDriver = $driver ?? $this->driver;
        $drivers = $this->getDrivers();
        if (!isset($drivers[$currentDriver])) {
            if (extension_loaded('yac')) {
                return new YacCache($driver);
            } else {
                throw new \InvalidArgumentException(sprintf('Driver %s not exist', $currentDriver));
            }
        }

        return $drivers[$currentDriver];
    }

    /**
     * @param string $key
     * @param callable $function
     * @param float $duration
     * @param string $driver
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function cache(string $key, callable $function, float $duration = 0, string $driver = 'memory')
    {
        $driver = $this->getDriver($driver);
        if ($driver->has($key)) {
            return $this->serializer ? $this->serializer->decode($driver->get($key)) : \igbinary_unserialize($driver->get($key));
        }
        $result = $function();
        $driver->set($key, $this->serializer ? $this->serializer->encode($result) : \igbinary_serialize($result), $duration);
        return $result;
    }

    /**
     * @return array
     */
    private function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        return $this->getDriver()->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->getDriver()->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function delete($key)
    {
        return $this->getDriver()->delete($key);
    }

    /**
     * @return bool
     */
    public function clear()
    {
        return $this->getDriver()->clear();
    }

    /**
     * @param iterable $keys
     * @param null $default
     * @return iterable
     * @throws InvalidArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        return $this->getDriver()->getMultiple($keys, $default);
    }

    /**
     * @param iterable $values
     * @param null $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null)
    {
        return $this->getDriver()->setMultiple($values, $ttl);
    }

    /**
     * @param iterable $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteMultiple($keys)
    {
        return $this->getDriver()->deleteMultiple($keys);
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function has($key)
    {
        return $this->getDriver()->has($key);
    }
}
