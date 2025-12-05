<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents a segment in a JSONPath expression
 *
 * @internal
 */
abstract class PathSegment
{
    /**
     * Check if this segment matches the current context
     *
     * @param  string|int  $key  Current key or index
     * @param  mixed  $value  Current value
     * @param  int  $depth  Current depth in JSON structure
     */
    abstract public function matches(string|int $key, mixed $value, int $depth): bool;

    /**
     * Whether this segment is recursive (uses ..)
     */
    abstract public function isRecursive(): bool;
}
