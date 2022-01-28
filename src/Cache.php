<?php

declare(strict_types=1);

namespace Rabbit\Cache;

use Psr\SimpleCache\CacheInterface;
use Rabbit\Parser\ParserInterface;

/**
 * Class Cache
 * @package rabbit\cache
 */
class Cache implements CacheInterface
{
    private string $driver = 'memory';

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

    public function cache(string $key, callable $function, float $duration = 0, string $driver = 'memory'): mixed
    {
        $driver = $this->getDriver($driver);
        if ($driver->has($key)) {
            return $this->serializer ? $this->serializer->decode($driver->get($key)) : \msgpack_pack($driver->get($key));
        }
        $result = $function();
        $driver->set($key, $this->serializer ? $this->serializer->encode($result) : \msgpack_pack($result), $duration);
        return $result;
    }

    private function getDrivers(): array
    {
        return $this->drivers;
    }

    public function get($key, mixed $default = null): mixed
    {
        return $this->getDriver()->get($key, $default);
    }

    public function set($key, mixed $value, $ttl = null): bool
    {
        return $this->getDriver()->set($key, $value, $ttl);
    }

    public function delete($key): bool
    {
        return $this->getDriver()->delete($key);
    }

    public function clear(): bool
    {
        return $this->getDriver()->clear();
    }

    public function getMultiple($keys, mixed $default = null): iterable
    {
        return $this->getDriver()->getMultiple($keys, $default);
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return $this->getDriver()->setMultiple($values, $ttl);
    }

    public function deleteMultiple($keys): bool
    {
        return $this->getDriver()->deleteMultiple($keys);
    }

    public function has($key): bool
    {
        return $this->getDriver()->has($key);
    }
}
