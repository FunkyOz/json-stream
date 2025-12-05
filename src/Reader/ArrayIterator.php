<?php

declare(strict_types=1);

namespace JsonStream\Reader;

use Countable;
use Generator;
use Iterator;

/**
 * Iterator for JSON arrays
 *
 * Implements Iterator and Countable interfaces for efficient streaming
 * iteration over JSON arrays without loading entire array into memory.
 *
 * @implements Iterator<int, mixed>
 */
class ArrayIterator implements Countable, Iterator
{
    private StreamReader $reader;

    private ?Generator $generator = null;

    private mixed $current = null;

    private int $key = -1;

    private bool $valid = false;

    private int $skipCount = 0;

    private int $limitCount = -1;

    private int $yieldedCount = 0;

    private bool $started = false;

    public function __construct(StreamReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Skip N elements from the beginning
     */
    public function skip(int $count): self
    {
        $this->skipCount = $count;

        return $this;
    }

    /**
     * Limit to N elements
     */
    public function limit(int $count): self
    {
        $this->limitCount = $count;

        return $this;
    }

    /**
     * Load all remaining elements into a PHP array
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this as $value) {
            $result[] = $value;
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
     * Get current element value
     */
    public function current(): mixed
    {
        return $this->current;
    }

    /**
     * Get current array index (0-based)
     */
    public function key(): int
    {
        return $this->key;
    }

    /**
     * Advance to next element
     */
    public function next(): void
    {
        if ($this->generator === null) {
            $this->valid = false;

            return;
        }

        // Check limit
        if ($this->limitCount !== -1 && $this->yieldedCount >= $this->limitCount) {
            $this->valid = false;

            return;
        }

        // Advance generator
        $this->generator->next();

        if (! $this->generator->valid()) {
            $this->valid = false;

            return;
        }

        $this->current = $this->generator->current();
        $this->key++;
        $this->yieldedCount++;
        $this->valid = true;

        $this->reader->incrementItemsProcessed();
    }

    /**
     * Reset to first element
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
        $this->key = -1;
        $this->yieldedCount = 0;
        $this->valid = false;

        // Create generator from parser
        $this->generator = $this->reader->getParser()->parseArray();

        // Start the generator
        $this->generator->rewind();

        // Handle skip
        for ($i = 0; $i < $this->skipCount; $i++) {
            if (! $this->generator->valid()) {
                return;
            }
            // Just advance the generator (it already parsed the value)
            $this->generator->next();
        }

        // Position at first element (or first after skip)
        if ($this->generator->valid()) {
            $this->current = $this->generator->current();
            $this->key = 0;
            $this->yieldedCount = 1;
            $this->valid = true;
            $this->reader->incrementItemsProcessed();
        }
    }

    /**
     * Check if current position is valid
     */
    public function valid(): bool
    {
        return $this->valid;
    }
}
