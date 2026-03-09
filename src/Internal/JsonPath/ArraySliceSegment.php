<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

use JsonStream\Exception\PathException;

/**
 * Represents an array slice segment ([start:end:step])
 *
 * Note: Negative indices (e.g., $[-1], $[-3:]) are not supported in streaming mode
 * because the array length is unknown during streaming. Use readAllMatches() to
 * buffer the entire array for negative index support.
 *
 * @internal
 */
final class ArraySliceSegment extends PathSegment
{
    /**
     * @param  int|null  $start  Start index (null = 0)
     * @param  int|null  $end  End index (null = length)
     * @param  int  $step  Step size (default 1)
     *
     * @throws PathException If negative indices are used (not supported in streaming mode)
     */
    public function __construct(
        private readonly ?int $start = null,
        private readonly ?int $end = null,
        private readonly int $step = 1
    ) {
        if (($start !== null && $start < 0) || ($end !== null && $end < 0)) {
            throw new PathException(
                'Negative array indices are not supported in streaming mode. '
                . 'Use readAllMatches() to buffer the entire array.'
            );
        }
    }

    public function matches(string|int $key, mixed $value, int $depth): bool
    {
        if (! is_int($key)) {
            return false;
        }

        $start = $this->start ?? 0;

        // Simple check for streaming - more complex logic in evaluator
        if ($this->end === null) {
            // Open-ended slice
            if ($this->step === 1) {
                return $key >= $start;
            }

            return $key >= $start && (($key - $start) % $this->step === 0);
        }

        // Check if key is in range
        if ($key < $start || $key >= $this->end) {
            return false;
        }

        // Check step
        return ($key - $start) % $this->step === 0;
    }

    public function isRecursive(): bool
    {
        return false;
    }

    public function getStart(): ?int
    {
        return $this->start;
    }

    public function getEnd(): ?int
    {
        return $this->end;
    }

    public function getStep(): int
    {
        return $this->step;
    }
}
