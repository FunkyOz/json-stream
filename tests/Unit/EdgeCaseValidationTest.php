<?php

declare(strict_types=1);

use JsonStream\Exception\IOException;
use JsonStream\Exception\ParseException;
use JsonStream\Exception\PathException;
use JsonStream\Internal\JsonPath\ArraySliceSegment;
use JsonStream\Internal\JsonPath\FilterSegment;
use JsonStream\Internal\JsonPath\PathEvaluator;
use JsonStream\Internal\JsonPath\PathExpression;
use JsonStream\Internal\JsonPath\PathFilter;
use JsonStream\Internal\JsonPath\PathParser;
use JsonStream\Reader\ObjectIterator;
use JsonStream\Reader\StreamReader;

// Task 37: Stream Position in Fluent Methods
describe('Task 37: Stream Position in Fluent Methods', function (): void {
    it('resets seekable stream when using withPath after partial read', function (): void {
        $json = '{"name": "test", "age": 30}';
        $reader = StreamReader::fromString($json);

        // Partially read
        $reader->readAll();

        // withPath creates new instance — stream should be reset for seekable streams
        $reader2 = StreamReader::fromString($json);
        $reader2->readAll(); // consume some data
        $reader3 = $reader2->withPath('$.name');
        $result = $reader3->readAll();
        expect($result)->toBe('test');
    });

    it('resets seekable stream when using withBufferSize', function (): void {
        $json = '[1, 2, 3]';
        $reader = StreamReader::fromString($json);
        $reader->readAll(); // consume data
        $reader2 = $reader->withBufferSize(4096);
        $result = $reader2->readAll();
        expect($result)->toBe([1, 2, 3]);
    });

    it('resets seekable stream when using withMaxDepth', function (): void {
        $json = '{"nested": {"value": 42}}';
        $reader = StreamReader::fromString($json);
        $reader->readAll(); // consume data
        $reader2 = $reader->withMaxDepth(100);
        $result = $reader2->readAll();
        expect($result)->toBe(['nested' => ['value' => 42]]);
    });

    it('throws IOException for non-seekable stream after partial read', function (): void {
        // Create a non-seekable stream (pipe)
        $pipes = [];
        $process = proc_open('php -r "echo \'{\"a\":1}\';"', [1 => ['pipe', 'w']], $pipes);
        $stream = $pipes[1];

        $reader = StreamReader::fromStream($stream);
        $reader->readAll(); // consume data

        expect(fn () => $reader->withPath('$.a'))
            ->toThrow(IOException::class, 'Cannot use fluent methods after reading from a non-seekable stream');

        fclose($stream);
        proc_close($process);
    });

    it('allows fluent methods on unconsumed streams', function (): void {
        $json = '{"items": [1, 2, 3]}';
        $reader = StreamReader::fromString($json);
        // No data consumed — should work fine
        $reader2 = $reader->withPath('$.items[*]');
        $items = iterator_to_array($reader2->readItems());
        expect($items)->toBe([1, 2, 3]);
    });
});

// Task 38: UTF-16 Lone Surrogate Validation
describe('Task 38: UTF-16 Surrogate Validation', function (): void {
    it('rejects lone high surrogate', function (): void {
        // \uD800 is a high surrogate without a following low surrogate
        $json = '"\uD800"';
        $reader = StreamReader::fromString($json);

        expect(fn () => $reader->readAll())
            ->toThrow(ParseException::class, 'lone high UTF-16 surrogate');
    });

    it('rejects lone low surrogate', function (): void {
        // \uDC00 is a lone low surrogate
        $json = '"\uDC00"';
        $reader = StreamReader::fromString($json);

        expect(fn () => $reader->readAll())
            ->toThrow(ParseException::class, 'lone low UTF-16 surrogate');
    });

    it('handles valid surrogate pair', function (): void {
        // \uD83D\uDE00 = 😀 (U+1F600)
        $json = '"\uD83D\uDE00"';
        $reader = StreamReader::fromString($json);

        $result = $reader->readAll();
        expect($result)->toBe('😀');
    });

    it('rejects high surrogate followed by non-surrogate', function (): void {
        // \uD800\u0041 - high surrogate followed by 'A' (not a low surrogate)
        $json = '"\uD800\u0041"';
        $reader = StreamReader::fromString($json);

        expect(fn () => $reader->readAll())
            ->toThrow(ParseException::class, 'expected low surrogate');
    });

    it('rejects high surrogate at end of string', function (): void {
        $json = '"\uD800"';
        $reader = StreamReader::fromString($json);

        expect(fn () => $reader->readAll())
            ->toThrow(ParseException::class);
    });

    it('parses valid BMP characters', function (): void {
        // \u00E9 = é
        $json = '"\u00E9"';
        $reader = StreamReader::fromString($json);

        expect($reader->readAll())->toBe('é');
    });

    it('handles multiple surrogate pairs in one string', function (): void {
        // Two emoji: 😀😀
        $json = '"\uD83D\uDE00\uD83D\uDE00"';
        $reader = StreamReader::fromString($json);

        expect($reader->readAll())->toBe('😀😀');
    });
});

