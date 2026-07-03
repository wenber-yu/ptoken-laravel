<?php

declare(strict_types=1);

namespace Wenbo\PToken\Laravel\CacheDrivers;

use Wenbo\PToken\CacheDrivers\PTokenCacheInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Laravel 缓存适配器，将 Laravel Illuminate Cache Repository 适配到 PTokenCacheInterface。
 */
class PTokenCacheDriver implements PTokenCacheInterface
{
    private Repository $store;

    public function __construct(Repository $store)
    {
        $this->store = $store;
    }

    public function get(string $key): mixed
    {
        return $this->store->get($key);
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        $this->store->put($key, $value, $ttl);
        return true;
    }

    public function delete(string $key): bool
    {
        return $this->store->forget($key);
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }
}
