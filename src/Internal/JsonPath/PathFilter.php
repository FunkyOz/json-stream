<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

/**
 * Filters parsed JSON data based on JSONPath expression
 *
 * Walks through JSON tree and extracts values that match the path.
 *
 * @internal
 */
final class PathFilter
{
    public function __construct(
        private readonly PathEvaluator $evaluator
    ) {}

    /**
     * Extract values matching the path from parsed JSON
     *
     * @param  mixed  $data  Parsed JSON data
     * @return array<mixed> Matching values
     */
    public function extract(mixed $data): array
    {
        $this->evaluator->reset();
        $results = [];

        // Check if root matches
        if ($this->evaluator->matches()) {
            $results[] = $data;
        }

        // Walk the tree to find matches
        $this->walk($data, $results);

        return $results;
    }

    /**
     * Recursively walk JSON tree looking for matches
     *
     * @param  mixed  $value  Current value
     * @param  array<mixed>  $results  Results accumulator
     */
    private function walk(mixed $value, array &$results): void
    {
        if (is_array($value)) {
            // Check if it's an associative array (object) or indexed array
            if ($this->isAssociativeArray($value)) {
                // Walk object properties
                foreach ($value as $key => $item) {
                    $this->evaluator->enterLevel($key, $item);

                    if ($this->evaluator->matches()) {
                        $results[] = $item;
                    }

                    $this->walk($item, $results);
                    $this->evaluator->exitLevel();
                }
            } else {
                // Walk array elements
                foreach ($value as $index => $item) {
                    $this->evaluator->enterLevel($index, $item);

                    if ($this->evaluator->matches()) {
                        $results[] = $item;
                    }

                    $this->walk($item, $results);
                    $this->evaluator->exitLevel();
                }
            }
        }
    }

    /**
     * Check if array is associative (object-like)
     *
     * @param  array<mixed>  $array
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return ! array_is_list($array);
    }
}
