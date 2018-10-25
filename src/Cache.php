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
class Cache
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
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $availableMethods = [
            'has',
            'get',
            'set',
            'delete',
            'getMultiple',
            'setMultiple',
            'deleteMultiple',
            'clear',
        ];
        if (!\in_array($method, $availableMethods, true)) {
            throw new \RuntimeException(sprintf('Method not exist, method=%s', $method));
        }
        $driver = $this->getDriver();
        return $driver->$method(...$arguments);
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

        //TODO If driver component not loaded, throw an exception.

        $bean = ObjectFactory::get($drivers[$currentDriver]);
        return $bean;
    }

    /**
     * @return array
     */
    private function getDrivers(): array
    {
        return $this->drivers;
    }

}