// Task 39: Negative Index Streaming Limitations
describe('Task 39: Negative Index Streaming', function (): void {
    it('throws PathException for negative start in slice', function (): void {
        expect(fn () => new ArraySliceSegment(-3, null, 1))
            ->toThrow(PathException::class, 'Negative array indices are not supported');
    });

    it('throws PathException for negative end in slice', function (): void {
        expect(fn () => new ArraySliceSegment(0, -2, 1))
            ->toThrow(PathException::class, 'Negative array indices are not supported');
    });

    it('allows positive slice indices', function (): void {
        $segment = new ArraySliceSegment(0, 5, 1);
        expect($segment->getStart())->toBe(0);
        expect($segment->getEnd())->toBe(5);
    });

    it('allows null slice indices', function (): void {
        $segment = new ArraySliceSegment(null, null, 1);
        expect($segment->getStart())->toBeNull();
        expect($segment->getEnd())->toBeNull();
    });

    it('throws via PathParser for negative slice', function (): void {
        $parser = new PathParser();
        expect(fn () => $parser->parse('$.items[-3:]'))
            ->toThrow(PathException::class, 'Negative array indices');
    });
});

// Task 40: Integer Overflow Handling
describe('Task 40: Integer Overflow Handling', function (): void {
    it('parses PHP_INT_MAX correctly', function (): void {
        $json = (string) PHP_INT_MAX;
        $reader = StreamReader::fromString($json);
        $result = $reader->readAll();

        expect($result)->toBe(PHP_INT_MAX);
    });

    it('converts number exceeding PHP_INT_MAX to float', function (): void {
        // PHP_INT_MAX + 1 should be float
        $bigNumber = '9999999999999999999999';
        $reader = StreamReader::fromString($bigNumber);
        $result = $reader->readAll();

        expect($result)->toBeFloat();
    });

    it('handles very large numbers as float', function (): void {
        $json = '99999999999999999999999999999';
        $reader = StreamReader::fromString($json);
        $result = $reader->readAll();

        expect($result)->toBeFloat();
    });

    it('handles normal integers without overflow', function (): void {
        $json = '42';
        $reader = StreamReader::fromString($json);

        expect($reader->readAll())->toBe(42);
    });

    it('handles negative numbers near overflow', function (): void {
        $json = '-' . PHP_INT_MAX;
        $reader = StreamReader::fromString($json);
        $result = $reader->readAll();

        expect($result)->toBe(-PHP_INT_MAX);
    });
});

// Task 41: ObjectIterator Cache Limits
describe('Task 41: ObjectIterator Cache Limits', function (): void {
    it('evicts oldest entries when cache is full', function (): void {
        // Create a JSON object with more properties than cache size
        $props = [];
        for ($i = 0; $i < 15; $i++) {
            $props["prop$i"] = $i;
        }
        $json = json_encode($props);

        $reader = StreamReader::fromString($json);
        $iterator = new ObjectIterator($reader, 5); // max 5 cached

        // Iterate through all properties
        $result = $iterator->toArray();
        expect(count($result))->toBe(15);
    });

    it('still returns correct values with bounded cache', function (): void {
        $json = '{"a": 1, "b": 2, "c": 3}';
        $reader = StreamReader::fromString($json);
        $iterator = new ObjectIterator($reader, 2);

        $result = $iterator->toArray();
        expect($result)->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
    });

    it('handles cache size of zero (no caching)', function (): void {
        $json = '{"a": 1, "b": 2}';
        $reader = StreamReader::fromString($json);
        $iterator = new ObjectIterator($reader, 0);

        $result = $iterator->toArray();
        expect($result)->toBe(['a' => 1, 'b' => 2]);
    });
});

