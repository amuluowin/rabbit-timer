<?php

declare(strict_types=1);

namespace Rabbit\Cache;

use Rabbit\Base\Helper\StringHelper;
use function extension_loaded;

/**
 * Class AbstractCache
 * @package rabbit\cache
 */
class AbstractCache
{
    protected readonly bool $msgAvailable;

    public function __construct()
    {
        $this->msgAvailable = extension_loaded('msgpack');
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
            if ($this->msgAvailable) {
                $serializedKey = \msgpack_pack($key);
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
