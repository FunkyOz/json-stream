<?php

use JsonStream\Internal\JsonPath\PathParser;

describe('PathExpression', function (): void {
    // Tests for getOriginalPath()
    it('getOriginalPath returns the original path string', function (): void {
        $parser = new PathParser();
        $path = '$.items[*].name';
        $expression = $parser->parse($path);

        expect($expression->getOriginalPath())->toBe($path);
    });

    it('getOriginalPath returns simple path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');

        expect($expression->getOriginalPath())->toBe('$.users');
    });

    // Tests for getSegments()
    it('getSegments returns array of segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');

        $segments = $expression->getSegments();
        expect($segments)->toBeArray();
        expect(count($segments))->toBeGreaterThan(0);
    });

    // Tests for getSegmentCount()
    it('getSegmentCount returns correct count for simple path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');

        expect($expression->getSegmentCount())->toBe(2); // root + users
    });

    it('getSegmentCount returns correct count for nested path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.books[0]');

        expect($expression->getSegmentCount())->toBe(4); // root + store + books + [0]
    });

    // Tests for getSegment()
    it('getSegment returns segment at valid index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');

        $segment = $expression->getSegment(0);
        expect($segment)->not()->toBeNull();
    });

    it('getSegment returns null for invalid index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');

        $segment = $expression->getSegment(100);
        expect($segment)->toBeNull();
    });

    // Tests for hasRecursive()
    it('hasRecursive returns true for recursive descent paths', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');

        expect($expression->hasRecursive())->toBeTrue();
    });

    it('hasRecursive returns false for non-recursive paths', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users.name');

        expect($expression->hasRecursive())->toBeFalse();
    });

    // Tests for canStreamArrayElements()
    it('canStreamArrayElements returns false for recursive paths', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..items');

        expect($expression->canStreamArrayElements())->toBeFalse();
    });

    it('canStreamArrayElements returns true for non-recursive paths', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');

        expect($expression->canStreamArrayElements())->toBeTrue();
    });

    it('canStreamArrayElements returns true for simple property paths', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users.data');

        expect($expression->canStreamArrayElements())->toBeTrue();
    });

    // Tests for hasEarlyTermination()
    it('hasEarlyTermination returns true for specific array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');

        expect($expression->hasEarlyTermination())->toBeTrue();
    });

    it('hasEarlyTermination returns true for bounded array slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0:10]');

        expect($expression->hasEarlyTermination())->toBeTrue();
    });

    it('hasEarlyTermination returns false for wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');

        expect($expression->hasEarlyTermination())->toBeFalse();
    });

    it('hasEarlyTermination returns false for negative array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[-1]');

        expect($expression->hasEarlyTermination())->toBeFalse();
    });

    it('hasEarlyTermination returns false for unbounded slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5:]');

        expect($expression->hasEarlyTermination())->toBeFalse();
    });

    // Tests for getTerminationIndex()
    it('getTerminationIndex returns index + 1 for specific index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');

        expect($expression->getTerminationIndex())->toBe(6); // 5 + 1
    });

    it('getTerminationIndex returns end for bounded slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0:10]');

        expect($expression->getTerminationIndex())->toBe(10);
    });

    it('getTerminationIndex returns null for wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');

        expect($expression->getTerminationIndex())->toBeNull();
    });

    it('getTerminationIndex returns null for negative index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[-1]');

        expect($expression->getTerminationIndex())->toBeNull();
    });

    it('getTerminationIndex returns null for unbounded slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5:]');

        expect($expression->getTerminationIndex())->toBeNull();
    });

    it('getTerminationIndex returns null for slice with end of 0', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0:0]');

        expect($expression->getTerminationIndex())->toBeNull();
    });

    // Tests for canUseSimpleStreaming()
    it('canUseSimpleStreaming returns true for simple wildcard pattern', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');

        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns true for property then wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.items[*]');

        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns true for array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0]');

        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns true for array slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0:10]');

        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns false for root only path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');

        expect($expression->canUseSimpleStreaming())->toBeFalse();
    });

    it('canUseSimpleStreaming returns false for recursive descent', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..items');

        expect($expression->canUseSimpleStreaming())->toBeFalse();
    });

    it('canUseSimpleStreaming returns true for wildcard followed by property', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*].name');

        // Now supported: parse array element and extract property via walkValue()
        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns false for multiple wildcards', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*].nested[*]');

        // Multiple wildcards require nested streaming - not yet supported
        expect($expression->canUseSimpleStreaming())->toBeFalse();
    });

    it('canUseSimpleStreaming returns true for filter expressions', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[?(@.price > 10)]');

        // Now supported: filter expressions use streaming with value evaluation
        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns true for array index followed by property', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0].name');

        // Now supported: parse element at index and extract property via walkValue()
        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });

    it('canUseSimpleStreaming returns true for array slice followed by property', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0:5].name');

        // Now supported: parse sliced elements and extract property via walkValue()
        expect($expression->canUseSimpleStreaming())->toBeTrue();
    });
});
