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
     * Check if we're at an array operation with remaining segments to extract
     *
     * For patterns like $.users[*].name, when we're at the wildcard position,
     * this returns true because we need to extract .name from each matched element.
     *
     * @return bool True if we should parse and extract remaining segments
     */
    public function shouldExtractFromValue(): bool
    {
        $segments = $this->expression->getSegments();
        $depth = count($this->pathStack);

        // Need at least depth segments to match current position
        if ($depth >= count($segments)) {
            return false;
        }

        // Check if segments up to current depth match
        if (! $this->matchSegmentsPartial($segments, 1, 0, $depth)) {
            return false;
        }

        // Check if there are remaining segments after current depth
        $remaining = $this->getRemainingSegments();

        return ! empty($remaining);
    }

    /**
     * Match segments up to a specific depth (partial match)
     *
     * @param  PathSegment[]  $segments  Path segments to match
     * @param  int  $segmentIndex  Current segment index
     * @param  int  $stackIndex  Current stack index
     * @param  int  $maxDepth  Maximum depth to match
     */
    private function matchSegmentsPartial(array $segments, int $segmentIndex, int $stackIndex, int $maxDepth): bool
    {
        // Matched up to max depth
        if ($stackIndex >= $maxDepth) {
            return true;
        }

        // No more segments to match but haven't reached maxDepth
        if ($segmentIndex >= count($segments)) {
            return false;
        }

        // No more stack to match
        if ($stackIndex >= count($this->pathStack)) {
            return false;
        }

        $segment = $segments[$segmentIndex];
        $key = $this->pathStack[$stackIndex];
        $value = $this->valueStack[$stackIndex];

        // Normal segment must match at current position
        if (! $segment->matches($key, $value, $stackIndex)) {
            return false;
        }

        // Continue with next segment
        return $this->matchSegmentsPartial($segments, $segmentIndex + 1, $stackIndex + 1, $maxDepth);
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

    /**
     * Get segments that come after the current match point
     *
     * When streaming an array like $.users[*].name, after matching at
     * $.users[*], we need to know that .name remains to be extracted.
     *
     * Returns only PropertySegment and ArrayIndexSegment instances that need
     * to be extracted via walkValue(). Does not include the segment we just
     * matched (wildcard/filter/slice) as that's already been processed.
     *
     * @return PathSegment[] Remaining segments to extract from matched value
     */
    public function getRemainingSegments(): array
    {
        $segments = $this->expression->getSegments();
        $depth = count($this->pathStack);

        // For $.users[*].name at depth 2 (root=$ + prop=users + index=0):
        // - Segments: [RootSegment, PropertySegment(users), WildcardSegment, PropertySegment(name)]
        // - Depth is 2 (users=1, index=2)
        // - Current segment is at index 2 (WildcardSegment)
        // - Remaining starts at index 3 (PropertySegment(name))

        // Current segment index is depth (0-based): depth 0 = segment 0, depth 1 = segment 1, etc.
        // But we want segments AFTER the one we just matched
        $currentSegmentIndex = $depth;

        // Return segments after current position that can be walked
        // Include: PropertySegment, ArrayIndexSegment
        // Exclude: WildcardSegment, FilterSegment, ArraySliceSegment (these need streaming)
        $remaining = [];
        for ($i = $currentSegmentIndex + 1; $i < count($segments); $i++) {
            $segment = $segments[$i];
            if ($segment instanceof PropertySegment) {
                $remaining[] = $segment;
            } elseif ($segment instanceof ArrayIndexSegment) {
                $remaining[] = $segment;
            } elseif ($segment instanceof WildcardSegment ||
                      $segment instanceof FilterSegment ||
                      $segment instanceof ArraySliceSegment) {
                // Can't walk into wildcards, filters, or slices - need nested streaming
                break;
            }
        }

        return $remaining;
    }

    /**
     * Walk into a parsed value to extract remaining path segments
     *
     * For patterns like $.users[*].name, after streaming the array,
     * this walks into each user object to extract the "name" property.
     *
     * @param  mixed  $value  The parsed value to walk into
     * @param  PathSegment[]  $segments  Segments to extract
     * @return mixed The extracted value, or null if not found
     */
    public function walkValue(mixed $value, array $segments): mixed
    {
        // If no segments, return the value as-is
        if (empty($segments)) {
            return $value;
        }

        $current = $value;

        foreach ($segments as $segment) {
            // PropertySegment: extract property from object
            if ($segment instanceof PropertySegment) {
                if (! is_array($current)) {
                    return null;
                }

                $propertyName = $segment->getPropertyName();
                if (! array_key_exists($propertyName, $current)) {
                    return null;
                }

                $current = $current[$propertyName];

                continue;
            }

            // ArrayIndexSegment: extract element from array
            if ($segment instanceof ArrayIndexSegment) {
                if (! is_array($current) || ! array_is_list($current)) {
                    return null;
                }

                $index = $segment->getIndex();
                // Handle negative indices
                if ($index < 0) {
                    $index = count($current) + $index;
                }

                if (! array_key_exists($index, $current)) {
                    return null;
                }

                $current = $current[$index];

                continue;
            }

            // WildcardSegment: yield all elements from array
            if ($segment instanceof WildcardSegment) {
                if (! is_array($current)) {
                    return null;
                }

                // This is a nested wildcard case like $.users[*].posts[*]
                // We need to return a generator or array of all elements
                // For now, we'll handle this differently in the caller
                return $current;
            }

            // Other segment types not yet supported in walk
            return null;
        }

        return $current;
    }
}