// Task 42: PathFilter Depth Tracking
describe('Task 42: PathFilter Depth Tracking', function (): void {
    it('enforces depth limit during path traversal', function (): void {
        // Build deeply nested structure
        $data = 'value';
        for ($i = 0; $i < 600; $i++) {
            $data = ['nested' => $data];
        }

        $parser = new PathParser();
        $expression = $parser->parse('$..nested');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator, 100);

        expect(fn () => $filter->extract($data))
            ->toThrow(ParseException::class, 'Maximum depth of 100 exceeded');
    });

    it('handles data within depth limit', function (): void {
        $data = ['a' => ['b' => ['c' => 'value']]];

        $parser = new PathParser();
        $expression = $parser->parse('$.a.b.c');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator, 100);

        $results = $filter->extract($data);
        expect($results)->toBe(['value']);
    });
});

// Task 43: isAssociativeArray optimization (merged into Task 42)
// The optimization was applied inline in PathFilter.

// Task 44: PathExpression Caching
describe('Task 44: PathExpression Analysis Caching', function (): void {
    it('caches hasRecursive result', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');

        // Call multiple times - should return cached value
        expect($expression->hasRecursive())->toBeTrue();
        expect($expression->hasRecursive())->toBeTrue();
    });

    it('caches canUseSimpleStreaming result', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*]');

        expect($expression->canUseSimpleStreaming())->toBeTrue();
        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('returns false for recursive simple streaming', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');

        expect($expression->canUseSimpleStreaming())->toBeFalse();
    });
});

// Task 46: IOException File Path Consistency
describe('Task 46: IOException File Path', function (): void {
    it('sets file path on file not found exception', function (): void {
        try {
            StreamReader::fromFile('/nonexistent/file.json');
            expect(false)->toBeTrue(); // Should not reach here
        } catch (IOException $e) {
            expect($e->getFilePath())->toBe('/nonexistent/file.json');
            expect($e->getMessage())->toBe('File not found');
        }
    });

    it('sets file path on unreadable file exception', function (): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: chmod does not restrict access; use a directory instead
            // fopen() on a directory fails on Windows, triggering IOException
            $tempFile = tempnam(sys_get_temp_dir(), 'jsonstream_test_');
            unlink($tempFile);
            mkdir($tempFile);

            try {
                StreamReader::fromFile($tempFile);
                expect(false)->toBeTrue(); // Should not reach here
            } catch (IOException $e) {
                expect($e->getFilePath())->toBe($tempFile);
            } finally {
                rmdir($tempFile);
            }
        } else {
            // Unix: chmod makes the file unreadable
            $tempFile = tempnam(sys_get_temp_dir(), 'jsonstream_test_');
            chmod($tempFile, 0000);

            try {
                StreamReader::fromFile($tempFile);
                expect(false)->toBeTrue(); // Should not reach here
            } catch (IOException $e) {
                expect($e->getFilePath())->toBe($tempFile);
            } finally {
                chmod($tempFile, 0644);
                unlink($tempFile);
            }
        }
    });
});

// Task 47: Unused Config Constants removed
describe('Task 47: Config Constants', function (): void {
    it('does not have MODE_RELAXED constant', function (): void {
        $reflection = new ReflectionClass(\JsonStream\Config::class);
        $constants = $reflection->getConstants();

        expect($constants)->not->toHaveKey('MODE_RELAXED');
    });

    it('does not have ENCODE constants', function (): void {
        $reflection = new ReflectionClass(\JsonStream\Config::class);
        $constants = $reflection->getConstants();

        expect($constants)->not->toHaveKey('ENCODE_NUMERIC_CHECK');
        expect($constants)->not->toHaveKey('ENCODE_PRETTY_PRINT');
        expect($constants)->not->toHaveKey('ENCODE_UNESCAPED_SLASHES');
        expect($constants)->not->toHaveKey('ENCODE_UNESCAPED_UNICODE');
    });
});

// Task 48: ReDoS Prevention
describe('Task 48: ReDoS Prevention', function (): void {
    it('rejects overly long filter expressions', function (): void {
        $longExpr = '@.property == ' . str_repeat('a', 1000);

        expect(fn () => new FilterSegment($longExpr))
            ->toThrow(PathException::class, 'Filter expression too long');
    });

    it('accepts normal filter expressions', function (): void {
        $segment = new FilterSegment('@.price > 10');
        expect($segment->getExpression())->toBe('@.price > 10');
    });

    it('accepts filter expressions at limit', function (): void {
        $expr = '@.prop == ' . str_repeat('a', 985); // Just under 1000
        $segment = new FilterSegment($expr);
        expect($segment->getExpression())->toBe($expr);
    });
});
