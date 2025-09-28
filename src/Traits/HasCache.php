<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Traits;

use DateInterval;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Trait providing caching functionality.
 */
trait HasCache
{
    protected ?CacheItemPoolInterface $cache = null;

    /**
     * Set the cache instance.
     */
    public function setCache(?CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Get the cache instance.
     */
    public function getCache(): ?CacheItemPoolInterface
    {
        return $this->cache;
    }

    /**
     * Check if caching is enabled.
     */
    public function hasCache(): bool
    {
        return $this->cache !== null;
    }

    /**
     * Get an item from cache.
     */
    protected function getFromCache(string $key): mixed
    {
        if (!$this->hasCache()) {
            return null;
        }

        try {
            $item = $this->cache->getItem($key);
            return $item->isHit() ? $item->get() : null;
        } catch (\Throwable $e) {
            $this->logWarning('Cache read failed', ['key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store an item in cache.
     */
    protected function storeInCache(string $key, mixed $value, ?DateInterval $ttl = null): bool
    {
        if (!$this->hasCache()) {
            return false;
        }

        try {
            $item = $this->cache->getItem($key);
            $item->set($value);

            if ($ttl !== null) {
                $item->expiresAfter($ttl);
            }

            return $this->cache->save($item);
        } catch (\Throwable $e) {
            $this->logWarning('Cache write failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete an item from cache.
     */
    protected function deleteFromCache(string $key): bool
    {
        if (!$this->hasCache()) {
            return false;
        }

        try {
            return $this->cache->deleteItem($key);
        } catch (\Throwable $e) {
            $this->logWarning('Cache delete failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear all cache.
     */
    protected function clearCache(): bool
    {
        if (!$this->hasCache()) {
            return false;
        }

        try {
            return $this->cache->clear();
        } catch (\Throwable $e) {
            $this->logWarning('Cache clear failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get multiple items from cache.
     */
    protected function getMultipleFromCache(array $keys): array
    {
        if (!$this->hasCache()) {
            return [];
        }

        try {
            $items = $this->cache->getItems($keys);
            $result = [];

            foreach ($items as $key => $item) {
                if ($item->isHit()) {
                    $result[$key] = $item->get();
                }
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logWarning('Cache multi-read failed', ['keys' => $keys, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Store multiple items in cache.
     */
    protected function storeMultipleInCache(array $items, ?DateInterval $ttl = null): bool
    {
        if (!$this->hasCache()) {
            return false;
        }

        try {
            $cacheItems = [];

            foreach ($items as $key => $value) {
                $item = $this->cache->getItem($key);
                $item->set($value);

                if ($ttl !== null) {
                    $item->expiresAfter($ttl);
                }

                $cacheItems[] = $item;
            }

            return $this->cache->saveMultiple($cacheItems);
        } catch (\Throwable $e) {
            $this->logWarning('Cache multi-write failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete multiple items from cache.
     */
    protected function deleteMultipleFromCache(array $keys): bool
    {
        if (!$this->hasCache()) {
            return false;
        }

        try {
            return $this->cache->deleteMultiple($keys);
        } catch (\Throwable $e) {
            $this->logWarning('Cache multi-delete failed', ['keys' => $keys, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache key with prefix.
     */
    protected function getCacheKey(string $key): string
    {
        return 'msgraph:' . $key;
    }

    /**
     * Cache a value with TTL in seconds.
     */
    protected function remember(string $key, callable $callback, int $ttlSeconds = 3600): mixed
    {
        $cached = $this->getFromCache($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $ttl = new DateInterval('PT' . $ttlSeconds . 'S');
        $this->storeInCache($key, $value, $ttl);

        return $value;
    }

    /**
     * Cache a value forever.
     */
    protected function rememberForever(string $key, callable $callback): mixed
    {
        $cached = $this->getFromCache($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->storeInCache($key, $value);

        return $value;
    }

    /**
     * Check if cache has a key.
     */
    protected function cacheHas(string $key): bool
    {
        if (!$this->hasCache()) {
            return false;
        }

        try {
            $item = $this->cache->getItem($key);
            return $item->isHit();
        } catch (\Throwable $e) {
            $this->logWarning('Cache check failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
