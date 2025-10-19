<?php
namespace KamranAta\IstanbulFestivali\Support;

use Psr\SimpleCache\CacheInterface;
use DateInterval;

/**
 * Array-based Cache Implementation
 * 
 * A simple in-memory cache implementation that implements PSR-16 Simple Cache.
 * Suitable for development and testing environments.
 * 
 * @package KamranAta\IstanbulFestivali\Support
 */
class ArrayCache implements CacheInterface
{
    private array $store = [];
    private array $expirations = [];

    /**
     * Retrieve a value from the cache
     * 
     * @param string $key The cache key
     * @param mixed $default Default value if key not found
     * @return mixed The cached value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->store[$key];
    }

    /**
     * Store a value in the cache
     * 
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|DateInterval|null $ttl Time to live in seconds
     * @return bool True on success
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        if ($ttl instanceof DateInterval) {
            $ttl = (new \DateTimeImmutable('@0'))->add($ttl)->getTimestamp();
            $ttl = $ttl - 0;
        }
        
        // Handle zero and negative TTL
        if ($ttl === 0 || $ttl < 0) {
            $this->expirations[$key] = time() - 1; // Expire immediately
        } else {
            $this->expirations[$key] = is_int($ttl) ? time() + $ttl : (is_null($ttl) ? null : time() + (int)$ttl);
        }
        
        return true;
    }

    /**
     * Delete a value from the cache
     * 
     * @param string $key The cache key to delete
     * @return bool True on success
     */
    public function delete(string $key): bool
    {
        unset($this->store[$key], $this->expirations[$key]);
        return true;
    }

    /**
     * Clear all cached values
     * 
     * @return bool True on success
     */
    public function clear(): bool
    {
        $this->store = $this->expirations = [];
        return true;
    }

    /**
     * Retrieve multiple values from the cache
     * 
     * @param iterable $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key-value pairs
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->get($k, $default);
        }
        return $out;
    }

    /**
     * Store multiple values in the cache
     * 
     * @param iterable $values Associative array of key-value pairs
     * @param int|DateInterval|null $ttl Time to live in seconds
     * @return bool True on success
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v, $ttl);
        }
        return true;
    }

    /**
     * Delete multiple values from the cache
     * 
     * @param iterable $keys Array of cache keys to delete
     * @return bool True on success
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $k) {
            $this->delete($k);
        }
        return true;
    }

    /**
     * Check if a key exists in the cache and is not expired
     * 
     * @param string $key The cache key to check
     * @return bool True if key exists and is not expired
     */
    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }
        
        $exp = $this->expirations[$key] ?? null;
        if ($exp !== null && $exp < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }
}
