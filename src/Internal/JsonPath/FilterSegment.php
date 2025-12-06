<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Represents a filter expression segment ([?(@.property op value)])
 *
 * @internal
 */
final class FilterSegment extends PathSegment
{
    /**
     * @param  string  $expression  Filter expression (e.g., "@.price < 10")
     */
    public function __construct(
        private readonly string $expression
    ) {
    }

    public function matches(string|int $key, mixed $value, int $depth): bool
    {
        // Filter expressions need the full value to evaluate
        // This is handled by the evaluator
        return $this->evaluateExpression($value);
    }

    public function isRecursive(): bool
    {
        return false;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Evaluate filter expression against a value
     *
     * @param  mixed  $value  Value to test
     */
    private function evaluateExpression(mixed $value): bool
    {
        // Parse the expression: @.property op value or @.nested.property op value
        // Simple regex-based parser for common cases
        $expr = trim($this->expression);

        // Match pattern: @.property.nested... operator value (supports nested properties)
        if (preg_match('/^@\.([\w.]+)\s*([<>=!]+)\s*(.+)$/', $expr, $matches)) {
            $propertyPath = $matches[1];
            $operator = $matches[2];
            $compareValue = $this->parseValue(trim($matches[3]));

            // Extract nested property from value
            $actualValue = $this->extractNestedProperty($value, $propertyPath);
            if ($actualValue === null && ! $this->hasNestedProperty($value, $propertyPath)) {
                return false;
            }

            return $this->compare($actualValue, $operator, $compareValue);
        }

        // Match pattern: @.property.nested... (existence check)
        if (preg_match('/^@\.([\w.]+)$/', $expr, $matches)) {
            $propertyPath = $matches[1];

            return $this->hasNestedProperty($value, $propertyPath);
        }

        return false;
    }

    /**
     * Extract a nested property from a value using dot notation
     *
     * @param  mixed  $value  Value to extract from
     * @param  string  $propertyPath  Property path (e.g., "author.country")
     * @return mixed Property value or null if not found
     */
    private function extractNestedProperty(mixed $value, string $propertyPath): mixed
    {
        $properties = explode('.', $propertyPath);
        $current = $value;

        foreach ($properties as $property) {
            if (! is_array($current) || ! array_key_exists($property, $current)) {
                return null;
            }
            $current = $current[$property];
        }

        return $current;
    }

    /**
     * Check if a nested property exists in a value
     *
     * @param  mixed  $value  Value to check
     * @param  string  $propertyPath  Property path (e.g., "author.country")
     */
    private function hasNestedProperty(mixed $value, string $propertyPath): bool
    {
        $properties = explode('.', $propertyPath);
        $current = $value;

        foreach ($properties as $property) {
            if (! is_array($current) || ! array_key_exists($property, $current)) {
                return false;
            }
            $current = $current[$property];
        }

        return true;
    }

    /**
     * Parse a value from filter expression
     *
     * @param  string  $str  String representation of value
     * @return mixed Parsed value
     */
    private function parseValue(string $str): mixed
    {
        // Remove quotes for strings
        if (preg_match('/^["\'](.+)["\']$/', $str, $matches)) {
            return $matches[1];
        }

        // Parse numbers
        if (is_numeric($str)) {
            return str_contains($str, '.') ? (float) $str : (int) $str;
        }

        // Parse booleans
        if ($str === 'true') {
            return true;
        }
        if ($str === 'false') {
            return false;
        }

        // Parse null
        if ($str === 'null') {
            return null;
        }

        return $str;
    }

    /**
     * Compare two values using an operator
     *
     * @param  mixed  $left  Left operand
     * @param  string  $operator  Comparison operator
     * @param  mixed  $right  Right operand
     */
    private function compare(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            '=', '==' => $left == $right,
            '===' => $left === $right,
            '!=', '<>' => $left != $right,
            '!==' => $left !== $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            default => false,
        };
    }
}
