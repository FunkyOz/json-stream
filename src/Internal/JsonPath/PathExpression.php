<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents a parsed JSONPath expression
 *
 * Stores the expression as a sequence of path segments
 * that can be evaluated against JSON data during streaming.
 *
 * Provides streaming capability detection to optimize memory usage
 * by enabling early termination and per-element evaluation.
 *
 * @internal
 */
final class PathExpression
{
    /**
     * @param  string  $originalPath  Original JSONPath string
     * @param  PathSegment[]  $segments  Parsed path segments
     */
    public function __construct(
        private readonly string $originalPath,
        private readonly array $segments
    ) {
    }

    /**
     * Get the original JSONPath string
     */
    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    /**
     * Get all path segments
     *
     * @return PathSegment[]
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * Get number of segments
     */
    public function getSegmentCount(): int
    {
        return count($this->segments);
    }

    /**
     * Get segment at index
     *
     * @param  int  $index  Segment index
     */
    public function getSegment(int $index): ?PathSegment
    {
        return $this->segments[$index] ?? null;
    }

    /**
     * Check if path has recursive segments
     */
    public function hasRecursive(): bool
    {
        foreach ($this->segments as $segment) {
            if ($segment->isRecursive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path can stream array elements
     *
     * Returns true if the path can be evaluated per-element during parsing
     * without needing to load the entire structure into memory.
     *
     * Examples:
     * - $.Ads[*] -> true (can stream each array element)
     * - $.Ads[0] -> true (can stream until index 0)
     * - $..Email -> false (recursive descent needs full tree traversal)
     *
     * @return bool True if path supports streaming
     */
    public function canStreamArrayElements(): bool
    {
        // Recursive paths can't truly stream as they need full tree context
        if ($this->hasRecursive()) {
            return false;
        }

        // All non-recursive paths can stream to some degree
        return true;
    }

    /**
     * Check if path has early termination capability
     *
     * Returns true if we can stop parsing once we've found all matching elements.
     * This is true for specific index access or bounded slices.
     *
     * Examples:
     * - $.Ads[0] -> true (stop after first match)
     * - $.Ads[0:10] -> true (stop after 10 elements)
     * - $.Ads[*] -> false (need all elements)
     *
     * @return bool True if early termination is possible
     */
    public function hasEarlyTermination(): bool
    {
        // Check if we have an array index or bounded slice segment
        foreach ($this->segments as $segment) {
            if ($segment instanceof ArrayIndexSegment) {
                $index = $segment->getIndex();
                // Only positive indices support early termination
                // Negative indices require knowing array length
                if ($index >= 0) {
                    return true;
                }
            }

            if ($segment instanceof ArraySliceSegment) {
                // Bounded slices with end specified support early termination
                if ($segment->getEnd() !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the termination index
     *
     * Returns the array index after which we can stop parsing.
     * Returns null if early termination is not applicable.
     *
     * Examples:
     * - $.Ads[0] -> 1 (stop after index 0)
     * - $.Ads[0:10] -> 10 (stop after index 9)
     * - $.Ads[*] -> null (no early termination)
     *
     * @return int|null Termination index or null
     */
    public function getTerminationIndex(): ?int
    {
        foreach ($this->segments as $segment) {
            if ($segment instanceof ArrayIndexSegment) {
                $index = $segment->getIndex();
                if ($index >= 0) {
                    // Terminate after this index (so index + 1)
                    return $index + 1;
                }
            }

            if ($segment instanceof ArraySliceSegment) {
                $end = $segment->getEnd();
                if ($end !== null && $end > 0) {
                    // Terminate at the end index
                    return $end;
                }
            }
        }

        return null;
    }

    /**
     * Check if path can use simple streaming optimization
     *
     * Returns true for simple patterns that can be streamed efficiently:
     * - $.array[*] - root array wildcard
     * - $.prop[*] - property then array wildcard
     * - $.prop.nested[*] - nested property navigation then wildcard
     * - $.array[0] or $.array[0:10] - specific index/slice access
     * - $.Ads[*] - the main use case!
     *
     * Returns false for complex patterns that need full tree walking:
     * - $..prop - recursive descent
     * - $.array[*].prop - wildcard followed by property access (needs walkValue)
     * - $.array[*].prop[*] - multiple wildcards
     * - Complex filter expressions
     *
     * @return bool True if simple streaming can be used
     */
    public function canUseSimpleStreaming(): bool
    {
        // Must have at least root + one segment
        if (count($this->segments) < 2) {
            return false;
        }

        // No recursive descent allowed
        if ($this->hasRecursive()) {
            return false;
        }

        $wildcardCount = 0;
        $hasArrayOpFollowedByProperty = false;

        // Skip root segment (index 0)
        for ($i = 1; $i < count($this->segments); $i++) {
            $segment = $this->segments[$i];
            $nextSegment = $this->segments[$i + 1] ?? null;

            // Count wildcards
            if ($segment instanceof WildcardSegment) {
                $wildcardCount++;

                // Check if wildcard is followed by property access
                if ($nextSegment !== null && $nextSegment instanceof PropertySegment) {
                    $hasArrayOpFollowedByProperty = true;
                }
            } elseif ($segment instanceof ArrayIndexSegment || $segment instanceof ArraySliceSegment) {
                // Check if array operation is followed by property access
                if ($nextSegment !== null && $nextSegment instanceof PropertySegment) {
                    $hasArrayOpFollowedByProperty = true;
                }
            } elseif ($segment instanceof FilterSegment) {
                // Filters are complex, not simple streaming
                return false;
            }
        }

        // Don't stream if:
        // - Multiple wildcards
        // - Array operation followed by property (like [*].name)
        if ($wildcardCount > 1 || $hasArrayOpFollowedByProperty) {
            return false;
        }

        // Simple patterns: $.array[*], $.prop.array[0], etc. (ending with array op)
        return true;
    }
}
