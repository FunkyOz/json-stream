<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

use JsonStream\Config;
use JsonStream\Exception\ParseException;

/**
 * Filters parsed JSON data based on JSONPath expression
 *
 * Walks through JSON tree and extracts values that match the path.
 * Enforces depth limits to prevent stack overflow from deeply nested data.
 *
 * @internal
 */
final class PathFilter
{
    public function __construct(
        private readonly PathEvaluator $evaluator,
        private readonly int $maxDepth = Config::DEFAULT_MAX_DEPTH
    ) {
    }

    /**
     * Extract values matching the path from parsed JSON
     *
     * @param  mixed  $data  Parsed JSON data
     * @return array<mixed> Matching values
     *
     * @throws ParseException If depth limit is exceeded
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
        $this->walk($data, $results, 0);

        return $results;
    }

    /**
     * Recursively walk JSON tree looking for matches
     *
     * @param  mixed  $value  Current value
     * @param  array<mixed>  $results  Results accumulator
     * @param  int  $depth  Current depth
     *
     * @throws ParseException If depth limit is exceeded
     */
    private function walk(mixed $value, array &$results, int $depth): void
    {
        if ($depth > $this->maxDepth) {
            throw new ParseException(
                "Maximum depth of $this->maxDepth exceeded during path traversal"
            );
        }

        if (is_array($value)) {
            // Check if it's an associative array (object) or indexed array
            if (! empty($value) && ! array_is_list($value)) {
                // Walk object properties
                foreach ($value as $key => $item) {
                    $this->evaluator->enterLevel($key, $item);

                    if ($this->evaluator->matches()) {
                        $results[] = $item;
                    }

                    $this->walk($item, $results, $depth + 1);
                    $this->evaluator->exitLevel();
                }
            } else {
                // Walk array elements
                foreach ($value as $index => $item) {
                    $this->evaluator->enterLevel($index, $item);

                    if ($this->evaluator->matches()) {
                        $results[] = $item;
                    }

                    $this->walk($item, $results, $depth + 1);
                    $this->evaluator->exitLevel();
                }
            }
        }
    }
}
