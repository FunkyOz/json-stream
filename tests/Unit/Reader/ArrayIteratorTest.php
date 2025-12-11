<?php

declare(strict_types=1);

use JsonStream\Reader\StreamReader;

describe('ArrayIterator', function (): void {
    it('iterates over array elements', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5]');
        $iterator = $reader->readArray();

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([1, 2, 3, 4, 5]);
    });

    it('provides correct keys (0-based index)', function (): void {
        $reader = StreamReader::fromString('["a", "b", "c"]');
        $iterator = $reader->readArray();

        $keys = [];
        foreach ($iterator as $key => $value) {
            $keys[] = $key;
        }

        expect($keys)->toBe([0, 1, 2]);
    });

    it('handles empty arrays', function (): void {
        $reader = StreamReader::fromString('[]');
        $iterator = $reader->readArray();

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([]);
    });

    it('handles arrays with mixed types', function (): void {
        $reader = StreamReader::fromString('[1, "two", 3.14, true, null, {"key": "value"}]');
        $iterator = $reader->readArray();

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([1, 'two', 3.14, true, null, ['key' => 'value']]);
    });

    it('handles nested arrays', function (): void {
        $reader = StreamReader::fromString('[[1, 2], [3, 4], [5, 6]]');
        $iterator = $reader->readArray();

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([[1, 2], [3, 4], [5, 6]]);
    });

    it('skip() skips N elements', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]');
        $iterator = $reader->readArray()->skip(3);

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([4, 5, 6, 7, 8, 9, 10]);
    });

    it('skip() with large count skips all', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3]');
        $iterator = $reader->readArray()->skip(100);

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([]);
    });

    it('limit() limits to N elements', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]');
        $iterator = $reader->readArray()->limit(3);

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([1, 2, 3]);
    });

    it('skip() and limit() work together', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]');
        $iterator = $reader->readArray()->skip(3)->limit(3);

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([4, 5, 6]);
    });

    it('toArray() loads remaining elements', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5]');
        $iterator = $reader->readArray();

        $result = $iterator->toArray();

        expect($result)->toBe([1, 2, 3, 4, 5]);
    });

    it('toArray() with skip and limit', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]');
        $iterator = $reader->readArray()->skip(2)->limit(5);

        $result = $iterator->toArray();

        expect($result)->toBe([3, 4, 5, 6, 7]);
    });

    it('count() returns -1 for streaming mode', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5]');
        $iterator = $reader->readArray();

        expect($iterator->count())->toBe(-1);
    });

    it('handles large arrays efficiently', function (): void {
        // Create large array JSON
        $json = '['.implode(',', range(1, 10000)).']';
        $reader = StreamReader::fromString($json);
        $iterator = $reader->readArray()->limit(100);

        $count = 0;
        foreach ($iterator as $value) {
            $count++;
        }

        expect($count)->toBe(100);
    });

    it('allows early termination', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]');
        $iterator = $reader->readArray();

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
            if ($value === 5) {
                break;
            }
        }

        expect($result)->toBe([1, 2, 3, 4, 5]);
    });

    it('handles arrays of objects', function (): void {
        $json = '[{"id": 1, "name": "Alice"}, {"id": 2, "name": "Bob"}]';
        $reader = StreamReader::fromString($json);
        $iterator = $reader->readArray();

        $result = [];
        foreach ($iterator as $value) {
            $result[] = $value;
        }

        expect($result)->toBe([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
    });

    it('does not rewind after iteration starts', function (): void {
        $reader = StreamReader::fromString('[1, 2, 3]');
        $iterator = $reader->readArray();

        // First iteration
        $result1 = [];
        foreach ($iterator as $value) {
            $result1[] = $value;
        }

        // Second iteration should be empty (no rewind for non-seekable)
        $result2 = [];
        foreach ($iterator as $value) {
            $result2[] = $value;
        }

        expect($result1)->toBe([1, 2, 3]);
        expect($result2)->toBe([]);
    });

    it('handles next() call when generator is null', function (): void {
        $reader = StreamReader::fromString('[1, 2]');
        $iterator = $reader->readArray();

        // Exhaust the iterator
        foreach ($iterator as $value) {
            // Consume
        }

        // Now generator is null, calling next() should not crash (lines 112-114)
        $iterator->next();
        expect($iterator->valid())->toBeFalse();
    });
});
