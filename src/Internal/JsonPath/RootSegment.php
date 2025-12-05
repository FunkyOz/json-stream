<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents the root $ segment
 *
 * @internal
 */
final class RootSegment extends PathSegment
{
    public function matches(string|int $key, mixed $value, int $depth): bool
    {
        return $depth === 0;
    }

    public function isRecursive(): bool
    {
        return false;
    }
}
