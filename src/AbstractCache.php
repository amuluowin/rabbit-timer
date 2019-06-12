<?php


namespace rabbit\cache;

use rabbit\helper\StringHelper;

/**
 * Class AbstractCache
 * @package rabbit\cache
 */
class AbstractCache
{
    /**
     * @var bool whether [igbinary serialization](https://pecl.php.net/package/igbinary) is available or not.
     */
    private $_igbinaryAvailable = false;

    /**
     * AbstractCache constructor.
     */
    public function __construct()
    {
        $this->_igbinaryAvailable = \extension_loaded('igbinary');
    }

    /**
     * @param $key
     * @return string
     */
    protected function buildKey($key): string
    {
        if (is_string($key)) {
            $key = StringHelper::byteLength($key) <= 32 ? $key : md5($key);
        } else {
            if ($this->_igbinaryAvailable) {
                $serializedKey = igbinary_serialize($key);
            } else {
                $serializedKey = serialize($key);
            }

            $key = md5($serializedKey);
        }

        return $key;
    }

    /**
     * @param $ttl
     * @return int
     */
    protected function getTtl($ttl): int
    {
        return ($ttl === null) ? 0 : (int)$ttl;
    }
}