---
title: Add Cache Size Limits to ObjectIterator
status: todo
priority: Medium
description: Implement cache size limits in ObjectIterator to prevent unbounded memory growth
---

## Objectives
- Add configurable maximum cache size to ObjectIterator
- Implement cache eviction strategy (LRU or similar)
- Prevent memory exhaustion from very large JSON objects
- Maintain backward compatibility with existing API
- Document caching behavior and limitations

## Deliverables
1. Modified `ObjectIterator` with configurable cache limits
2. Cache eviction strategy implementation (LRU recommended)
3. Configuration option in `Config` for cache size
4. Unit tests verifying cache behavior and limits
5. Documentation of caching strategy and performance implications

## Technical Details

**Location:** `src/Reader/ObjectIterator.php:34`

**Current Issue:**
```php
/** @var array<string, mixed> Cached properties for has()/get() */
private array $cache = [];
```

**Impact:**
- For very large JSON objects, cache grows unbounded
- Attacker could craft large object to cause memory exhaustion
- Negates memory benefits of streaming

**Proposed Solution:**
```php
class ObjectIterator implements IteratorAggregate
{
    /** @var array<string, mixed> Cached properties for has()/get() */
    private array $cache = [];

    /** @var int Maximum number of cached properties */
    private int $maxCacheSize;

    /** @var array<string, int> Access timestamps for LRU eviction */
    private array $cacheTimestamps = [];

    /** @var int Current timestamp counter */
    private int $timestampCounter = 0;

    public function __construct(
        private readonly Parser $parser,
        int $maxCacheSize = 1000  // Default limit
    ) {
        $this->maxCacheSize = $maxCacheSize;
    }

    public function get(string $key): mixed
    {
        if (isset($this->cache[$key])) {
            // Update access timestamp for LRU
            $this->cacheTimestamps[$key] = $this->timestampCounter++;
            return $this->cache[$key];
        }

        // ... parse and find value ...

        // Add to cache with eviction if needed
        $this->addToCache($key, $value);

        return $value;
    }

    private function addToCache(string $key, mixed $value): void
    {
        // Check if cache is full
        if (count($this->cache) >= $this->maxCacheSize && !isset($this->cache[$key])) {
            // Evict least recently used item
            $lruKey = array_search(min($this->cacheTimestamps), $this->cacheTimestamps);
            unset($this->cache[$lruKey], $this->cacheTimestamps[$lruKey]);
        }

        $this->cache[$key] = $value;
        $this->cacheTimestamps[$key] = $this->timestampCounter++;
    }

    /**
     * Disable caching for memory-constrained scenarios
     */
    public function disableCache(): void
    {
        $this->maxCacheSize = 0;
        $this->cache = [];
        $this->cacheTimestamps = [];
    }
}
```

**Alternative: SplDoublyLinkedList for LRU:**
```php
class ObjectIterator
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var SplDoublyLinkedList<string> LRU order */
    private SplDoublyLinkedList $lruOrder;

    /** @var array<string, int> Position in LRU list */
    private array $lruPositions = [];

    // More efficient LRU implementation using doubly linked list
}
```

## Dependencies
- May depend on Task 02 if Config class needs modification

## Estimated Complexity
**Medium** - LRU implementation requires careful bookkeeping

## Implementation Notes
- Default cache size should balance performance and memory (suggest 1000 items)
- Consider making cache size configurable via `Config::READER_CACHE_SIZE`
- LRU (Least Recently Used) is recommended eviction strategy
- Alternative: FIFO (simpler but less optimal)
- Alternative: No caching at all (opt-in via flag)
- Need to measure performance impact of LRU bookkeeping
- Consider whether to cache `null` values (property doesn't exist)

**Configuration Addition (Config.php):**
```php
/**
 * Maximum number of properties to cache in ObjectIterator
 * Set to 0 to disable caching
 */
public const READER_CACHE_SIZE = 1000;
```

**Documentation Points:**
- Explain that caching improves performance for repeated property access
- Document that very large objects may not benefit from full caching
- Explain how to disable caching for memory-critical scenarios
- Provide benchmarks showing cache hit/miss performance

## Acceptance Criteria
- [ ] Cache size is limited to configurable maximum
- [ ] LRU eviction strategy is implemented correctly
- [ ] Tests verify cache eviction when limit is reached
- [ ] Tests verify LRU ordering (most recently used items are retained)
- [ ] Tests verify behavior with cache disabled (size = 0)
- [ ] Tests verify memory usage stays bounded with very large objects
- [ ] Configuration option added to Config class
- [ ] Documentation explains caching behavior and configuration
- [ ] Performance benchmarks show acceptable overhead
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
