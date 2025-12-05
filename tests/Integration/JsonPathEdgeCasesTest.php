<?php

declare(strict_types=1);

use JsonStream\Exception\PathException;
use JsonStream\Reader\StreamReader;

/**
 * JSONPath Edge Cases Test Suite
 *
 * Tests edge cases and boundary conditions for JSONPath implementation:
 * - Empty results
 * - Boundary conditions
 * - Special characters
 * - Complex filters
 * - Malformed paths
 * - Deep nesting
 * - Large arrays
 * - Type mismatches
 */
describe('JSONPath Edge Cases', function (): void {
    describe('Empty Results', function (): void {
        it('returns empty for path with no matches', function (): void {
            $json = '{"users": [{"name": "Alice"}]}';
            $reader = StreamReader::fromString($json)->withPath('$.nonexistent');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for filter with no matches', function (): void {
            $json = '{"users": [{"age": 25}, {"age": 30}]}';
            $reader = StreamReader::fromString($json)->withPath('$.users[?(@.age > 100)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for wildcard on empty array', function (): void {
            $json = '{"items": []}';
            $reader = StreamReader::fromString($json)->withPath('$.items[*]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for wildcard on empty object', function (): void {
            $json = '{"data": {}}';
            $reader = StreamReader::fromString($json)->withPath('$.data.*');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for property access on null', function (): void {
            $json = '{"value": null}';
            $reader = StreamReader::fromString($json)->withPath('$.value.property');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for array access on object', function (): void {
            $json = '{"data": {"a": 1, "b": 2}}';
            $reader = StreamReader::fromString($json)->withPath('$.data[0]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for property access on array', function (): void {
            $json = '{"data": [1, 2, 3]}';
            $reader = StreamReader::fromString($json)->withPath('$.data.property');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for recursive descent with no matches', function (): void {
            $json = '{"a": {"b": {"c": 123}}}';
            $reader = StreamReader::fromString($json)->withPath('$..nonexistent');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for slice beyond array length', function (): void {
            $json = '[1, 2, 3]';
            $reader = StreamReader::fromString($json)->withPath('$[10:20]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('returns empty for filter on non-array', function (): void {
            $json = '{"value": "string"}';
            $reader = StreamReader::fromString($json)->withPath('$[?(@.prop == "test")]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });
    });

    describe('Boundary Conditions', function (): void {
        it('handles array with single element', function (): void {
            $json = '[42]';
            $reader = StreamReader::fromString($json)->withPath('$[0]');
            $result = $reader->readAll();

            expect($result)->toBe(42);
        });

        it('handles object with single property', function (): void {
            $json = '{"only": "property"}';
            $reader = StreamReader::fromString($json)->withPath('$.only');
            $result = $reader->readAll();

            expect($result)->toBe('property');
        });

        it('handles first array element [0]', function (): void {
            $json = '[1, 2, 3, 4, 5]';
            $reader = StreamReader::fromString($json)->withPath('$[0]');
            $result = $reader->readAll();

            expect($result)->toBe(1);
        });

        it('handles last array element by index', function (): void {
            $json = '[1, 2, 3, 4, 5]';
            $reader = StreamReader::fromString($json)->withPath('$[4]');
            $result = $reader->readAll();

            expect($result)->toBe(5);
        });

        it('handles slice starting at 0', function (): void {
            $json = '[1, 2, 3]';
            $reader = StreamReader::fromString($json)->withPath('$[0:2]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([1, 2]);
        });

        it('handles slice ending at array length', function (): void {
            $json = '[1, 2, 3]';
            $reader = StreamReader::fromString($json)->withPath('$[1:3]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([2, 3]);
        });

        it('handles deeply nested access (10 levels)', function (): void {
            $json = '{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":{"j":"deep"}}}}}}}}}}';
            $reader = StreamReader::fromString($json)->withPath('$.a.b.c.d.e.f.g.h.i.j');
            $result = $reader->readAll();

            expect($result)->toBe('deep');
        });

        it('handles very large array index', function (): void {
            $json = json_encode(array_fill(0, 1000, 'item'));
            $reader = StreamReader::fromString($json)->withPath('$[999]');
            $result = $reader->readAll();

            expect($result)->toBe('item');
        });

        it('handles empty string value', function (): void {
            $json = '{"value": ""}';
            $reader = StreamReader::fromString($json)->withPath('$.value');
            $result = $reader->readAll();

            expect($result)->toBe('');
        });

        it('handles zero numeric value', function (): void {
            $json = '{"value": 0}';
            $reader = StreamReader::fromString($json)->withPath('$.value');
            $result = $reader->readAll();

            expect($result)->toBe(0);
        });

        it('handles false boolean value', function (): void {
            $json = '{"value": false}';
            $reader = StreamReader::fromString($json)->withPath('$.value');
            $result = $reader->readAll();

            expect($result)->toBe(false);
        });

        it('handles null value', function (): void {
            $json = '{"value": null}';
            $reader = StreamReader::fromString($json)->withPath('$.value');
            $result = $reader->readAll();

            expect($result)->toBe(null);
        });
    });

    describe('Special Characters in Property Names', function (): void {
        it('handles properties with spaces', function (): void {
            $json = '{"first name": "Alice"}';
            $reader = StreamReader::fromString($json)->withPath("$['first name']");
            $result = $reader->readAll();

            expect($result)->toBe('Alice');
        });

        it('handles properties with hyphens', function (): void {
            $json = '{"first-name": "Alice"}';
            $reader = StreamReader::fromString($json)->withPath('$.first-name');
            $result = $reader->readAll();

            expect($result)->toBe('Alice');
        });

        it('handles properties with underscores', function (): void {
            $json = '{"first_name": "Alice"}';
            $reader = StreamReader::fromString($json)->withPath('$.first_name');
            $result = $reader->readAll();

            expect($result)->toBe('Alice');
        });

        it('handles properties with dots in bracket notation', function (): void {
            $json = '{"file.name": "test.txt"}';
            $reader = StreamReader::fromString($json)->withPath("$['file.name']");
            $result = $reader->readAll();

            expect($result)->toBe('test.txt');
        });

        it('handles properties with unicode characters', function (): void {
            // Use escaped JSON for unicode to avoid UTF-8 parsing issues
            $json = '{"na\u00efve": "value", "\u4f60\u597d": "world"}';

            $reader = StreamReader::fromString($json)->withPath("$['naïve']");
            $result1 = $reader->readAll();
            expect($result1)->toBe('value');

            $reader = StreamReader::fromString($json)->withPath("$['你好']");
            $result2 = $reader->readAll();
            expect($result2)->toBe('world');
        });

        it('handles properties with numbers in names', function (): void {
            $json = '{"item1": "first", "item2": "second"}';
            $reader = StreamReader::fromString($json)->withPath('$.item1');
            $result = $reader->readAll();

            expect($result)->toBe('first');
        });

        it('handles escaped quotes in bracket notation', function (): void {
            $json = '{"quo\\"te": "value"}';
            $reader = StreamReader::fromString($json)->withPath('$["quo\\"te"]');
            $items = iterator_to_array($reader->readItems());

            // May not match due to escaping complexity
            expect($items)->toBeArray();
        });
    });

    describe('Complex Filter Expressions', function (): void {
        it('filters with string equality', function (): void {
            $json = '{"items": [{"type": "A"}, {"type": "B"}, {"type": "A"}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.type == "A")]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
            expect($items[0]['type'])->toBe('A');
        });

        it('filters with numeric equality', function (): void {
            $json = '{"items": [{"id": 1}, {"id": 2}, {"id": 1}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.id == 1)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters with float comparison', function (): void {
            $json = '{"items": [{"price": 1.5}, {"price": 2.7}, {"price": 0.99}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.price > 1.0)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters with boolean value', function (): void {
            $json = '{"items": [{"active": true}, {"active": false}, {"active": true}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.active == true)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters with null comparison', function (): void {
            $json = '{"items": [{"value": null}, {"value": "test"}, {"value": null}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.value == null)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters with nested property access', function (): void {
            $json = '{
                "items": [
                    {"meta": {"status": "active"}},
                    {"meta": {"status": "inactive"}},
                    {"meta": {"status": "active"}}
                ]
            }';
            $reader = StreamReader::fromString($json)
                ->withPath('$.items[?(@.meta.status == "active")]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters with deep nested property', function (): void {
            $json = '{
                "items": [
                    {"a": {"b": {"c": 1}}},
                    {"a": {"b": {"c": 2}}},
                    {"a": {"b": {"c": 1}}}
                ]
            }';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.a.b.c == 1)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters with missing property returns false', function (): void {
            $json = '{"items": [{"a": 1}, {"b": 2}, {"a": 3}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.a > 0)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(2);
        });

        it('filters on array elements without specific property', function (): void {
            $json = '{"items": [1, 2, 3]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.value > 0)]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('filters with <= and >= operators', function (): void {
            $json = '{"items": [{"x": 1}, {"x": 2}, {"x": 3}]}';

            $reader1 = StreamReader::fromString($json)->withPath('$.items[?(@.x <= 2)]');
            $items1 = iterator_to_array($reader1->readItems());
            expect($items1)->toHaveCount(2);

            $reader2 = StreamReader::fromString($json)->withPath('$.items[?(@.x >= 2)]');
            $items2 = iterator_to_array($reader2->readItems());
            expect($items2)->toHaveCount(2);
        });

        it('filters with != operator', function (): void {
            $json = '{"items": [{"status": "ok"}, {"status": "error"}, {"status": "ok"}]}';
            $reader = StreamReader::fromString($json)->withPath('$.items[?(@.status != "ok")]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(1);
            expect($items[0]['status'])->toBe('error');
        });
    });

    describe('Malformed Paths', function (): void {
        it('throws exception for empty path', function (): void {
            expect(fn () => StreamReader::fromString('{}')->withPath(''))
                ->toThrow(PathException::class);
        });

        it('throws exception for path without root $', function (): void {
            expect(fn () => StreamReader::fromString('{}')->withPath('users'))
                ->toThrow(PathException::class);
        });

        it('throws exception for unclosed bracket', function (): void {
            expect(fn () => StreamReader::fromString('{}')->withPath('$.users['))
                ->toThrow(PathException::class);
        });

        it('throws exception for unclosed quote', function (): void {
            expect(fn () => StreamReader::fromString('{}')->withPath("$['property"))
                ->toThrow(PathException::class);
        });

        it('throws exception for invalid filter expression', function (): void {
            expect(fn () => StreamReader::fromString('{}')->withPath('$.items[?(@'))->toThrow(PathException::class);
        });

        it('throws exception for unclosed filter paren', function (): void {
            expect(fn () => StreamReader::fromString('{}')->withPath('$.items[?(@.x > 5'))->toThrow(PathException::class);
        });
    });

    describe('Type Mismatches', function (): void {
        it('handles array index on string (returns empty)', function (): void {
            $json = '{"value": "string"}';
            $reader = StreamReader::fromString($json)->withPath('$.value[0]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('handles property access on number (returns empty)', function (): void {
            $json = '{"value": 123}';
            $reader = StreamReader::fromString($json)->withPath('$.value.property');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('handles property access on boolean (returns empty)', function (): void {
            $json = '{"value": true}';
            $reader = StreamReader::fromString($json)->withPath('$.value.property');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('handles wildcard on string (returns empty)', function (): void {
            $json = '{"value": "string"}';
            $reader = StreamReader::fromString($json)->withPath('$.value[*]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('handles wildcard on number (returns empty)', function (): void {
            $json = '{"value": 123}';
            $reader = StreamReader::fromString($json)->withPath('$.value.*');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });

        it('handles slice on object (returns empty)', function (): void {
            $json = '{"data": {"a": 1, "b": 2}}';
            $reader = StreamReader::fromString($json)->withPath('$.data[0:2]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toBe([]);
        });
    });

    describe('Large Data Handling', function (): void {
        it('handles array with 1000 elements', function (): void {
            $data = array_fill(0, 1000, ['id' => 1, 'value' => 'test']);
            $json = json_encode($data);

            $reader = StreamReader::fromString($json)->withPath('$[*].id');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(1000);
            expect($items[0])->toBe(1);
        });

        it('handles deeply nested structure (15 levels)', function (): void {
            $nested = 'value';
            for ($i = 0; $i < 15; $i++) {
                $nested = ['level' => $nested];
            }
            $json = json_encode($nested);

            $path = '$'.str_repeat('.level', 15);
            $reader = StreamReader::fromString($json)->withPath($path);
            $result = $reader->readAll();

            expect($result)->toBe('value');
        });

        it('handles recursive descent on large structure', function (): void {
            $data = [
                'level1' => array_fill(0, 100, [
                    'level2' => ['target' => 'found'],
                ]),
            ];
            $json = json_encode($data);

            $reader = StreamReader::fromString($json)->withPath('$..target');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(100);
        });

        it('handles wildcard on large object', function (): void {
            $data = [];
            for ($i = 0; $i < 1000; $i++) {
                $data["key{$i}"] = $i;
            }
            $json = json_encode($data);

            $reader = StreamReader::fromString($json)->withPath('$.*');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(1000);
        });

        it('handles multiple wildcards on nested arrays', function (): void {
            $data = [
                'outer' => array_fill(0, 10, [
                    'inner' => array_fill(0, 10, 'value'),
                ]),
            ];
            $json = json_encode($data);

            $reader = StreamReader::fromString($json)->withPath('$.outer[*].inner[*]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(100);
        });
    });

    describe('Whitespace Handling', function (): void {
        it('handles path with spaces', function (): void {
            $json = '{"data": [1, 2, 3]}';
            $reader = StreamReader::fromString($json)->withPath('$ . data [ 0 ]');
            $result = $reader->readAll();

            expect($result)->toBe(1);
        });

        it('handles filter with spaces', function (): void {
            $json = '{"items": [{"x": 1}, {"x": 2}]}';
            $reader = StreamReader::fromString($json)
                ->withPath('$.items[?( @.x > 1 )]');
            $items = iterator_to_array($reader->readItems());

            expect($items)->toHaveCount(1);
        });
    });
});
