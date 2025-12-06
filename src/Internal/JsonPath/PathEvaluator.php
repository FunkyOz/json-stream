<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Evaluates JSONPath expressions during streaming parse
 *
 * Maintains path state during parsing and determines if current
 * position matches the JSONPath expression.
 *
 * @internal
 */
final class PathEvaluator
{
    /** @var array<int, string|int> Current path stack (keys/indices) */
    private array $pathStack = [];

    /** @var array<int, mixed> Value stack for filter evaluation */
    private array $valueStack = [];

    /**
     * @param  PathExpression  $expression  JSONPath expression to evaluate
     */
    public function __construct(
        private readonly PathExpression $expression
    ) {
    }

    /**
     * Enter a new level (object property or array element)
     *
     * @param  string|int  $key  Property name or array index
     * @param  mixed  $value  Value at this position
     */
    public function enterLevel(string|int $key, mixed $value): void
    {
        $this->pathStack[] = $key;
        $this->valueStack[] = $value;
    }

    /**
     * Exit current level
     */
    public function exitLevel(): void
    {
        array_pop($this->pathStack);
        array_pop($this->valueStack);
    }

    /**
     * Check if current position matches the path expression
     *
     * @return bool True if current position should be yielded
     */
    public function matches(): bool
    {
        $segments = $this->expression->getSegments();
        $depth = count($this->pathStack);

        // Root segment is always at position 0, actual path starts at 1
        if (count($segments) === 1) {
            // Just $, match root level
            return $depth === 0;
        }

        // Try to match path segments against current path stack
        return $this->matchSegments($segments, 1, 0);
    }

    /**
     * Check if current path structure matches without evaluating filters
     *
     * Used by streaming parser to decide if it should parse deeper into structure.
     * Returns true if the path structure (keys/indices) matches, regardless of filter
     * expressions that require value inspection.
     *
     * @return bool True if path structure matches
     */
    public function matchesStructure(): bool
    {
        $segments = $this->expression->getSegments();
        $depth = count($this->pathStack);

        // Root segment is always at position 0, actual path starts at 1
        if (count($segments) === 1) {
            return $depth === 0;
        }

        // Match segments but skip filter evaluation
        return $this->matchSegmentsStructural($segments, 1, 0);
    }

    /**
     * Check if current segment needs value for matching
     *
     * Returns true if the current segment has a filter expression that requires
     * the value to be parsed before we can determine if it matches.
     *
     * @return bool True if value is needed for match evaluation
     */
    public function needsValueForMatch(): bool
    {
        $segments = $this->expression->getSegments();
        $depth = count($this->pathStack);

        // Check if we're at a segment that needs value evaluation
        if ($depth === 0 || count($segments) <= 1) {
            return false;
        }

        // Find which segment we're currently evaluating
        $segmentIndex = $this->findCurrentSegmentIndex($segments);
        if ($segmentIndex === null || $segmentIndex >= count($segments)) {
            return false;
        }

        $segment = $segments[$segmentIndex];

        // Filter segments always need the value
        return $segment instanceof FilterSegment;
    }

    /**
     * Check if parsing can terminate early
     *
     * Returns true if we've found all matching values and can stop parsing.
     * This is true for specific index access (e.g., $.Ads[0]) or bounded slices
     * (e.g., $.Ads[0:10]) where we know exactly how many elements we need.
     *
     * @return bool True if we can stop parsing
     */
    public function canTerminateEarly(): bool
    {
        // Check if we've matched all required items
        // This depends on the path expression having early termination capability
        return $this->expression->hasEarlyTermination()
            && $this->hasReachedTerminationPoint();
    }

    /**
     * Check if we've reached the termination point
     *
     * @return bool True if termination point reached
     */
    private function hasReachedTerminationPoint(): bool
    {
        $terminationIndex = $this->expression->getTerminationIndex();
        if ($terminationIndex === null) {
            return false;
        }

        // Check if current index exceeds termination point
        $depth = count($this->pathStack);
        if ($depth === 0) {
            return false;
        }

        $currentKey = $this->pathStack[$depth - 1];
        if (! is_int($currentKey)) {
            return false;
        }

        return $currentKey >= $terminationIndex;
    }

