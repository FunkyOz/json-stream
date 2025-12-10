<?php

declare(strict_types=1);

namespace JsonStream\Reader;

use Generator;
use Iterator;
use JsonStream\Exception\ParseException;
use JsonStream\Internal\TokenType;

/**
 * Generic iterator for any JSON structure
 *
 * Automatically detects the type of JSON structure (array, object, or scalar)
 * and provides iteration accordingly. Supports type checking methods.
 *
 * @implements Iterator<string|int|null, mixed>
 */
class ItemIterator implements Iterator
{
    private StreamReader $reader;

    private ?Generator $generator = null;

    private mixed $current = null;

    private string|int|null $key = null;

    private bool $valid = false;

    private bool $started = false;

    private ?string $rootType = null;

    private bool $isScalar = false;

    public function __construct(StreamReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Get the type of the current item
     *
     * @return string One of: 'array', 'object', 'string', 'number', 'boolean', 'null'
     */
    public function getType(): string
    {
        if ($this->current === null) {
            return 'null';
        }

        if (is_array($this->current)) {
            // Check if associative (object-like) or indexed (array-like)
            return array_is_list($this->current) ? 'array' : 'object';
        }

        if (is_bool($this->current)) {
            return 'boolean';
        }

        if (is_int($this->current) || is_float($this->current)) {
            return 'number';
        }

        if (is_string($this->current)) {
            return 'string';
        }

        return 'unknown';
    }

    /**
     * Check if current item is an array
     */
    public function isArray(): bool
    {
        return $this->getType() === 'array';
    }

    /**
     * Check if current item is an object
     */
    public function isObject(): bool
    {
        return $this->getType() === 'object';
    }

    /**
     * Load all items into a PHP array
     *
     * @return array<string|int, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this as $key => $value) {
            if ($key === null) {
                $result[] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get current item value
     */
    public function current(): mixed
    {
        return $this->current;
    }

    /**
     * Get current key (string for objects, int for arrays, null for scalars)
     */
    public function key(): string|int|null
    {
        return $this->key;
    }

    /**
     * Advance to next item
     */
    public function next(): void
    {
        // Handle scalar case
        if ($this->isScalar) {
            $this->valid = false;

            return;
        }

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

        // Cast key based on root type
        if ($this->rootType === 'object' && is_string($this->generator->key())) {
            $this->key = $this->generator->key();
        } elseif (is_int($this->generator->key())) {
            // Array type or filtered results
            $this->key = $this->generator->key();
        } else {
            throw new ParseException('Invalid key type');
        }

        $this->current = $this->generator->current();
        $this->valid = true;

        $this->reader->incrementItemsProcessed();
    }

    /**
     * Convert array to generator for iteration
     *
     * @param  array<mixed>  $array
     * @return \Generator<int, mixed>
     */
    private function arrayToGenerator(array $array): \Generator
    {
        foreach ($array as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * Reset to first item
     *
     * Note: May not work for non-seekable streams.
     *
     * Path filtering uses true streaming via parseAndExtractMatches().
     * Elements are yielded one at a time without buffering the entire result set.
     */
    public function rewind(): void
    {
        // Only rewind if we haven't started yet
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->valid = false;

        // If path filtering is enabled, choose strategy based on pattern complexity
        if ($this->reader->hasPathFilter()) {
            // Check if pattern can use simple streaming optimization
            if ($this->reader->getPathExpression()?->canUseSimpleStreaming()) {
                // Simple pattern - use true streaming (no buffering)
                $this->isScalar = false;
                $this->rootType = 'array';
                $this->generator = $this->reader->getParser()->parseAndExtractMatches();
                $this->generator->rewind();

                if ($this->generator->valid()) {
                    $this->key = 0;
                    $this->current = $this->generator->current();
                    $this->valid = true;
                    $this->reader->incrementItemsProcessed();
                }
            } else {
                // Complex pattern - use PathFilter (buffers but handles all cases)
                $matches = $this->reader->readAllMatches();

                if (! empty($matches)) {
                    $this->isScalar = false;
                    $this->rootType = 'array';
                    $this->generator = $this->arrayToGenerator($matches);
                    $this->generator->rewind();

                    if ($this->generator->valid()) {
                        $this->key = 0;
                        $this->current = $this->generator->current();
                        $this->valid = true;
                        $this->reader->incrementItemsProcessed();
                    }
                }
            }

            return;
        }

        // Normal iteration without filtering
        // Detect root type by peeking at first token
        $firstToken = $this->reader->getParser()->peekToken();

        $this->rootType = match ($firstToken->type) {
            TokenType::LEFT_BRACKET => 'array',
            TokenType::LEFT_BRACE => 'object',
            default => 'scalar',
        };

        // Create appropriate generator based on type
        if ($this->rootType === 'array') {
            $this->isScalar = false;
            $this->generator = $this->reader->getParser()->parseArray();
            $this->generator->rewind();
            if ($this->generator->valid()) {
                $this->key = (int) $this->generator->key();
                $this->current = $this->generator->current();
                $this->valid = true;
                $this->reader->incrementItemsProcessed();
            }
        } elseif ($this->rootType === 'object') {
            $this->isScalar = false;
            $this->generator = $this->reader->getParser()->parseObject();
            $this->generator->rewind();
            if ($this->generator->valid()) {
                $this->key = (string) $this->generator->key();
                $this->current = $this->generator->current();
                $this->valid = true;
                $this->reader->incrementItemsProcessed();
            }
        } else {
            // Scalar value - yield it once
            $this->isScalar = true;
            $this->key = null;
            $this->current = $this->reader->getParser()->parseValue();
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
