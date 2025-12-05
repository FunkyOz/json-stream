<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents a wildcard segment ([*] or .*)
 *
 * @internal
 */
final class WildcardSegment extends PathSegment
{
    private bool $recursive;

    /**
     * @param  bool  $recursive  Whether this is a recursive descent
     */
    public function __construct(bool $recursive = false)
    {
        $this->recursive = $recursive;
    }

    public function matches(string|int $key, mixed $value, int $depth): bool
    {
        // Wildcard matches everything
        return true;
    }

    public function isRecursive(): bool
    {
        return $this->recursive;
    }
}