    /**
     * Match segments structurally (without filter evaluation)
     *
     * @param  PathSegment[]  $segments  Path segments to match
     * @param  int  $segmentIndex  Current segment index
     * @param  int  $stackIndex  Current stack index
     */
    private function matchSegmentsStructural(array $segments, int $segmentIndex, int $stackIndex): bool
    {
        // All segments matched
        if ($segmentIndex >= count($segments)) {
            return $stackIndex === count($this->pathStack);
        }

        // No more stack to match
        if ($stackIndex >= count($this->pathStack)) {
            return false;
        }

        $segment = $segments[$segmentIndex];
        $key = $this->pathStack[$stackIndex];
        $value = null; // Don't use value for structural matching

        // Recursive segment tries to match at any depth
        if ($segment->isRecursive()) {
            // For structural matching, we can't fully evaluate recursive segments
            // Return true if there's any potential match
            return true;
        }

        // For filters, do structural match only (ignore filter condition)
        if ($segment instanceof FilterSegment) {
            // Array filters match any array index
            if (is_int($key)) {
                return $this->matchSegmentsStructural($segments, $segmentIndex + 1, $stackIndex + 1);
            }

            return false;
        }

        // Normal segment must match at current position
        if (! $segment->matches($key, $value, $stackIndex)) {
            return false;
        }

        // Continue with next segment
        return $this->matchSegmentsStructural($segments, $segmentIndex + 1, $stackIndex + 1);
    }

    /**
     * Find the segment index we're currently evaluating
     *
     * @param  PathSegment[]  $segments  Path segments
     * @return int|null Current segment index or null
     */
    private function findCurrentSegmentIndex(array $segments): ?int
    {
        $depth = count($this->pathStack);

        // Simple case: depth maps to segment index + 1 (accounting for root)
        // This works for non-recursive paths
        if ($depth > 0 && $depth < count($segments)) {
            return $depth;
        }

        return null;
    }

    /**
     * Match segments against path stack recursively
     *
     * @param  PathSegment[]  $segments  Path segments to match
     * @param  int  $segmentIndex  Current segment index
     * @param  int  $stackIndex  Current stack index
     */
    private function matchSegments(array $segments, int $segmentIndex, int $stackIndex): bool
    {
        // All segments matched
        if ($segmentIndex >= count($segments)) {
            return $stackIndex === count($this->pathStack);
        }

        // No more stack to match
        if ($stackIndex >= count($this->pathStack)) {
            return false;
        }

        $segment = $segments[$segmentIndex];
        $key = $this->pathStack[$stackIndex];
        $value = $this->valueStack[$stackIndex];

        // Recursive segment tries to match at any depth
        if ($segment->isRecursive()) {
            // Try matching at current position
            if ($segment->matches($key, $value, $stackIndex)) {
                if ($this->matchSegments($segments, $segmentIndex + 1, $stackIndex + 1)) {
                    return true;
                }
            }

            // Try skipping to next level
            return $this->matchSegments($segments, $segmentIndex, $stackIndex + 1);
        }

        // Normal segment must match at current position
        if (! $segment->matches($key, $value, $stackIndex)) {
            return false;
        }

        // Continue with next segment
        return $this->matchSegments($segments, $segmentIndex + 1, $stackIndex + 1);
    }

    /**
     * Get current depth in the JSON structure
     */
    public function getDepth(): int
    {
        return count($this->pathStack);
    }

    /**
     * Get current path as string (for debugging)
     */
    public function getCurrentPath(): string
    {
        if (empty($this->pathStack)) {
            return '$';
        }

        $path = '$';
        foreach ($this->pathStack as $key) {
            if (is_int($key)) {
                $path .= "[{$key}]";
            } else {
                $path .= ".{$key}";
            }
        }

        return $path;
    }

    /**
     * Get current value
     */
    public function getCurrentValue(): mixed
    {
        if (empty($this->valueStack)) {
            return null;
        }

        return end($this->valueStack);
    }

    /**
     * Reset evaluator state
     */
    public function reset(): void
    {
        $this->pathStack = [];
        $this->valueStack = [];
    }

    /**
     * Get the PathExpression being evaluated
     *
     * @return PathExpression The path expression
     */
    public function getExpression(): PathExpression
    {
        return $this->expression;
    }
}
