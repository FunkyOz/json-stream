<?php

declare(strict_types=1);

use JsonStream\Reader\StreamReader;

describe('ItemIterator', function (): void {
    describe('array handling', function (): void {
        it('iterates over arrays', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3, 4, 5]');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $key => $value) {
                $result[$key] = $value;
            }

            expect($result)->toBe([0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5]);
        });

        it('getType() returns array for arrays', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3]');
            $iterator = $reader->readItems();

            foreach ($iterator as $value) {
                expect($iterator->getType())->toBe('number');
                break;
            }
        });

        it('isArray() returns true for array elements', function (): void {
            $reader = StreamReader::fromString('[[1, 2], [3, 4]]');
            $iterator = $reader->readItems();

            foreach ($iterator as $value) {
                expect($iterator->isArray())->toBeTrue();
                expect($iterator->isObject())->toBeFalse();
                break;
            }
        });
    });

    describe('object handling', function (): void {
        it('iterates over objects', function (): void {
            $reader = StreamReader::fromString('{"a": 1, "b": 2, "c": 3}');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $key => $value) {
                $result[$key] = $value;
            }

            expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
        });

        it('keys are strings for objects', function (): void {
            $reader = StreamReader::fromString('{"name": "Alice", "age": 30}');
            $iterator = $reader->readItems();

            $keys = [];
            foreach ($iterator as $key => $value) {
                $keys[] = $key;
                expect(is_string($key))->toBeTrue();
            }

            expect($keys)->toBe(['name', 'age']);
        });

        it('isObject() returns true for object properties', function (): void {
            $reader = StreamReader::fromString('{"user": {"name": "Alice"}, "admin": true}');
            $iterator = $reader->readItems();

            foreach ($iterator as $key => $value) {
                if ($key === 'user') {
                    expect($iterator->isObject())->toBeTrue();
                    expect($iterator->isArray())->toBeFalse();
                    break;
                }
            }
        });
    });

    describe('scalar handling', function (): void {
        it('handles string scalars', function (): void {
            $reader = StreamReader::fromString('"hello world"');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $value) {
                $result[] = $value;
            }

            expect($result)->toBe(['hello world']);
            expect($iterator->getType())->toBe('string');
        });

        it('handles number scalars', function (): void {
            $reader = StreamReader::fromString('42');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $value) {
                $result[] = $value;
            }

            expect($result)->toBe([42]);
            expect($iterator->getType())->toBe('number');
        });

        it('handles boolean scalars', function (): void {
            $reader = StreamReader::fromString('true');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $value) {
                $result[] = $value;
            }

            expect($result)->toBe([true]);
            expect($iterator->getType())->toBe('boolean');
        });

        it('handles null scalars', function (): void {
            $reader = StreamReader::fromString('null');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $value) {
                $result[] = $value;
            }

            expect($result)->toBe([null]);
            expect($iterator->getType())->toBe('null');
        });

        it('scalar keys are null', function (): void {
            $reader = StreamReader::fromString('42');
            $iterator = $reader->readItems();

            foreach ($iterator as $key => $value) {
                expect($key)->toBeNull();
            }
        });
    });

    describe('type checking methods', function (): void {
        it('getType() returns correct types', function (): void {
            $json = '{"str": "hello", "num": 42, "bool": true, "null": null, "arr": [1,2], "obj": {"a": 1}}';
            $reader = StreamReader::fromString($json);
            $iterator = $reader->readItems();

            $types = [];
            foreach ($iterator as $key => $value) {
                $types[$key] = $iterator->getType();
            }

            expect($types['str'])->toBe('string');
            expect($types['num'])->toBe('number');
            expect($types['bool'])->toBe('boolean');
            expect($types['null'])->toBe('null');
            expect($types['arr'])->toBe('array');
            expect($types['obj'])->toBe('object');
        });

        it('isArray() correctly identifies arrays', function (): void {
            $reader = StreamReader::fromString('{"arr": [1,2], "str": "hello"}');
            $iterator = $reader->readItems();

            foreach ($iterator as $key => $value) {
                if ($key === 'arr') {
                    expect($iterator->isArray())->toBeTrue();
                } else {
                    expect($iterator->isArray())->toBeFalse();
                }
            }
        });

        it('isObject() correctly identifies objects', function (): void {
            $reader = StreamReader::fromString('{"obj": {"a": 1}, "num": 42}');
            $iterator = $reader->readItems();

            foreach ($iterator as $key => $value) {
                if ($key === 'obj') {
                    expect($iterator->isObject())->toBeTrue();
                } else {
                    expect($iterator->isObject())->toBeFalse();
                }
            }
        });
    });

    describe('toArray() method', function (): void {
        it('loads arrays', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3]');
            $iterator = $reader->readItems();

            $result = $iterator->toArray();

            expect($result)->toBe([1, 2, 3]);
        });

        it('loads objects', function (): void {
            $reader = StreamReader::fromString('{"a": 1, "b": 2}');
            $iterator = $reader->readItems();

            $result = $iterator->toArray();

            expect($result)->toBe(['a' => 1, 'b' => 2]);
        });

        it('loads scalars', function (): void {
            $reader = StreamReader::fromString('"hello"');
            $iterator = $reader->readItems();

            $result = $iterator->toArray();

            expect($result)->toBe(['hello']);
        });
    });

    describe('mixed structures', function (): void {
        it('handles arrays of objects', function (): void {
            $json = '[{"id": 1, "name": "Alice"}, {"id": 2, "name": "Bob"}]';
            $reader = StreamReader::fromString($json);
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $key => $value) {
                $result[$key] = $value;
                expect($iterator->isObject())->toBeTrue();
            }

            expect($result)->toBe([
                0 => ['id' => 1, 'name' => 'Alice'],
                1 => ['id' => 2, 'name' => 'Bob'],
            ]);
        });

        it('handles objects with array values', function (): void {
            $json = '{"users": [{"id": 1}, {"id": 2}], "count": 2}';
            $reader = StreamReader::fromString($json);
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $key => $value) {
                $result[$key] = $value;
            }

            expect($result)->toBe([
                'users' => [['id' => 1], ['id' => 2]],
                'count' => 2,
            ]);
        });

        it('handles deeply nested structures', function (): void {
            $json = '{"level1": {"level2": {"level3": [1, 2, 3]}}}';
            $reader = StreamReader::fromString($json);
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $key => $value) {
                $result[$key] = $value;
            }

            expect($result)->toBe([
                'level1' => ['level2' => ['level3' => [1, 2, 3]]],
            ]);
        });
    });

    describe('iteration behavior', function (): void {
        it('allows early termination', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3, 4, 5]');
            $iterator = $reader->readItems();

            $result = [];
            foreach ($iterator as $value) {
                $result[] = $value;
                if ($value === 3) {
                    break;
                }
            }

            expect($result)->toBe([1, 2, 3]);
        });

        it('does not rewind after iteration starts', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3]');
            $iterator = $reader->readItems();

            // First iteration
            $result1 = [];
            foreach ($iterator as $value) {
                $result1[] = $value;
            }

            // Second iteration should be empty
            $result2 = [];
            foreach ($iterator as $value) {
                $result2[] = $value;
            }

            expect($result1)->toBe([1, 2, 3]);
            expect($result2)->toBe([]);
        });

        it('handles next() call when generator is null', function (): void {
            $reader = StreamReader::fromString('[1, 2]');
            $iterator = $reader->readItems();

            // Exhaust the iterator
            foreach ($iterator as $value) {
                // Consume
            }

            // Now generator is null, calling next() should not crash (lines 139-141)
            $iterator->next();
            expect($iterator->valid())->toBeFalse();
        });

        it('getType returns number for numeric values', function (): void {
            $reader = StreamReader::fromString('[1, 2.5]');
            $iterator = $reader->readItems();

            $types = [];
            foreach ($iterator as $value) {
                $types[] = $iterator->getType();
            }

            // Both integers and floats are returned as 'number' type
            expect($types)->toBe(['number', 'number']);
        });
    });
});
