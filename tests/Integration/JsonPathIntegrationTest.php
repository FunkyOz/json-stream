<?php

use JsonStream\Exception\PathException;
use JsonStream\Reader\StreamReader;

describe('JSONPath Integration', function (): void {
    it('creates reader with valid JSONPath', function (): void {
        $json = '{"users": [{"name": "Alice"}, {"name": "Bob"}]}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.users[*].name');

        expect($reader)->toBeInstanceOf(StreamReader::class);
        expect($reader->hasPathFilter())->toBeTrue();
    });

    it('throws PathException for invalid JSONPath', function (): void {
        $json = '{"users": []}';

        expect(function () use ($json): void {
            StreamReader::fromString($json)->withPath('invalid');
        })->toThrow(PathException::class);
    });

    it('parses complex JSONPath expression', function (): void {
        $json = '{"store": {"book": [{"title": "Book 1", "price": 10}]}}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.store.book[?(@.price < 20)]');

        expect($reader->hasPathFilter())->toBeTrue();
    });

    it('accepts various JSONPath operators', function (): void {
        $json = '{"data": []}';

        // Root path
        $reader1 = StreamReader::fromString($json)->withPath('$');
        expect($reader1->hasPathFilter())->toBeTrue();

        // Property access
        $reader2 = StreamReader::fromString($json)->withPath('$.data');
        expect($reader2->hasPathFilter())->toBeTrue();

        // Array wildcard
        $reader3 = StreamReader::fromString($json)->withPath('$.data[*]');
        expect($reader3->hasPathFilter())->toBeTrue();

        // Array index
        $reader4 = StreamReader::fromString($json)->withPath('$.data[0]');
        expect($reader4->hasPathFilter())->toBeTrue();

        // Array slice
        $reader5 = StreamReader::fromString($json)->withPath('$.data[0:5]');
        expect($reader5->hasPathFilter())->toBeTrue();

        // Recursive descent
        $reader6 = StreamReader::fromString($json)->withPath('$..name');
        expect($reader6->hasPathFilter())->toBeTrue();

        // Filter expression
        $reader7 = StreamReader::fromString($json)->withPath('$.data[?(@.active == true)]');
        expect($reader7->hasPathFilter())->toBeTrue();
    });

    it('provides PathException with helpful error message', function (): void {
        $json = '{"data": []}';

        try {
            StreamReader::fromString($json)->withPath('$.users[');
            expect(false)->toBeTrue(); // Should not reach here
        } catch (PathException $e) {
            expect($e->getMessage())->toContain('Expected');
            expect($e->getPath())->toBe('$.users[');
        }
    });

    it('handles empty path', function (): void {
        $json = '{"data": []}';

        expect(function () use ($json): void {
            StreamReader::fromString($json)->withPath('');
        })->toThrow(PathException::class);
    });

    it('preserves other reader config with path', function (): void {
        $json = '{"data": []}';

        $reader = StreamReader::fromString($json)
            ->withBufferSize(2048)
            ->withMaxDepth(64)
            ->withPath('$.data');

        expect($reader->hasPathFilter())->toBeTrue();

        $stats = $reader->getStats();
        expect($stats['bufferSize'])->toBe(2048);
        expect($stats['maxDepth'])->toBe(64);
    });
});
