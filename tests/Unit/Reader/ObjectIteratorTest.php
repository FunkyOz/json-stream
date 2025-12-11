<?php

declare(strict_types=1);

use JsonStream\Reader\StreamReader;

describe('ObjectIterator', function (): void {
    it('iterates over object properties', function (): void {
        $reader = StreamReader::fromString('{"a": 1, "b": 2, "c": 3}');
        $iterator = $reader->readObject();

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
    });

    it('provides correct string keys', function (): void {
        $reader = StreamReader::fromString('{"name": "Alice", "age": 30, "city": "NYC"}');
        $iterator = $reader->readObject();

        $keys = [];
        foreach ($iterator as $key => $value) {
            $keys[] = $key;
        }

        expect($keys)->toBe(['name', 'age', 'city']);
    });

    it('handles empty objects', function (): void {
        $reader = StreamReader::fromString('{}');
        $iterator = $reader->readObject();

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe([]);
    });

    it('handles objects with mixed value types', function (): void {
        $json = '{"str": "hello", "num": 42, "float": 3.14, "bool": true, "null": null, "arr": [1,2,3]}';
        $reader = StreamReader::fromString($json);
        $iterator = $reader->readObject();

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe([
            'str' => 'hello',
            'num' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'arr' => [1, 2, 3],
        ]);
    });

    it('handles nested objects', function (): void {
        $json = '{"user": {"name": "Alice", "age": 30}, "admin": false}';
        $reader = StreamReader::fromString($json);
        $iterator = $reader->readObject();

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe([
            'user' => ['name' => 'Alice', 'age' => 30],
            'admin' => false,
        ]);
    });

    it('has() checks for property existence', function (): void {
        $reader = StreamReader::fromString('{"name": "Alice", "age": 30}');
        $iterator = $reader->readObject();

        expect($iterator->has('name'))->toBeTrue();
        expect($iterator->has('age'))->toBeTrue();
        expect($iterator->has('city'))->toBeFalse();
    });

    it('get() retrieves property value', function (): void {
        $reader = StreamReader::fromString('{"name": "Alice", "age": 30}');
        $iterator = $reader->readObject();

        expect($iterator->get('name'))->toBe('Alice');
        expect($iterator->get('age'))->toBe(30);
    });

    it('get() returns default for missing property', function (): void {
        $reader = StreamReader::fromString('{"name": "Alice"}');
        $iterator = $reader->readObject();

        expect($iterator->get('city'))->toBeNull();
        expect($iterator->get('city', 'Unknown'))->toBe('Unknown');
    });

    it('get() and has() use cache', function (): void {
        $reader = StreamReader::fromString('{"a": 1, "b": 2, "c": 3}');
        $iterator = $reader->readObject();

        // Iterate to populate cache
        foreach ($iterator as $key => $value) {
            if ($key === 'b') {
                break;
            }
        }

        // Now has() should find 'a' and 'b' in cache
        expect($iterator->has('a'))->toBeTrue();
        expect($iterator->has('b'))->toBeTrue();
        expect($iterator->get('a'))->toBe(1);
        expect($iterator->get('b'))->toBe(2);
    });

    it('toArray() loads all properties', function (): void {
        $reader = StreamReader::fromString('{"name": "Alice", "age": 30, "city": "NYC"}');
        $iterator = $reader->readObject();

        $result = $iterator->toArray();

        expect($result)->toBe(['name' => 'Alice', 'age' => 30, 'city' => 'NYC']);
    });

    it('toArray() includes cached properties', function (): void {
        $reader = StreamReader::fromString('{"a": 1, "b": 2, "c": 3}');
        $iterator = $reader->readObject();

        // Iterate partially
        foreach ($iterator as $key => $value) {
            if ($key === 'b') {
                break;
            }
        }

        // toArray should include both cached and remaining
        $result = $iterator->toArray();

        expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
    });

    it('count() returns -1 for streaming mode', function (): void {
        $reader = StreamReader::fromString('{"a": 1, "b": 2}');
        $iterator = $reader->readObject();

        expect($iterator->count())->toBe(-1);
    });

    it('handles large objects efficiently', function (): void {
        // Create large object JSON
        $props = [];
        for ($i = 0; $i < 1000; $i++) {
            $props[] = '"prop'.$i.'": '.$i;
        }
        $json = '{'.implode(',', $props).'}';

        $reader = StreamReader::fromString($json);
        $iterator = $reader->readObject();

        $count = 0;
        foreach ($iterator as $value) {
            $count++;
            if ($count === 100) {
                break;
            }
        }

        expect($count)->toBe(100);
    });

    it('allows early termination', function (): void {
        $reader = StreamReader::fromString('{"a": 1, "b": 2, "c": 3, "d": 4, "e": 5}');
        $iterator = $reader->readObject();

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
            if ($key === 'c') {
                break;
            }
        }

        expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
    });

    it('handles objects with array values', function (): void {
        $json = '{"users": [{"id": 1}, {"id": 2}], "count": 2}';
        $reader = StreamReader::fromString($json);
        $iterator = $reader->readObject();

        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe([
            'users' => [['id' => 1], ['id' => 2]],
            'count' => 2,
        ]);
    });

    it('does not rewind after iteration starts', function (): void {
        $reader = StreamReader::fromString('{"a": 1, "b": 2}');
        $iterator = $reader->readObject();

        // First iteration
        $result1 = [];
        foreach ($iterator as $key => $value) {
            $result1[$key] = $value;
        }

        // Second iteration should be empty (no rewind for non-seekable)
        $result2 = [];
        foreach ($iterator as $key => $value) {
            $result2[$key] = $value;
        }

        expect($result1)->toBe(['a' => 1, 'b' => 2]);
        expect($result2)->toBe([]);
    });

    it('handles next() call when generator is null', function (): void {
        $json = '{"a": 1, "b": 2}';
        $reader = StreamReader::fromString($json);
        $iterator = $reader->readObject();

        // Exhaust the iterator
        foreach ($iterator as $value) {
            // Consume
        }

        // Now generator is null, calling next() should not crash (lines 130-132)
        $iterator->next();
        expect($iterator->valid())->toBeFalse();
    });
});
