<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents an array index segment ([0], [-1])
 *
 * @internal
 */
final class ArrayIndexSegment extends PathSegment
{
    /**
     * @param  int  $index  Array index (negative for reverse indexing)
     */
    public function __construct(
        private readonly int $index
    ) {
    }

    public function matches(string|int $key, mixed $value, int $depth): bool
    {
        if (! is_int($key)) {
            return false;
        }

        // For now, we match positive indices directly
        // Negative indices require knowing array length, handled by evaluator
        return $this->index >= 0 && $key === $this->index;
    }

    public function isRecursive(): bool
    {
        return false;
    }

    public function getIndex(): int
    {
        return $this->index;
    }
}
