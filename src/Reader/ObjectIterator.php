<?php

declare(strict_types=1);

namespace JsonStream\Reader;

use Countable;
use Generator;
use Iterator;
use JsonStream\Exception\ParseException;

/**
 * Iterator for JSON objects
 *
 * Implements Iterator and Countable interfaces for efficient streaming
 * iteration over JSON objects without loading entire object into memory.
 *
 * The cache is bounded to prevent unbounded memory growth from very large
 * JSON objects. When the cache reaches its limit, the oldest entries are
 * evicted (FIFO).
 *
 * @implements Iterator<string, mixed>
 */
class ObjectIterator implements Countable, Iterator
{
    private const DEFAULT_MAX_CACHE_SIZE = 1000;

    private StreamReader $reader;

    private ?Generator $generator = null;

    private mixed $current = null;

    private ?string $key = null;

    private bool $valid = false;

    private bool $started = false;

    /** @var array<string, mixed> Cached properties for has()/get() */
    private array $cache = [];

    private int $maxCacheSize;

    public function __construct(StreamReader $reader, int $maxCacheSize = self::DEFAULT_MAX_CACHE_SIZE)
    {
        $this->reader = $reader;
        $this->maxCacheSize = $maxCacheSize;
    }

    /**
     * Check if object has a property
     */
    public function has(string $key): bool
    {
        // Check cache first
        if (array_key_exists($key, $this->cache)) {
            return true;
        }

        // Iterate through remaining properties
        foreach ($this as $propKey => $propValue) {
            if ($propKey === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get property value or return default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check cache first
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // Iterate through remaining properties
        foreach ($this as $propKey => $propValue) {
            if ($propKey === $key) {
                return $propValue;
            }
        }

        return $default;
    }

    /**
     * Load all properties into a PHP array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = $this->cache;

        foreach ($this as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Returns -1 for streaming mode (count unknown without scanning)
     *
     * @return int<-1, max>
     */
    public function count(): int
    {
        return -1;
    }

    /**
     * Get current property value
     */
    public function current(): mixed
    {
        return $this->current;
    }

    /**
     * Get current property name
     */
    public function key(): ?string
    {
        return $this->key;
    }

    /**
     * Advance to next property
     */
    public function next(): void
    {
        if ($this->generator === null) {
            $this->valid = false;

            return;
        }

        // Advance generator
        $this->generator->next();

        if (! $this->generator->valid()) {
            $this->valid = false;

            return;
        }

        if (!is_string($this->generator->key())) {
            throw new ParseException('Invalid key type');
        }
        $this->key = $this->generator->key();
        $this->current = $this->generator->current();
        $this->valid = true;

        // Cache property with eviction
        $this->addToCache($this->key, $this->current);

        $this->reader->incrementItemsProcessed();
    }

    /**
     * Reset to first property
     *
     * Note: May not work for non-seekable streams
     */
    public function rewind(): void
    {
        // Only rewind if we haven't started yet
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->key = null;
        $this->valid = false;
        $this->cache = [];

        // Create generator from parser
        $this->generator = $this->reader->getParser()->parseObject();

        // Start the generator
        $this->generator->rewind();

        // Position at first property
        if ($this->generator->valid()) {
            $this->key = (string) $this->generator->key();
            $this->current = $this->generator->current();
            $this->valid = true;
            $this->addToCache($this->key, $this->current);
            $this->reader->incrementItemsProcessed();
        }
    }

    /**
     * Add entry to cache with FIFO eviction when limit is reached
     */
    private function addToCache(string $key, mixed $value): void
    {
        if ($this->maxCacheSize <= 0) {
            return;
        }

        if (count($this->cache) >= $this->maxCacheSize && ! array_key_exists($key, $this->cache)) {
            // Evict oldest entry (FIFO) — cache is non-empty since count >= maxCacheSize > 0
            reset($this->cache);
            unset($this->cache[key($this->cache)]);
        }

        $this->cache[$key] = $value;
    }

    /**
     * Check if current position is valid
     */
    public function valid(): bool
    {
        return $this->valid;
    }
}
