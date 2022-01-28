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

    public function get($key, mixed $default = null): mixed
    {
        return $this->yac->get($key);
    }

    public function set($key, $value, $ttl = null): bool
    {
        return $ttl === null ? $this->yac->set($key, $value) : $this->yac->set($key, $value, (int)$ttl);
    }

    public function delete($key): bool
    {
        return $this->yac->delete($key);
    }

    public function clear(): bool
    {
        return $this->yac->flush();
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return $this->yac->get($keys);
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return $ttl === null ? $this->yac->set($values) : $this->yac->set($values, $ttl);
    }

    public function deleteMultiple($keys): bool
    {
        return $this->yac->delete($keys);
    }

    public function has($key): bool
    {
        return $this->yac->get($key) !== false;
    }
}
