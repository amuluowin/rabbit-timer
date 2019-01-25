<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/24
 * Time: 18:00
 */

namespace rabbit\cache;

use Psr\SimpleCache\CacheInterface;
use rabbit\core\ObjectFactory;
use rabbit\parser\ParserInterface;
use Swoole\Table;

/**
 * Class Cache
 * @package rabbit\cache
 */
class Cache implements CacheInterface
{
    /**
     * @var string
     */
    private $driver = 'memory';

    /**
     * @var array
     */
    private $drivers = [];

    /**
     * @var ParserInterface|null
     */
    private $serializer = null;

    /**
     * Cache constructor.
     */
    public function __construct(array $drivers)
    {
        $this->serializer = ObjectFactory::get('cache.serializer ', false);
        $this->drivers = $drivers;
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
            throw new \InvalidArgumentException(sprintf('Driver %s not exist', $currentDriver));
        }

        return $drivers[$currentDriver];
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
     * @return mixed|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        $this->getDriver()->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $ttl
     * @return bool|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set($key, $value, $ttl = null)
    {
        $this->getDriver()->set($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return bool|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete($key)
    {
        $this->getDriver()->delete($key);
    }

    /**
     * @return bool|void
     */
    public function clear()
    {
        $this->getDriver()->clear();
    }

    /**
     * @param iterable $keys
     * @param null $default
     * @return iterable|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        $this->getDriver()->getMultiple($keys, $default);
    }

    /**
     * @param iterable $values
     * @param null $ttl
     * @return bool|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->getDriver()->setMultiple($values, $ttl);
    }

    /**
     * @param iterable $keys
     * @return bool|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function deleteMultiple($keys)
    {
        $this->getDriver()->deleteMultiple($keys);
    }

    /**
     * @param string $key
     * @return bool|void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function has($key)
    {
        $this->getDriver()->has($key);
    }
}