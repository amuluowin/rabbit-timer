<?php

declare(strict_types=1);

namespace Rabbit\Cache;

use Psr\SimpleCache\CacheInterface;
use Yac;

class YacCache extends AbstractCache implements CacheInterface
{
    private Yac $yac;

    public function __construct(string $key = null)
    {
        $this->yac = new Yac($key ?? 'yac:');
    }

    public function get($key, $default = null)
    {
        return $this->yac->get($key);
    }

    public function set($key, $value, $ttl = null)
    {
        return $ttl === null ? $this->yac->set($key, $value) : $this->yac->set($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->yac->delete($key);
    }

    public function clear()
    {
        return $this->yac->flush();
    }

    public function getMultiple($keys, $default = null)
    {
        return $this->yac->get($keys);
    }

    public function setMultiple($values, $ttl = null)
    {
        return $ttl === null ? $this->yac->set($values) : $this->yac->set($values, $ttl);
    }

    public function deleteMultiple($keys)
    {
        return $this->yac->delete($keys);
    }

    public function has($key)
    {
        return $this->yac->get($key) === false;
    }
}
