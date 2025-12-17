<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents a property access segment (.property or ['property'])
 *
 * @internal
 */
final class PropertySegment extends PathSegment
{
    private bool $recursive;

    /**
     * @param  string  $property  Property name to match
     * @param  bool  $recursive  Whether this is a recursive descent (..)
     */
    public function __construct(
        private readonly string $property,
        bool $recursive = false
    ) {
        $this->recursive = $recursive;
    }

    public function matches(string|int $key, mixed $value, int $depth): bool
    {
        return $key === $this->property;
    }

    public function isRecursive(): bool
    {
        return $this->recursive;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Get the property name (alias for getProperty())
     */
    public function getPropertyName(): string
    {
        return $this->property;
    }
}
